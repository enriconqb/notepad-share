<?php

declare(strict_types=1);

use App\Csrf;
use App\HistoryStore;
use App\Http;
use App\MarkdownRenderer;
use App\NoteStore;
use App\Router;
use App\SessionService;
use App\Slug;
use App\UploadStore;
use App\YjsStore;

final class Kernel
{
    public function __construct(
        private Router $router,
        private NoteStore $notes,
        private HistoryStore $history,
        private UploadStore $uploads,
        private MarkdownRenderer $markdown,
        private YjsStore $yjs,
        private string $basePath,
        private string $views,
        private string $publicDir,
    ) {
        $this->register();
    }

    private function register(): void
    {
        $r = $this->router;
        $r->add('GET', '/', fn () => $this->landing());
        $r->add('GET', '/n/(?P<slug>[a-z0-9-]+)', fn (string $slug) => $this->editor($slug));
        $r->add('GET', '/n/(?P<slug>[a-z0-9-]+)/img/(?P<id>[a-f0-9]+)', fn (string $slug, string $id) => $this->image($slug, $id));
        $r->add('GET', '/api/session', fn () => Http::json(SessionService::payload()));
        $r->add('PATCH', '/api/session', fn () => $this->patchSession());
        $r->add('POST', '/api/notes', fn () => $this->createNote());
        $r->add('GET', '/api/notes/(?P<slug>[a-z0-9-]+)', fn (string $slug) => $this->getNote($slug));
        $r->add('PUT', '/api/notes/(?P<slug>[a-z0-9-]+)', fn (string $slug) => $this->putNote($slug));
        $r->add('POST', '/api/notes/(?P<slug>[a-z0-9-]+)/persist', fn (string $slug) => $this->persistNote($slug));
        $r->add('GET', '/api/notes/(?P<slug>[a-z0-9-]+)/sync', fn (string $slug) => $this->getSync($slug));
        $r->add('POST', '/api/notes/(?P<slug>[a-z0-9-]+)/sync', fn (string $slug) => $this->postSync($slug));
        $r->add('GET', '/api/notes/(?P<slug>[a-z0-9-]+)/events', fn (string $slug) => $this->sse($slug));
        $r->add('POST', '/api/notes/(?P<slug>[a-z0-9-]+)/presence', fn (string $slug) => $this->presence($slug));
        $r->add('GET', '/api/notes/(?P<slug>[a-z0-9-]+)/history', fn (string $slug) => $this->listHistory($slug));
        $r->add('POST', '/api/notes/(?P<slug>[a-z0-9-]+)/history', fn (string $slug) => $this->addHistory($slug));
        $r->add('POST', '/api/notes/(?P<slug>[a-z0-9-]+)/history/(?P<id>[0-9]+_[a-zA-Z0-9]+)/restore', fn (string $slug, string $id) => $this->restoreHistory($slug, $id));
        $r->add('DELETE', '/api/notes/(?P<slug>[a-z0-9-]+)/history', fn (string $slug) => $this->clearHistory($slug));
        $r->add('POST', '/api/notes/(?P<slug>[a-z0-9-]+)/images', fn (string $slug) => $this->upload($slug));
        $r->add('POST', '/api/preview', fn () => $this->preview());
    }

    public function run(string $method, string $path): void
    {
        $this->router->dispatch($method, $path);
    }

    private function requireSlug(string $slug): string
    {
        $slug = Slug::normalize($slug);
        if (!Slug::valid($slug)) {
            Http::error('slug', 'Slug tidak valid', 400);
        }
        return $slug;
    }

    private function landing(): void
    {
        $base = htmlspecialchars($this->basePath, ENT_QUOTES, 'UTF-8');
        include $this->views . '/landing.php';
        exit;
    }

