<?php
// tags.php — supports GET, POST, PUT, DELETE to manage php-app/data/tags.csv
header('Content-Type: application/json; charset=utf-8');
$file = __DIR__ . '/../../data/tags.csv';
if (!is_dir(dirname($file))) mkdir(dirname($file), 0777, true);
if (!is_file($file)) touch($file);

function load_tags($file){
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
    return $out;
}

function save_tags($file, $rows){
    $tmp = $file . '.tmp';
    if (($h = fopen($tmp, 'w')) === false) return false;
    foreach ($rows as $r){
        fputcsv($h, [$r['tag'], $r['hex']]);
    }
    fclose($h);
    return rename($tmp, $file);
}

function sanitize_hex($v){
    $v = preg_replace('/[^0-9a-fA-F]/', '', (string)$v);
    $v = strtolower($v);
    if ($v === '') return '';
    if (strlen($v) === 3 || strlen($v) === 6) return $v;
    // if length mismatched, truncate/pad to 6
    return str_pad(substr($v, 0, 6), 6, '0');
}

$method = $_SERVER['REQUEST_METHOD'];
$tags = load_tags($file);

if ($method === 'GET'){
    echo json_encode($tags);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];

if ($method === 'POST'){
    // create
    $tag = trim($body['tag'] ?? '');
    $hex = sanitize_hex($body['hex'] ?? '');
    if ($tag === ''){ http_response_code(400); echo json_encode(['error'=>'tag is required']); exit; }
    // check exists (case-insensitive)
    foreach ($tags as $t) if (strcasecmp($t['tag'], $tag) === 0){ http_response_code(409); echo json_encode(['error'=>'tag already exists']); exit; }
    $tags[] = ['tag'=>$tag,'hex'=>$hex];
    if (!save_tags($file, $tags)) { http_response_code(500); echo json_encode(['error'=>'failed to save tags']); exit; }
    http_response_code(201); echo json_encode(['tag'=>$tag,'hex'=>$hex]); exit;
}

if ($method === 'PUT'){
    // update — prefer body.oldTag to locate, otherwise use body.tag
    $old = isset($body['oldTag']) ? trim($body['oldTag']) : null;
    $tag = trim($body['tag'] ?? '');
    $hex = sanitize_hex($body['hex'] ?? '');
    if ($old === null && $tag === ''){ http_response_code(400); echo json_encode(['error'=>'oldTag or tag required']); exit; }
    $found = false;
    foreach ($tags as $i => $t){
        if (($old !== null && strcasecmp($t['tag'],$old)===0) || ($old===null && strcasecmp($t['tag'],$tag)===0)){
            $tags[$i] = ['tag'=>($tag ?: $t['tag']),'hex'=>($hex !== '' ? $hex : $t['hex'])];
            $found = true; break;
        }
    }
    if (!$found){ http_response_code(404); echo json_encode(['error'=>'tag not found']); exit; }
    if (!save_tags($file, $tags)) { http_response_code(500); echo json_encode(['error'=>'failed to save tags']); exit; }
    echo json_encode(['tag'=>$tags[$i]['tag'],'hex'=>$tags[$i]['hex']]); exit;
}

if ($method === 'DELETE'){
    $q = $_GET;
    $todel = isset($q['tag']) ? trim($q['tag']) : null;
    if (!$todel){ http_response_code(400); echo json_encode(['error'=>'tag query param required']); exit; }
    $new = [];
    $found = false;
    foreach ($tags as $t){ if (strcasecmp($t['tag'],$todel)===0){ $found = true; continue; } $new[] = $t; }
    if (!$found){ http_response_code(404); echo json_encode(['error'=>'tag not found']); exit; }
    if (!save_tags($file, $new)) { http_response_code(500); echo json_encode(['error'=>'failed to save tags']); exit; }
    echo json_encode(['deleted'=>$todel]); exit;
}

http_response_code(405);
echo json_encode(['error'=>'method not allowed']);
