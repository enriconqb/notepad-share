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
        $r->add('GET', '/api/notes/(?P<slug>[a-z0-9-]+)/access', fn (string $slug) => $this->accessMeta($slug));
        $r->add('POST', '/api/notes/(?P<slug>[a-z0-9-]+)/unlock', fn (string $slug) => $this->unlockNote($slug));
        $r->add('POST', '/api/notes/(?P<slug>[a-z0-9-]+)/lock', fn (string $slug) => $this->toggleLock($slug));
        $r->add('GET', '/api/notes/(?P<slug>[a-z0-9-]+)', fn (string $slug) => $this->getNote($slug));
        $r->add('PUT', '/api/notes/(?P<slug>[a-z0-9-]+)', fn (string $slug) => $this->putNote($slug));
        $r->add('DELETE', '/api/notes/(?P<slug>[a-z0-9-]+)', fn (string $slug) => $this->deleteNote($slug));
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

    private function loadLiveNote(string $slug): ?array
    {
        $note = $this->notes->get($slug, false);
        if ($note === null) {
            return null;
        }
        if ($this->notes->isExpired($note)) {
            $this->destroyRoom($slug);
            return null;
        }
        return $note;
    }

    private function destroyRoom(string $slug): void
    {
        $this->history->deleteAll($slug);
        $this->yjs->delete($slug);
        $this->uploads->deleteSlug($slug);
        $this->notes->destroy($slug);
        unset($_SESSION['room_unlock'][$slug]);
    }

    private function requireRoomAccess(string $slug): array
    {
        $slug = $this->requireSlug($slug);
        $note = $this->loadLiveNote($slug);
        if ($note === null) {
            Http::error('not_found', 'Ruang tidak ditemukan', 404);
        }
        if (!$this->notes->canAccess($note)) {
            $code = $this->notes->hasPassword($note) ? 'locked' : 'forbidden';
            $msg = $code === 'locked' ? 'Ruang terkunci. Masukkan password.' : 'Ruang dikunci dan tidak dapat diakses.';
            Http::error($code, $msg, 403);
        }
        return $note;
    }

    private function landing(?string $prefillSlug = null): void
    {
        $base = htmlspecialchars($this->basePath, ENT_QUOTES, 'UTF-8');
        if ($prefillSlug === null) {
            $prefillSlug = Slug::normalize((string) ($_GET['slug'] ?? ''));
        }
        if ($prefillSlug !== '' && !Slug::valid($prefillSlug)) {
            $prefillSlug = '';
        }
        include $this->views . '/landing.php';
        exit;
    }

    private function editor(string $slug): void
    {
        $slug = $this->requireSlug($slug);
        $note = $this->loadLiveNote($slug);
        if ($note === null || !$this->notes->canAccess($note)) {
            $this->landing($slug);
            return;
        }
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
        $this->requireRoomAccess($slug);
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

    private function accessMeta(string $slug): void
    {
        $slug = $this->requireSlug($slug);
        $note = $this->loadLiveNote($slug);
        if ($note === null) {
            Http::json(['exists' => false, 'locked' => false, 'has_password' => false, 'is_owner' => false]);
        }
        Http::json($this->notes->accessMeta($note));
    }

    private function createNote(): void
    {
        Csrf::requireValid();
        $body = Http::body();
        $slug = Slug::normalize((string) ($body['slug'] ?? ''));
        if (!Slug::valid($slug)) {
            Http::error('slug', 'Slug tidak valid');
        }
        $existing = $this->loadLiveNote($slug);
        if ($existing !== null) {
            Http::error('exists', 'Slug sudah dipakai', 409);
        }
        $this->notes->destroy($slug);
        $format = ($body['format'] ?? 'md') === 'txt' ? 'txt' : 'md';
        $retention = NoteStore::normalizeRetention($body['retention_ms'] ?? 86400000);
        $note = $this->notes->ensure($slug);
        $note = $this->notes->save($slug, function (array $n) use ($format, $retention) {
            $n['format'] = $format;
            $n['retention_ms'] = $retention;
            $n['locked'] = false;
            $n['owner_session_id'] = SessionService::id();
            $n['password_hash'] = null;
            $n['access_gen'] = 1;
            $n['created_at'] = gmdate('c');
            return $n;
        });
        Http::json(['slug' => $note['slug']], 201);
    }

    private function unlockNote(string $slug): void
    {
        Csrf::requireValid();
        $this->rateLimitUnlock();
        $slug = $this->requireSlug($slug);
        $note = $this->loadLiveNote($slug);
        if ($note === null) {
            Http::error('not_found', 'Ruang tidak ditemukan', 404);
        }
        if ($this->notes->isOwner($note) || !$this->notes->isLocked($note)) {
            $this->notes->grantUnlock($slug, $note);
            Http::json(['ok' => true]);
        }
        if (!$this->notes->hasPassword($note)) {
            Http::error('forbidden', 'Ruang dikunci dan tidak dapat diakses.', 403);
        }
        $password = (string) (Http::body()['password'] ?? '');
        if (!password_verify($password, (string) $note['password_hash'])) {
            Http::error('password', 'Password salah', 403);
        }
        $this->notes->grantUnlock($slug, $note);
        Http::json(['ok' => true]);
    }

    private function toggleLock(string $slug): void
    {
        Csrf::requireValid();
        $note = $this->requireRoomAccess($slug);
        if (!$this->notes->isOwner($note)) {
            Http::error('forbidden', 'Hanya pembuat ruang yang dapat mengubah kunci', 403);
        }
        $body = Http::body();
        $locked = (bool) ($body['locked'] ?? true);
        $password = (string) ($body['password'] ?? '');
        if ($locked && $password === '') {
            Http::error('password', 'Password wajib diisi untuk mengunci ruang');
        }
        $result = $this->notes->save($slug, function (array $n) use ($locked, $password) {
            $n['locked'] = $locked;
            $n['access_gen'] = (int) ($n['access_gen'] ?? 1) + 1;
            if ($locked) {
                $n['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            } else {
                $n['password_hash'] = null;
            }
            return $n;
        });
        Http::json($this->notes->publicNote($result));
    }

    private function deleteNote(string $slug): void
    {
        Csrf::requireValid();
        $note = $this->requireRoomAccess($slug);
        if (!$this->notes->isOwner($note)) {
            Http::error('forbidden', 'Hanya pembuat ruang yang dapat menghapus', 403);
        }
        $this->destroyRoom($slug);
        Http::json(['ok' => true, 'deleted' => true]);
    }

    private function getNote(string $slug): void
    {
        $note = $this->requireRoomAccess($slug);
        $this->maybePrune($note);
        $note = $this->loadLiveNote($slug);
        if ($note === null) {
            Http::error('not_found', 'Ruang tidak ditemukan', 404);
        }
        Http::json($this->notes->publicNote($note), 200, ['ETag' => '"' . $note['rev'] . '"']);
    }

    private function putNote(string $slug): void
    {
        Csrf::requireValid();
        $this->rateLimitPuts();
        $this->requireRoomAccess($slug);
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
                $n['retention_ms'] = NoteStore::normalizeRetention($body['retention_ms']);
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
        $this->requireRoomAccess($slug);
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
                $n['retention_ms'] = NoteStore::normalizeRetention($body['retention_ms']);
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
        $this->requireRoomAccess($slug);
        $since = (int) ($_GET['since'] ?? 0);
        Http::json($this->yjs->since($slug, $since));
    }

    private function postSync(string $slug): void
    {
        Csrf::requireValid();
        $this->rateLimitSync();
        $this->requireRoomAccess($slug);
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
        $this->requireRoomAccess($slug);
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
        $this->requireRoomAccess($slug);
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
            if ($note === null) {
                break;
            }
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
        $note = $this->requireRoomAccess($slug);
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
        $note = $this->requireRoomAccess($slug);
        $rec = $this->history->add($slug, (string) $note['content'], SessionService::displayName(), SessionService::id());
        Http::json(['ok' => true, 'id' => $rec['id'] ?? null]);
    }

    private function restoreHistory(string $slug, string $id): void
    {
        Csrf::requireValid();
        $this->requireRoomAccess($slug);
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
        $this->requireRoomAccess($slug);
        $n = $this->history->deleteAll($slug);
        Http::json(['deleted' => $n]);
    }

    private function upload(string $slug): void
    {
        Csrf::requireValid();
        $this->requireRoomAccess($slug);
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
        if ($this->notes->isExpired($note)) {
            $this->destroyRoom($slug);
            return;
        }
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

    private function rateLimitUnlock(): void
    {
        $now = time();
        $hits = array_values(array_filter($_SESSION['unlock_hits'] ?? [], fn ($t) => $t > $now - 60));
        if (count($hits) >= 15) {
            Http::error('rate', 'Terlalu banyak percobaan password', 429);
        }
        $hits[] = $now;
        $_SESSION['unlock_hits'] = $hits;
    }
}