    private function editor(string $slug): void
    {
        $slug = $this->requireSlug($slug);
        $this->notes->ensure($slug);
        $html = file_get_contents($this->publicDir . '/assets/app.html');
        $html = str_replace(
            ['{{BASE}}', '{{SLUG}}'],
            [htmlspecialchars($this->basePath, ENT_QUOTES, 'UTF-8'), htmlspecialchars($slug, ENT_QUOTES, 'UTF-8')],
            $html
        );
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    private function image(string $slug, string $id): void
    {
        $slug = $this->requireSlug($slug);
        $file = $this->uploads->find($slug, $id);
        if (!$file) {
            http_response_code(404);
            echo 'Not found';
            exit;
        }
        header('Content-Type: ' . $file['mime']);
        header('Cache-Control: public, max-age=86400');
        readfile($file['path']);
        exit;
    }

    private function patchSession(): void
    {
        Csrf::requireValid();
        $name = trim((string) (Http::body()['display_name'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9._ -]{2,24}$/', $name)) {
            Http::error('name', 'Nama tampilan 2–24 karakter');
        }
        SessionService::setDisplayName($name);
        Http::json(SessionService::payload());
    }

    private function createNote(): void
    {
        Csrf::requireValid();
        $body = Http::body();
        $slug = Slug::normalize((string) ($body['slug'] ?? ''));
        if (!Slug::valid($slug)) {
            Http::error('slug', 'Slug tidak valid');
        }
        if (is_file($this->notes->path($slug))) {
            Http::error('exists', 'Slug sudah dipakai', 409);
        }
        $note = $this->notes->ensure($slug);
        $format = ($body['format'] ?? 'md') === 'txt' ? 'txt' : 'md';
        $note = $this->notes->save($slug, function (array $n) use ($format) {
            $n['format'] = $format;
            return $n;
        });
        Http::json(['slug' => $note['slug']], 201);
    }

    private function getNote(string $slug): void
    {
        $slug = $this->requireSlug($slug);
        $note = $this->notes->ensure($slug);
        $this->maybePrune($note);
        Http::json($this->notes->publicNote($note), 200, ['ETag' => '"' . $note['rev'] . '"']);
    }

    private function putNote(string $slug): void
    {
        Csrf::requireValid();
        $this->rateLimitPuts();
        $slug = $this->requireSlug($slug);
        $body = Http::body();
        $baseRev = (int) ($body['base_rev'] ?? -1);
        $content = (string) ($body['content'] ?? '');
        if (strlen($content) > 1572864) {
            Http::error('size', 'Konten terlalu besar', 413);
        }
        $result = $this->notes->save($slug, function (array $n) use ($baseRev, $body, $content) {
            if ($baseRev !== (int) $n['rev']) {
                $n['_conflict'] = true;
                return $n;
            }
            $n['content'] = $content;
            if (isset($body['format'])) {
                $n['format'] = $body['format'] === 'txt' ? 'txt' : 'md';
            }
            if (isset($body['retention_ms'])) {
                $n['retention_ms'] = (int) $body['retention_ms'];
            }
            if (isset($body['encrypted'])) {
                $n['encrypted'] = (bool) $body['encrypted'];
            }
            if (isset($body['title'])) {
                $n['title'] = substr((string) $body['title'], 0, 120);
            }
            $n['rev'] = (int) $n['rev'] + 1;
            $n['updated_at'] = gmdate('c');
            $n['updated_by'] = SessionService::displayName();
            return $n;
        });
        if (!empty($result['_conflict'])) {
            unset($result['_conflict']);
            Http::json($this->notes->publicNote($result), 409);
        }
        Http::json(['rev' => $result['rev'], 'updated_at' => $result['updated_at']]);
    }

    private function persistNote(string $slug): void
    {
        Csrf::requireValid();
        $slug = $this->requireSlug($slug);
        $body = Http::body();
        $content = (string) ($body['content'] ?? '');
        if (strlen($content) > 1572864) {
            Http::error('size', 'Konten terlalu besar', 413);
        }
        $result = $this->notes->save($slug, function (array $n) use ($body, $content) {
            if (($n['content'] ?? '') !== $content) {
                $n['content'] = $content;
                $n['rev'] = (int) $n['rev'] + 1;
                $n['updated_at'] = gmdate('c');
                $n['updated_by'] = SessionService::displayName();
            }
            if (isset($body['format'])) {
                $n['format'] = $body['format'] === 'txt' ? 'txt' : 'md';
            }
            if (isset($body['retention_ms'])) {
                $n['retention_ms'] = (int) $body['retention_ms'];
            }
            if (isset($body['encrypted'])) {
                $n['encrypted'] = (bool) $body['encrypted'];
            }
            return $n;
        });
        Http::json(['rev' => $result['rev'], 'updated_at' => $result['updated_at'] ?? gmdate('c')]);
    }

    private function getSync(string $slug): void
    {
        $slug = $this->requireSlug($slug);
        $this->notes->ensure($slug);
        $since = (int) ($_GET['since'] ?? 0);
        Http::json($this->yjs->since($slug, $since));
    }

    private function postSync(string $slug): void
    {
        Csrf::requireValid();
        $this->rateLimitSync();
        $slug = $this->requireSlug($slug);
        $this->notes->ensure($slug);
        $body = Http::body();
        $update = (string) ($body['update'] ?? '');
        $client = (int) ($body['client'] ?? 0);
        $snapshot = !empty($body['snapshot']);
        try {
            $data = $this->yjs->append($slug, $update, $client, $snapshot);
        } catch (\InvalidArgumentException $e) {
            Http::error('sync', $e->getMessage());
        }
        $last = $data['updates'][count($data['updates']) - 1] ?? null;
        Http::json(['seq' => $data['seq'], 'update' => $last]);
    }

    private function presence(string $slug): void
    {
        Csrf::requireValid();
        $slug = $this->requireSlug($slug);
        $note = $this->notes->save($slug, function (array $n) {
            $n['presence'] = $this->notes->freshPresence($n['presence'] ?? []);
            $found = false;
            $sid = SessionService::id();
            foreach ($n['presence'] as &$p) {
                if (($p['session_id'] ?? '') === $sid) {
                    $p['display_name'] = SessionService::displayName();
                    $p['color'] = SessionService::color();
                    $p['last_seen'] = gmdate('c');
                    $found = true;
                }
            }
            unset($p);
            if (!$found) {
                $n['presence'][] = [
                    'session_id' => $sid,
                    'display_name' => SessionService::displayName(),
                    'color' => SessionService::color(),
                    'last_seen' => gmdate('c'),
                ];
            }
            return $n;
        });
        Http::json(['online' => count($this->notes->freshPresence($note['presence'] ?? []))]);
    }

    private function sse(string $slug): void
    {
        $slug = $this->requireSlug($slug);
        $this->notes->ensure($slug);
        session_write_close();
        ignore_user_abort(true);
        set_time_limit(0);
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');
        while (ob_get_level() > 0) {
            ob_end_flush();
        }
        $start = time();
        $lastSeq = (int) ($_GET['since'] ?? 0);
        $lastPing = 0;
        $lastOnline = -1;
        while (time() - $start < 55) {
            if (connection_aborted()) {
                break;
            }
            $pack = $this->yjs->since($slug, $lastSeq);
            foreach ($pack['updates'] as $row) {
                $lastSeq = (int) $row['seq'];
                echo "event: y\n";
                echo 'data: ' . json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n\n";
            }
            $note = $this->notes->get($slug, false);
            $online = count($note['presence'] ?? []);
            if ($online !== $lastOnline) {
                $lastOnline = $online;
                echo "event: presence\n";
                echo 'data: ' . json_encode(['online' => $online, 'users' => $this->notes->publicNote($note)['presence'] ?? []], JSON_UNESCAPED_UNICODE) . "\n\n";
            }
            if (time() - $lastPing >= 12) {
                $lastPing = time();
                echo "event: ping\ndata: {\"seq\":{$lastSeq}}\n\n";
            }
            flush();
            usleep(50000);
        }
        exit;
    }

    private function listHistory(string $slug): void
    {
        $slug = $this->requireSlug($slug);
        $note = $this->notes->ensure($slug);
        $this->history->prune($slug, (int) $note['retention_ms']);
        $items = [];
        foreach ($this->history->list($slug, 50) as $row) {
            $items[] = [
                'id' => $row['id'],
                'ts' => $row['ts'],
                'user' => $row['user'],
                'len' => $row['len'],
                'preview' => mb_substr((string) $row['content'], 0, 120),
            ];
        }
        Http::json(['items' => $items]);
    }

    private function addHistory(string $slug): void
    {
        Csrf::requireValid();
        $slug = $this->requireSlug($slug);
        $note = $this->notes->ensure($slug);
        $rec = $this->history->add($slug, (string) $note['content'], SessionService::displayName(), SessionService::id());
        Http::json(['ok' => true, 'id' => $rec['id'] ?? null]);
    }

    private function restoreHistory(string $slug, string $id): void
    {
        Csrf::requireValid();
        $slug = $this->requireSlug($slug);
        $rec = $this->history->get($slug, $id);
        if (!$rec) {
            Http::error('history', 'Snapshot tidak ditemukan', 404);
        }
        $result = $this->notes->save($slug, function (array $n) use ($rec) {
            $n['content'] = (string) $rec['content'];
            $n['rev'] = (int) $n['rev'] + 1;
            $n['updated_at'] = gmdate('c');
            $n['updated_by'] = SessionService::displayName();
            return $n;
        });
        Http::json(['rev' => $result['rev'], 'content' => (string) $rec['content']]);
    }

    private function clearHistory(string $slug): void
    {
        Csrf::requireValid();
        $slug = $this->requireSlug($slug);
        $n = $this->history->deleteAll($slug);
        Http::json(['deleted' => $n]);
    }

    private function upload(string $slug): void
    {
        Csrf::requireValid();
        $slug = $this->requireSlug($slug);
        $this->notes->ensure($slug);
        try {
            $info = $this->uploads->save($slug, $_FILES['file'] ?? [], $this->basePath);
        } catch (\InvalidArgumentException $e) {
            Http::error('upload', $e->getMessage());
        } catch (\Throwable $e) {
            Http::error('upload', $e->getMessage(), 500);
        }
        Http::json($info);
    }

    private function preview(): void
    {
        Csrf::requireValid();
        $body = Http::body();
        $content = (string) ($body['content'] ?? '');
        if (strlen($content) > 1572864) {
            Http::error('size', 'Konten terlalu besar', 413);
        }
        if (str_starts_with($content, 'PQC1:') || str_starts_with($content, 'AES1:')) {
            Http::json(['html' => null, 'encrypted' => true]);
        }
        $format = ($body['format'] ?? 'md') === 'txt' ? 'txt' : 'md';
        $html = $this->markdown->render($format, $content);
        Http::json(['html' => $html, 'encrypted' => false]);
    }

    private function maybePrune(array $note): void
    {
        $slug = (string) $note['slug'];
        $key = 'prune_' . $slug;
        $now = time();
        if (($_SESSION[$key] ?? 0) > $now - 300) {
            return;
        }
        $_SESSION[$key] = $now;
        $this->history->prune($slug, (int) ($note['retention_ms'] ?? 86400000));
    }

    private function rateLimitPuts(): void
    {
        $now = time();
        $hits = array_values(array_filter($_SESSION['put_hits'] ?? [], fn ($t) => $t > $now - 60));
        if (count($hits) >= 90) {
            Http::error('rate', 'Terlalu banyak penyimpanan', 429);
        }
        $hits[] = $now;
        $_SESSION['put_hits'] = $hits;
    }

    private function rateLimitSync(): void
    {
        $now = time();
        $hits = array_values(array_filter($_SESSION['sync_hits'] ?? [], fn ($t) => $t > $now - 60));
        if (count($hits) >= 400) {
            Http::error('rate', 'Terlalu banyak sync', 429);
        }
        $hits[] = $now;
        $_SESSION['sync_hits'] = $hits;
    }
}
