<?php
header('Content-Type: application/json; charset=utf-8');

// read known submitters from submitters.csv (if present)
$csvFile = __DIR__ . '/../data/submitters.csv';
if (!is_file($csvFile)) {
    $csvFile = __DIR__ . '/../../data/submitters.csv';
}
$known = [];
if (is_file($csvFile) && ($h = fopen($csvFile, 'r')) !== false) {
    while (($row = fgetcsv($h)) !== false) {
        if (!isset($row[0])) continue;
        $submitter = trim($row[0]);
        if ($submitter === '') continue;
        $known[$submitter] = true;
    }
    fclose($h);
}

// collect usage counts from the SQLite entries DB (if available)
$counts = [];
$dbFile = __DIR__ . '/../data/changelog.db';
if (is_file($dbFile) && extension_loaded('pdo') && in_array('sqlite', PDO::getAvailableDrivers())) {
    try {
        $pdo = new PDO('sqlite:' . $dbFile, SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->query('SELECT submitter FROM entries WHERE submitter IS NOT NULL AND submitter != ""');
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $submitter = trim($row['submitter'] ?? '');
            if ($submitter === '') continue;
            if (!isset($counts[$submitter])) $counts[$submitter] = 0;
            $counts[$submitter]++;
        }
    } catch (Exception $e) {
        // ignore DB errors and fall back to CSV-only response
    }
}

// build output array with known and counted submitters
$out = [];
$seen = [];
foreach ($known as $submitter => $v) {
    $out[] = ['submitter' => $submitter, 'count' => isset($counts[$submitter]) ? (int)$counts[$submitter] : 0];
    $seen[$submitter] = true;
}
// include submitters that appear in entries but not in CSV
foreach ($counts as $submitter => $c) {
    if (isset($seen[$submitter])) continue;
    $out[] = ['submitter' => $submitter, 'count' => (int)$c];
}

// sort by count desc, then submitter asc
usort($out, function($a, $b){
    if (($b['count'] ?? 0) !== ($a['count'] ?? 0)) return ($b['count'] ?? 0) - ($a['count'] ?? 0);
    return strcasecmp($a['submitter'], $b['submitter']);
});

echo json_encode($out);
