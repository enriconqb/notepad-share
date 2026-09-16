<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use App\HistoryStore;
use App\NoteStore;

$data = $root . '/data';
$n = (new HistoryStore($data))->pruneAll(new NoteStore($data));
echo "Pruned {$n} history files\n";
