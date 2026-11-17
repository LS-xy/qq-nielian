<?php
/* QQ捏脸 · 源码打包下载 */
$root = __DIR__;
$files = [
    'index.php' => 'index.php',
    'app.js' => 'app.js',
    'api.php' => 'api.php',
    'download.php' => 'download.php',
    'README.md' => 'README.md',
];

$extraReadme = <<<'TXT'
# QQ捏脸

一个把图片做成 Q 弹脸蛋的网页小游戏：导入图片 → 涂抹想变软的地方 → 拖拽玩耍。

## 文件

- `index.php` 页面与样式
- `app.js`    WebGL 网格变形、弹簧物理、涂抹与笔刷
- `api.php`   分享图保存与画廊列表（需要可写 `save/` 目录）
- `save/`     分享图存放目录

## 部署

1. 上传全部文件到任意 PHP 5.6+ / 7.x / 8.x 空间
2. 确保 `save/` 目录可写（没有的话程序会自动创建）
3. 访问 `index.php` 即可

## 玩法

1. 选择/拖入/粘贴一张图片
2. 涂抹想变软的区域，可切换橡皮擦与笔刷粗细
3. 拖动脸蛋，松手回弹；可保存分享或下载图片

## 注意

- 需要浏览器支持 WebGL
- 分享功能依赖 `api.php` 与 `save/` 的写权限，纯静态托管时请自行去掉分享按钮
TXT;

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

$zip->addFromString('qq-nielian/README.md', $extraReadme);
$zip->addEmptyDir('qq-nielian/save');
$zip->close();

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="qq-nielian-source.zip"');
header('Content-Length: ' . filesize($zipPath));
header('Cache-Control: no-store');
readfile($zipPath);
@unlink($zipPath);
