<?php
header('Content-Type: application/json; charset=utf-8');
$file = __DIR__ . '/../../data/tags.csv';
if (!is_file($file)) { echo json_encode([]); exit; }

$out = [];
if (($h = fopen($file, 'r')) !== false) {
    while (($row = fgetcsv($h)) !== false) {
        if (!isset($row[0])) continue;
        $tag = trim($row[0]);
        $hex = isset($row[1]) ? trim($row[1]) : '';
        if ($tag === '') continue;
        $out[] = ['tag' => $tag, 'hex' => $hex];
    }
    fclose($h);
}

echo json_encode($out);
