<?php
$cfg = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
$author = $cfg['author'] ?? 'LSMa';
$contribute = $cfg['contribute'] ?? '#';
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

$items = [];
$dir = __DIR__ . '/save';
if (is_dir($dir)) {
    $files = glob($dir . '/*.png') ?: [];
    usort($files, function ($a, $b) { return filemtime($b) - filemtime($a); });
    foreach (array_slice($files, 0, 60) as $f) {
        $items[] = [
            'url' => './save/' . basename($f),
            'time' => date('m-d H:i', filemtime($f)),
        ];
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>大家做的脸蛋 · QQ捏脸</title>
<meta name="description" content="QQ捏脸的作品墙：看看别人把哪些图片做成了会弹的脸，点一张就能直接拿去涂抹。">
<meta name="keywords" content="QQ捏脸,作品墙,弹脸,WebGL,LSMa">
<meta name="author" content="<?= htmlspecialchars($author, ENT_QUOTES) ?>">
<meta name="theme-color" content="#f56d9b">
<style>
:root { --ink:#3a2b2f; --muted:#a08a92; --line:#ffd7e2; --pink:#ff8fb1; --pink-deep:#f56d9b; }
* { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
body {
    margin: 0;
    background: #fff;
    color: var(--ink);
    font: 15px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
}
header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 20px 10px;
    max-width: 960px;
    margin: 0 auto;
}
header h1 { margin: 0; font-size: 16px; font-weight: 600; }
header a {
    font-size: 13px;
    color: var(--pink-deep);
    text-decoration: none;
    padding: 7px 14px;
    border: 1px solid var(--line);
    border-radius: 9px;
}
header a:hover { border-color: var(--pink); }
main { max-width: 960px; margin: 0 auto; padding: 6px 16px 40px; }
.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 10px;
}
figure {
    margin: 0;
    border: 1px solid #f0f0f0;
    border-radius: 10px;
    overflow: hidden;
    background: #fff;
    cursor: pointer;
    position: relative;
    aspect-ratio: 1 / 1;
    transition: border-color .15s ease, box-shadow .15s ease;
}
figure:hover { border-color: var(--pink); box-shadow: 0 8px 20px rgba(245,109,155,.16); }
figure img { display: block; width: 100%; height: 100%; object-fit: cover; }
figure figcaption {
    position: absolute;
    left: 0; right: 0; bottom: 0;
    padding: 4px 8px;
    font-size: 11px;
    color: #fff;
    background: linear-gradient(transparent, rgba(58,43,47,.55));
}
.empty {
    padding: 60px 20px;
    text-align: center;
    color: var(--muted);
    font-size: 14px;
}
.empty a { color: var(--pink-deep); }
</style>
</head>
<body>
<header>
    <h1>大家做的脸蛋</h1>
    <a href="index.php">去做一个</a>
</header>
<main>
<?php if (!$items): ?>
    <div class="empty">
        还没有人分享。<a href="index.php">去做第一个</a>
    </div>
<?php else: ?>
    <div class="grid">
        <?php foreach ($items as $it): ?>
        <figure data-url="<?= htmlspecialchars($it['url'], ENT_QUOTES) ?>">
            <img src="<?= htmlspecialchars($it['url'], ENT_QUOTES) ?>" alt="" loading="lazy">
            <figcaption><?= htmlspecialchars($it['time'], ENT_QUOTES) ?></figcaption>
        </figure>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
</main>
<script>
document.querySelectorAll('figure[data-url]').forEach(function (f) {
    f.addEventListener('click', function () {
        location.href = 'index.php?img=' + encodeURIComponent(f.getAttribute('data-url'));
    });
});
</script>
</body>
</html>
