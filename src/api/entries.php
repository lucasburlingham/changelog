<?php

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
    $pdo = new PDO('sqlite:' . $dbFile);
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
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to open or initialize database',
        'detail' => $e->getMessage(),
        'db_path' => $dbFile,
    ]);
    exit;
}

function parse_time($v){
    if ($v === null || $v === '') return null;
    if (is_numeric($v)) return (int)$v;
    $ts = strtotime($v);
    if ($ts === false) return null;
    return $ts * 1000;
}

function wants_html_fragment(){
    return (($_GET['format'] ?? '') === 'html')
        || (isset($_SERVER['HTTP_HX_REQUEST']) && strtolower((string)$_SERVER['HTTP_HX_REQUEST']) === 'true');
}

function esc($value){
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function row_to_entry($row){
    $row['tags'] = array_values(array_filter(array_map('trim', explode(',', trim((string)($row['tags'] ?? ''), ',')))));
    $row['timestamp'] = (int)($row['timestamp'] ?? 0);
    return $row;
}

function format_datetime_local_value($timestampMs){
    if (!$timestampMs) return '';
    $seconds = (int) floor(((int)$timestampMs) / 1000);
    return date('Y-m-d\\TH:i', $seconds);
}

function render_entry_view_fragment($entry){
    $id = esc($entry['id'] ?? '');
    $title = esc($entry['title'] ?? '');
    $submitter = trim((string)($entry['submitter'] ?? ''));
    $submitterOut = $submitter === '' ? '—' : esc($submitter);
    $description = (string)($entry['description'] ?? '');
    $timestamp = (int)($entry['timestamp'] ?? 0);
    $metaDate = $timestamp > 0 ? date('Y-m-d H:i:s', (int) floor($timestamp / 1000)) : '';

    $tags = '';
    foreach (($entry['tags'] ?? []) as $tagName) {
        $tags .= '<span class="tag">' . esc($tagName) . '</span>';
    }

    return
        '<div class="entry is-editable" id="entry-' . $id . '"'
        . ' hx-get="/api/entries.php?id=' . rawurlencode((string)$entry['id']) . '&format=html&mode=edit"'
        . ' hx-trigger="dblclick"'
        . ' hx-target="this"'
        . ' hx-swap="outerHTML"'
        . ' title="Double-click to edit date and body">'
        . '<div><strong>' . $title . '</strong></div>'
        . '<div class="meta">' . esc($metaDate) . ' — ' . $submitterOut . '</div>'
        . '<div class="description">' . $description . '</div>'
        . '<div class="tags">' . $tags . '</div>'
        . '</div>';
}

function render_entry_edit_fragment($entry){
    $id = esc($entry['id'] ?? '');
    $title = esc($entry['title'] ?? '');
    $timestampLocal = esc(format_datetime_local_value($entry['timestamp'] ?? 0));
    $description = esc((string)($entry['description'] ?? ''));
    $editorId = 'entryEditDescription-' . preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($entry['id'] ?? ''));

    return
        '<form class="entry entry-edit-form" id="entry-' . $id . '"'
        . ' hx-put="/api/entries.php?id=' . rawurlencode((string)$entry['id']) . '&format=html"'
        . ' hx-target="this"'
        . ' hx-swap="outerHTML">'
        . '<div><strong>' . $title . '</strong></div>'
        . '<label>Date <input type="datetime-local" name="timestamp" value="' . $timestampLocal . '" required></label>'
        . '<label>Body <textarea id="' . esc($editorId) . '" class="entry-edit-description" name="description" required>' . $description . '</textarea></label>'
        . '<div class="controls">'
        . '<button type="submit">Save</button>'
        . '<button type="button" class="secondary" hx-get="/api/entries.php?id=' . rawurlencode((string)$entry['id']) . '&format=html&mode=view" hx-target="closest form" hx-swap="outerHTML">Cancel</button>'
        . '</div>'
        . '</form>';
}

// POST -> create entry
if ($method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
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

// PUT -> update an existing entry (date/body)
if ($method === 'PUT') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $data = [];
        parse_str($raw, $data);
    }

    $id = trim((string)($_GET['id'] ?? ($data['id'] ?? '')));
    if ($id === '') {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'id is required']);
        exit;
    }

    $description = (string)($data['description'] ?? '');
    if (trim($description) === '') {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'description is required']);
        exit;
    }

    $timestamp = parse_time($data['timestamp'] ?? null);
    if ($timestamp === null) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'valid timestamp is required']);
        exit;
    }

    $update = $pdo->prepare('UPDATE entries SET description = :description, timestamp = :timestamp WHERE id = :id');
    $update->execute([
        ':description' => $description,
        ':timestamp' => $timestamp,
        ':id' => $id,
    ]);

    $fetch = $pdo->prepare('SELECT id,title,description,submitter,tags,timestamp FROM entries WHERE id = :id');
    $fetch->execute([':id' => $id]);
    $row = $fetch->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'entry not found']);
        exit;
    }

    $entry = row_to_entry($row);

    if (wants_html_fragment()) {
        header('Content-Type: text/html; charset=utf-8');
        echo render_entry_view_fragment($entry);
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($entry);
    exit;
}

// GET -> list/filter/sort entries
if ($method === 'GET') {
    $q = $_GET;

    if (!empty($q['id'])) {
        $stmt = $pdo->prepare('SELECT id,title,description,submitter,tags,timestamp FROM entries WHERE id = :id');
        $stmt->execute([':id' => (string)$q['id']]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'entry not found']);
            exit;
        }

        $entry = row_to_entry($row);
        if (wants_html_fragment()) {
            $mode = strtolower((string)($q['mode'] ?? 'view'));
            header('Content-Type: text/html; charset=utf-8');
            echo $mode === 'edit' ? render_entry_edit_fragment($entry) : render_entry_view_fragment($entry);
            exit;
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($entry);
        exit;
    }

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
    $rows = array_map('row_to_entry', $rows);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($rows);
    exit;
}

http_response_code(405);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['error'=>'method not allowed']);
