<?php
header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method not allowed']);
    exit;
}

if (empty($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['error' => 'file is required']);
    exit;
}

$file = $_FILES['file'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'upload failed with error code ' . (int) ($file['error'] ?? -1)]);
    exit;
}

if (!is_uploaded_file($file['tmp_name'])) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid upload source']);
    exit;
}

$maxBytes = 10 * 1024 * 1024;
if (($file['size'] ?? 0) > $maxBytes) {
    http_response_code(400);
    echo json_encode(['error' => 'image must be 10 MB or smaller']);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']) ?: '';
$allowedTypes = [
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
];

if (!isset($allowedTypes[$mime])) {
    http_response_code(400);
    echo json_encode(['error' => 'unsupported image type']);
    exit;
}

$imageSize = @getimagesize($file['tmp_name']);
if ($imageSize === false) {
    http_response_code(400);
    echo json_encode(['error' => 'uploaded file is not a valid image']);
    exit;
}

$uploadDir = dirname(__DIR__) . '/data/uploads';
if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true)) {
    http_response_code(500);
    echo json_encode(['error' => 'unable to create upload directory']);
    exit;
}

if (!is_writable($uploadDir)) {
    http_response_code(500);
    echo json_encode(['error' => 'upload directory is not writable']);
    exit;
}

$filename = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mime];
$targetPath = $uploadDir . '/' . $filename;
if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'unable to save uploaded image']);
    exit;
}

$location = '/data/uploads/' . $filename;

http_response_code(201);
echo json_encode([
    'location' => $location,
    'width' => $imageSize[0],
    'height' => $imageSize[1],
]);