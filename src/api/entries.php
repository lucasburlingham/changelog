<?php
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'];
$dbFile = __DIR__ . '/../data/changelog.db';
// ensure parent directory exists (create if necessary) and is writable
$dir = dirname($dbFile);
if (!is_dir($dir)) {
    @mkdir($dir, 0777, true);
}
if (!is_dir($dir) || !is_writable($dir)) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to create or write to data directory: ' . $dir]);
    exit;
}

// Validate PDO / pdo_sqlite availability so we return JSON errors instead of an HTML crash
if (!extension_loaded('pdo')) {
    http_response_code(500);
    echo json_encode(['error' => 'PDO extension is not available on this PHP installation']);
    exit;
}
if (!in_array('sqlite', PDO::getAvailableDrivers())) {
    http_response_code(500);
    echo json_encode(['error' => 'PDO SQLite driver not available (pdo_sqlite is not enabled)']);
    exit;
}

try {
    $pdo = new PDO('sqlite:' . $dbFile, SQLITE3_OPEN_CREATE | SQLITE3_OPEN_READWRITE);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS entries (
          id TEXT PRIMARY KEY,
          title TEXT,
          description TEXT,
          submitter TEXT,
          tags TEXT,
          timestamp INTEGER
        )"
    );
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to open or initialize database: ' . $e->getMessage()]);
    exit;
}

function parse_time($v){
    if ($v === null || $v === '') return null;
    if (is_numeric($v)) return (int)$v;
    $ts = strtotime($v);
    if ($ts === false) return null;
    return $ts * 1000;
}

// POST -> create entry
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) $data = $_POST;

    $title = trim($data['title'] ?? '');
    if ($title === '') { http_response_code(400); echo json_encode(['error'=>'title is required']); exit; }

    $description = $data['description'] ?? '';
    $submitter = $data['submitter'] ?? '';

    $tags = [];
    if (isset($data['tags'])) {
        if (is_array($data['tags'])) $tags = $data['tags'];
        else $tags = preg_split('/\s*,\s*/', $data['tags']);
    }
    $tags = array_values(array_filter(array_map('trim', $tags)));
    $tags_csv = count($tags) ? (',' . implode(',', $tags) . ',') : '';

    $id = bin2hex(random_bytes(16));
    $timestamp = isset($data['timestamp']) ? parse_time($data['timestamp']) : round(microtime(true) * 1000);

    $stmt = $pdo->prepare('INSERT INTO entries (id,title,description,submitter,tags,timestamp) VALUES (:id,:title,:description,:submitter,:tags,:timestamp)');
    $stmt->execute([
        ':id' => $id,
        ':title' => $title,
        ':description' => $description,
        ':submitter' => $submitter,
        ':tags' => $tags_csv,
        ':timestamp' => $timestamp
    ]);

    http_response_code(201);
    echo json_encode([
        'id'=>$id,'title'=>$title,'description'=>$description,'submitter'=>$submitter,'tags'=>$tags,'timestamp'=>$timestamp
    ]);
    exit;
}

// GET -> list/filter/sort entries
if ($method === 'GET') {
    $q = $_GET;

    $sql = 'SELECT id,title,description,submitter,tags,timestamp FROM entries WHERE 1=1';
    $binds = [];

    if (!empty($q['submitter'])){
        $sql .= ' AND submitter LIKE :submitter';
        $binds[':submitter'] = '%' . $q['submitter'] . '%';
    }

    if (!empty($q['from'])){
        $from = parse_time($q['from']);
        if($from !== null){ $sql .= ' AND timestamp >= :from'; $binds[':from'] = $from; }
    }
    if (!empty($q['to'])){
        $to = parse_time($q['to']);
        if($to !== null){ $sql .= ' AND timestamp <= :to'; $binds[':to'] = $to; }
    }

    if (!empty($q['tags'])){
        $tags = preg_split('/\s*,\s*/', $q['tags']);
        $i = 0;
        foreach ($tags as $tag){
            $tag = trim($tag);
            if ($tag === '') continue;
            $sql .= " AND tags LIKE :tag$i";
            $binds[":tag$i"] = '%,' . $tag . ',%';
            $i++;
        }
    }

    $allowed = ['timestamp','submitter','tags','title'];
    // normalize/validate requested sort column — default to 'timestamp' when missing/invalid
    $sort = $q['sort'] ?? 'timestamp';
    if (!in_array($sort, $allowed, true)) {
        $sort = 'timestamp';
    }
    $order = (strtolower($q['order'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';

    $sql .= " ORDER BY $sort $order";

    $limit = isset($q['limit']) ? (int)$q['limit'] : 0;
    $offset = isset($q['offset']) ? (int)$q['offset'] : 0;
    if ($limit > 0) {
        if ($limit > 200) {
            $limit = 200;
        }
        if ($offset < 0) {
            $offset = 0;
        }
        $sql .= ' LIMIT :limit OFFSET :offset';
    }

    $stmt = $pdo->prepare($sql);
    foreach ($binds as $k => $v) $stmt->bindValue($k, $v);
    if ($limit > 0) {
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    }
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // convert tags back to array
    $rows = array_map(function($r){
        $r['tags'] = array_values(array_filter(array_map('trim', explode(',', trim($r['tags'], ',')))));
        return $r;
    }, $rows);

    echo json_encode($rows);
    exit;
}

http_response_code(405);
echo json_encode(['error'=>'method not allowed']);
