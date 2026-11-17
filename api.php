<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$dir = __DIR__ . '/save';
if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $out = [];
    $files = glob($dir . '/*.png') ?: [];
    usort($files, function ($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    foreach (array_slice($files, 0, 24) as $f) {
        $out[] = [
            'url' => './save/' . basename($f),
            'time' => date('m-d H:i', filemtime($f)),
        ];
    }
    echo json_encode(['ok' => true, 'items' => $out], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'msg' => 'method']);
    exit;
}

$raw = file_get_contents('php://input');
if (strlen($raw) > 12 * 1024 * 1024) {
    http_response_code(413);
    echo json_encode(['ok' => false, 'msg' => '图片太大']);
    exit;
}

$body = json_decode($raw, true);
$data = is_array($body) && isset($body['image']) ? (string)$body['image'] : '';

if (!preg_match('#^data:image/png;base64,#', $data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => '格式不对']);
    exit;
}

$bin = base64_decode(substr($data, strpos($data, ',') + 1), true);
if ($bin === false || strlen($bin) < 200) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'msg' => '数据损坏']);
    exit;
}

$name = date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.png';
$path = $dir . '/' . $name;

if (file_put_contents($path, $bin) === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => '写入失败']);
    exit;
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'imxy.cn';
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

echo json_encode([
    'ok' => true,
    'url' => $scheme . '://' . $host . $base . '/save/' . $name,
    'size' => strlen($bin),
], JSON_UNESCAPED_UNICODE);
