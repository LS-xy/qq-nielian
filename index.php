<?php
$cfg = is_file(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [];
$author = $cfg['author'] ?? 'LSMa';
$contribute = $cfg['contribute'] ?? '#';
$repo = $cfg['repo'] ?? '#';
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>QQ捏脸 · 把图片变成会弹的脸</title>
<meta name="description" content="QQ捏脸是一个浏览器小游戏：上传任意图片，涂抹想变软的部位，然后按住往外拉、松手「啪」地弹回去。支持保存分享与源码下载，纯前端 WebGL 实现，无需安装。">
<meta name="keywords" content="QQ捏脸,捏脸,弹脸,雪糕脸蛋,mochi,WebGL,图片变形,浏览器小游戏,互动玩具,LSMa">
<meta name="author" content="<?= htmlspecialchars($author, ENT_QUOTES) ?>">
<meta name="theme-color" content="#f56d9b">
<meta property="og:type" content="website">
<meta property="og:title" content="QQ捏脸 · 把图片变成会弹的脸">
<meta property="og:description" content="上传图片，涂一涂，按住拉出去松手就弹回来。浏览器直接玩，无需安装。">
<meta property="og:image" content="<?= htmlspecialchars($base, ENT_QUOTES) ?>/og.png">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><circle cx='16' cy='16' r='14' fill='%23ffb7c5'/><circle cx='11' cy='13' r='2.4' fill='%233a2b2f'/><circle cx='21' cy='13' r='2.4' fill='%233a2b2f'/><ellipse cx='16' cy='20' rx='4' ry='2.6' fill='%23ff8fb1'/></svg>">
<style>
:root {
    --ink: #3a2b2f;
    --muted: #a08a92;
    --line: #ffd7e2;
    --bg: #ffffff;
    --card: #ffffff;
    --pink: #ff8fb1;
    --pink-deep: #f56d9b;
    --pink-soft: #ffe3ec;
}
* { box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
html, body { margin: 0; height: 100%; }
body {
    background: var(--bg);
    color: var(--ink);
    font: 15px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    overscroll-behavior: none;
}
header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 16px 20px 4px;
}
header h1 { margin: 0; font-size: 16px; font-weight: 600; letter-spacing: 0; }
header .tag { font-size: 11.5px; color: var(--muted); opacity: .8; }
.hamburger {
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0;
    border: 1px solid #eee;
    border-radius: 9px;
    background: #fff;
    color: var(--ink);
    cursor: pointer;
}
.hamburger span {
    display: block;
    width: 15px;
    height: 1.5px;
    background: currentColor;
    border-radius: 1px;
    position: relative;
}
.hamburger span::before,
.hamburger span::after {
    content: '';
    position: absolute;
    left: 0;
    width: 15px;
    height: 1.5px;
    background: currentColor;
    border-radius: 1px;
}
.hamburger span::before { top: -5px; }
.hamburger span::after { top: 5px; }

.steps {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 2px;
    padding: 8px 12px 2px;
    user-select: none;
}
.step {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 13px;
    color: var(--muted);
    border: 1px solid transparent;
    background: transparent;
    cursor: default;
    font-family: inherit;
}
.step b {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--pink-soft);
    color: var(--pink-deep);
    font-size: 11.5px;
}
.step.on { background: var(--pink-deep); color: #fff; }
.step.on b { background: rgba(255,255,255,.30); color: #fff; }
.step.done { cursor: pointer; color: var(--pink-deep); }
.step.done b { background: transparent; border: 1px solid var(--pink-deep); color: var(--pink-deep); }
.step-sep { width: 12px; height: 1px; background: var(--line); }

main {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 8px 14px 20px;
    gap: 10px;
}
.stage {
    position: relative;
    width: min(94vw, 62vh, 560px);
    aspect-ratio: 1 / 1;
    border-radius: 16px;
    background: #fafafa;
    border: 1px solid #f0f0f0;
    overflow: hidden;
    touch-action: none;
}
canvas { display: block; width: 100%; height: 100%; }
.empty {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    background: #fff;
    text-align: center;
    padding: 24px;
}
.empty strong { font-size: 15px; font-weight: 600; }
.empty span { color: var(--muted); font-size: 12.5px; }
.plus-btn {
    width: 62px;
    height: 62px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: 50%;
    background: var(--pink-deep);
    color: #fff;
    font-size: 30px;
    line-height: 1;
    cursor: pointer;
    box-shadow: 0 10px 24px rgba(245,109,155,.32);
    transition: transform .14s ease;
}
.plus-btn:active { transform: scale(.94); }
.plus-btn input { display: none; }
.ghost-btn {
    padding: 7px 16px;
    font-size: 13px;
    color: var(--pink-deep);
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 9px;
    cursor: pointer;
    font-family: inherit;
}
.ghost-btn:hover { border-color: var(--pink); }

.big-actions { display: flex; gap: 8px; justify-content: center; width: min(94vw, 62vh, 560px); }
button.main-btn {
    flex: 1;
    padding: 12px 20px;
    font: inherit;
    font-size: 14.5px;
    font-weight: 600;
    color: #fff;
    background: var(--pink-deep);
    border: 0;
    border-radius: 10px;
    cursor: pointer;
    transition: transform .12s ease, opacity .15s;
}
button.main-btn:active { transform: translateY(1px) scale(.99); }
button.main-btn:disabled { opacity: .45; cursor: default; }

/* ---------- 抽屉 ---------- */
.drawer-mask {
    position: fixed;
    inset: 0;
    background: rgba(58,43,47,.28);
    opacity: 0;
    pointer-events: none;
    transition: opacity .2s ease;
    z-index: 60;
}
.drawer-mask.show { opacity: 1; pointer-events: auto; }
.drawer {
    position: fixed;
    top: 0;
    right: 0;
    bottom: 0;
    width: min(280px, 84vw);
    background: #fff;
    border-left: 1px solid #f2f2f2;
    transform: translateX(102%);
    transition: transform .26s cubic-bezier(.32,.72,0,1);
    z-index: 61;
    display: flex;
    flex-direction: column;
    padding: 14px 14px 18px;
    overflow-y: auto;
}
.drawer.show { transform: none; }
.drawer-head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 2px 4px 10px;
    font-size: 11.5px;
    letter-spacing: .1em;
    color: var(--muted);
}
.drawer-close {
    width: 28px;
    height: 28px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 0;
    background: transparent;
    color: var(--muted);
    font-size: 18px;
    cursor: pointer;
    border-radius: 6px;
}
.drawer-close:hover { background: #fafafa; color: var(--ink); }
.drawer h3 {
    margin: 12px 4px 6px;
    font-size: 11px;
    letter-spacing: .08em;
    color: var(--muted);
    font-weight: 500;
}
.drawer-row { display: flex; gap: 6px; }
.drawer-row button {
    flex: 1;
    padding: 9px 8px;
    font: inherit;
    font-size: 13px;
    color: var(--ink);
    background: #fff;
    border: 1px solid #eee;
    border-radius: 8px;
    cursor: pointer;
}
.drawer-row button.on {
    background: var(--pink-deep);
    border-color: var(--pink-deep);
    color: #fff;
}
.drawer-slider {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 4px 2px;
    font-size: 12px;
    color: var(--muted);
}
.drawer-slider input[type="range"] {
    -webkit-appearance: none;
    flex: 1;
    height: 20px;
    background: transparent;
}
.drawer-slider input[type="range"]::-webkit-slider-runnable-track {
    height: 5px;
    border-radius: 3px;
    background: var(--pink-soft);
}
.drawer-slider input[type="range"]::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--pink-deep);
    margin-top: -6.5px;
}
.drawer a, .drawer button.linklike {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 10px;
    margin-bottom: 2px;
    font-size: 14px;
    color: var(--ink);
    background: transparent;
    border: 0;
    border-radius: 8px;
    text-decoration: none;
    text-align: left;
    cursor: pointer;
    font-family: inherit;
    width: 100%;
}
.drawer a:hover, .drawer button.linklike:hover { background: #fafafa; text-decoration: none; }
.drawer .foot {
    margin-top: auto;
    padding-top: 12px;
    border-top: 1px solid #f4f4f4;
    font-size: 11px;
    color: var(--muted);
    text-align: center;
}

.toast {
    position: fixed;
    left: 50%;
    bottom: 26px;
    transform: translate(-50%, 12px);
    background: rgba(58,43,47,.94);
    color: #fff;
    font-size: 13px;
    padding: 10px 18px;
    border-radius: 999px;
    opacity: 0;
    pointer-events: none;
    transition: opacity .2s ease, transform .2s ease;
    z-index: 80;
}
.toast.show { opacity: 1; transform: translate(-50%, 0); }

.only-s1, .only-s2, .only-s3 { display: none; }
body[data-step="1"] .only-s1 { display: flex; }
body[data-step="2"] .only-s2 { display: flex; }
body[data-step="3"] .only-s3 { display: flex; }
.only-s1, .only-s3 { flex-direction: column; align-items: center; gap: 10px; width: 100%; }

@media (max-width: 520px) {
    header { padding: 12px 14px 2px; }
    .step { padding: 5px 9px; font-size: 12.5px; }
}
</style>
</head>
<body data-step="1">

<header>
    <div>
        <h1>QQ捏脸</h1>
        <div class="tag">拖动 → 松手 → 啪</div>
    </div>
    <button type="button" class="hamburger" id="menuBtn" aria-label="打开菜单"><span></span></button>
</header>

<div class="steps">
    <button type="button" class="step on" id="st1"><b>1</b>导入</button>
    <span class="step-sep"></span>
    <button type="button" class="step" id="st2"><b>2</b>涂抹</button>
    <span class="step-sep"></span>
    <button type="button" class="step" id="st3"><b>3</b>开玩</button>
</div>

<main>
    <div class="stage" id="stage">
        <canvas id="gl"></canvas>
        <div class="empty" id="empty">
            <strong>放一张图片</strong>
            <span>点加号选图，或直接拖进来 / Ctrl+V 粘贴</span>
            <label class="plus-btn" title="选择图片">
                +
                <input type="file" id="file" accept="image/*">
            </label>
            <button type="button" class="ghost-btn" id="sample">用示例脸蛋</button>
        </div>
    </div>

    <div class="only-s1">
        <div class="big-actions">
            <button type="button" class="main-btn" id="pickBtn">选择图片</button>
            <button type="button" class="main-btn" id="sample2" style="background:#fff;color:var(--pink-deep);border:1px solid var(--line)">示例脸蛋</button>
        </div>
    </div>

    <div class="only-s3">
        <div class="big-actions">
            <button type="button" class="main-btn" id="backStep2" style="background:#fff;color:var(--ink);border:1px solid #eee">再涂一下</button>
            <button type="button" class="main-btn" id="save">保存分享</button>
        </div>
    </div>

    <div class="hint" id="hint" style="width:min(94vw,62vh,560px);font-size:12.5px;color:var(--muted);text-align:center;min-height:18px"></div>
</main>

<div class="drawer-mask" id="drawerMask"></div>
<aside class="drawer" id="drawer">
    <div class="drawer-head"><span>菜单</span><button type="button" class="drawer-close" id="drawerClose">&times;</button></div>

    <h3>笔刷</h3>
    <div class="drawer-row">
        <button type="button" data-tool="brush" class="on">画笔</button>
        <button type="button" data-tool="eraser">橡皮擦</button>
    </div>
    <div class="drawer-slider">
        <span>粗细</span>
        <input type="range" id="radius" min="24" max="120" value="60">
    </div>

    <h3>涂抹</h3>
    <div class="drawer-row">
        <button type="button" id="clearAll">全部清除</button>
        <button type="button" id="softAll">全部软糯</button>
    </div>

    <h3>玩耍</h3>
    <div class="drawer-row">
        <button type="button" id="toStep3">开玩！</button>
    </div>

    <h3>更多</h3>
    <a href="gallery.php" target="_blank" rel="noopener">大家做的脸蛋</a>
    <a href="download.php" download>下载源码</a>
    <button type="button" class="linklike" id="openAbout">查看简介</button>
    <a href="<?= htmlspecialchars($contribute, ENT_QUOTES) ?>" target="_blank" rel="noopener">贡献</a>

    <div class="foot">by <?= htmlspecialchars($author, ENT_QUOTES) ?></div>
</aside>

<div class="drawer-mask" id="aboutMask"></div>
<aside class="drawer" id="aboutDrawer" style="width:min(320px,86vw)">
    <div class="drawer-head"><span>简介</span><button type="button" class="drawer-close" id="aboutClose">&times;</button></div>
    <div style="padding:2px 6px;font-size:13.5px;line-height:1.75;color:var(--ink)">
        <p style="margin:0 0 10px"><strong>QQ捏脸</strong> 是一个很朴素的玩具：把你喜欢的图片放进来，涂一涂想变软的地方，然后按住往外拉、松手，看它「啪」地弹回去。</p>
        <p style="margin:0 0 10px">它不依赖任何游戏引擎：图片被铺成一张网格，每个顶点用阻尼弹簧控制，变形由 WebGL 的顶点着色器绘制。所以它很轻，一张图就能玩，手机上也能跑。</p>
        <p style="margin:0 0 10px">灵感来自那种一戳就会回弹的软胶玩具。全部代码开源，欢迎拿去改成你自己的版本。</p>
        <p style="margin:0;color:var(--muted);font-size:12.5px">作者：<?= htmlspecialchars($author, ENT_QUOTES) ?><br>源码：<a href="<?= htmlspecialchars($repo, ENT_QUOTES) ?>" target="_blank" rel="noopener" style="color:var(--pink-deep)"><?= htmlspecialchars($repo, ENT_QUOTES) ?></a></p>
    </div>
</aside>

<div class="toast" id="toast"></div>

<script>
window.GAME_BASE = <?= json_encode($base) ?>;
</script>
<script src="app.js?v=100"></script>
</body>
</html>
