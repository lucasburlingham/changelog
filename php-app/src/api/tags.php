<?php
header('Content-Type: application/json; charset=utf-8');

// read known tags/hex from tags.csv (if present)
// prefer runtime data directory, fall back to repository `src/data/tags.csv`
$csvFile = __DIR__ . '/../../data/tags.csv';
if (!is_file($csvFile)) {
    $csvFile = __DIR__ . '/../data/tags.csv';
}
$known = [];
if (is_file($csvFile) && ($h = fopen($csvFile, 'r')) !== false) {
    while (($row = fgetcsv($h)) !== false) {
        if (!isset($row[0])) continue;
        $tag = trim($row[0]);
        $hex = isset($row[1]) ? trim($row[1]) : '';
        if ($tag === '') continue;
        $known[$tag] = $hex;
    }
    fclose($h);
}

// collect usage counts from the SQLite entries DB (if available)
$counts = [];
$dbFile = __DIR__ . '/../data/changelog.db';
if (is_file($dbFile) && extension_loaded('pdo') && in_array('sqlite', PDO::getAvailableDrivers())) {
    try {
        $pdo = new PDO('sqlite:' . $dbFile);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->query('SELECT tags FROM entries');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $raw = isset($row['tags']) ? trim($row['tags'], ',') : '';
            if ($raw === '') continue;
            $parts = array_values(array_filter(array_map('trim', explode(',', $raw))));
            foreach ($parts as $t) {
                if ($t === '') continue;
                if (!isset($counts[$t])) $counts[$t] = 0;
                $counts[$t]++;
            }
        }
    } catch (Exception $e) {
        // ignore DB errors and fall back to CSV-only response
    }
}

// merge known tags and counted tags into a single array
$out = [];
$seen = [];
foreach ($known as $tag => $hex) {
    $out[] = ['tag' => $tag, 'hex' => $hex, 'count' => isset($counts[$tag]) ? (int)$counts[$tag] : 0];
    $seen[$tag] = true;
}
// include tags that appear in entries but not in CSV
foreach ($counts as $tag => $c) {
    if (isset($seen[$tag])) continue;
    $out[] = ['tag' => $tag, 'hex' => '', 'count' => (int)$c];
}

// sort by count desc, then tag asc
usort($out, function($a, $b){
    if (($b['count'] ?? 0) !== ($a['count'] ?? 0)) return ($b['count'] ?? 0) - ($a['count'] ?? 0);
    return strcasecmp($a['tag'], $b['tag']);
});

echo json_encode($out);
