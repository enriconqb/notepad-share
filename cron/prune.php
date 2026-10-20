<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use App\HistoryStore;
use App\JsonFile;
use App\NoteStore;
use App\UploadStore;
use App\YjsStore;

$data = $root . '/data';
$notes = new NoteStore($data);
$history = new HistoryStore($data);
$yjs = new YjsStore($data);
$uploads = new UploadStore($data);

$prunedHistory = 0;
$expiredRooms = 0;
foreach (glob($data . '/notes/*.json') ?: [] as $file) {
    $note = JsonFile::read($file);
    if (!is_array($note)) {
        continue;
    }
    $slug = (string) ($note['slug'] ?? '');
    if ($slug === '') {
        continue;
    }
    if ($notes->isExpired($note)) {
        $history->deleteAll($slug);
        $yjs->delete($slug);
        $uploads->deleteSlug($slug);
        $notes->destroy($slug);
        $expiredRooms++;
        continue;
    }
    $retention = (int) ($note['retention_ms'] ?? 86400000);
    $prunedHistory += $history->prune($slug, $retention);
    $uploads->pruneExpired($slug, $retention);
}

echo "Pruned {$prunedHistory} history files, expired {$expiredRooms} rooms\n";
