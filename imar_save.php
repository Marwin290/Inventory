<?php
// IMAR Asset Inventory — server-side save endpoint
// Upload this file to the SAME FOLDER as this HTML file on your web host.
// It receives JSON POSTed by the app and writes it to imar_db.json,
// so every save in the browser is persisted on the server automatically.

header('Content-Type: application/json');

// Only accept POST
if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
if($raw === false || $raw === ''){
    http_response_code(400);
    echo json_encode(['error' => 'Empty body']);
    exit;
}

// Validate it is JSON before writing (avoid corrupting the file on bad input)
$decoded = json_decode($raw);
if($decoded === null && json_last_error() !== JSON_ERROR_NONE){
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON']);
    exit;
}

$target = __DIR__ . '/imar_db.json';

// Keep one rolling backup of the previous version before overwriting
if(file_exists($target)){
    @copy($target, __DIR__ . '/imar_db.backup.json');
}

// Stamp a server-side sync timestamp (ms) into the saved file. The client polls
// for this value to detect when ANOTHER device has saved newer data, so every
// open tab/device can auto-refresh without a manual page reload.
$syncTs = (int) round(microtime(true) * 1000);
$decoded->_syncTs = $syncTs;
$out = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

$bytes = @file_put_contents($target, $out, LOCK_EX);
if($bytes === false){
    http_response_code(500);
    echo json_encode(['error' => 'Could not write imar_db.json — check folder write permissions (chmod 755 or 775)']);
    exit;
}

echo json_encode(['ok' => true, 'bytes' => $bytes, 'syncTs' => $syncTs]);
