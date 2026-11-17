<?php
/* QQ捏脸 · 源码打包下载 */
$root = __DIR__;
$files = [
    'index.php' => 'index.php',
    'app.js' => 'app.js',
    'api.php' => 'api.php',
    'download.php' => 'download.php',
    'gallery.php' => 'gallery.php',
    'config.php' => 'config.php',
    'README.md' => 'README.md',
];


$zipPath = sys_get_temp_dir() . '/qq-nielian-' . bin2hex(random_bytes(4)) . '.zip';
$zip = new ZipArchive();

if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(500);
    echo '无法创建压缩包：服务器需要启用 ZipArchive 扩展';
    exit;
}

foreach ($files as $src => $name) {
    $full = $root . '/' . $src;
    if (is_file($full)) {
        $zip->addFile($full, 'qq-nielian/' . $name);
    }
}


$zip->addEmptyDir('qq-nielian/save');
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="qq-nielian-source.zip"');
header('Content-Length: ' . filesize($zipPath));
header('Cache-Control: no-store');
readfile($zipPath);
@unlink($zipPath);
