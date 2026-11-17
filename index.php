<?php
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>QQ捏脸</title>
<style>
:root {
    --ink: #3a2b2f;
    --muted: #a08a92;
    --line: #ffd7e2;
    --bg: #fff0f4;
    --card: #ffffff;
    --pink: #ff8fb1;
    --pink-deep: #f56d9b;
    --pink-soft: #ffe3ec;
    --shadow: 0 8px 24px rgba(245, 109, 155, .10);
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
    padding: 14px 18px 6px;
}
header h1 { margin: 0; font-size: 17px; letter-spacing: .02em; }
header .tag { font-size: 12px; color: var(--muted); }
.steps {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 6px 12px 4px;
    user-select: none;
}
.step {
    display: inline-flex;
    align-items: center;
    gap: 7px;
    padding: 8px 16px;
    border-radius: 999px;
    font-size: 14px;
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
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: var(--pink-soft);
    color: var(--pink-deep);
    font-size: 13px;
}
.step.on {
    background: var(--pink);
    color: #fff;
    box-shadow: 0 6px 16px rgba(245, 109, 155, .30);
}
.step.on b { background: rgba(255, 255, 255, .30); color: #fff; }
.step.done { color: var(--pink-deep); cursor: pointer; }
.step.done b { background: var(--pink); color: #fff; }
.step-sep { width: 14px; height: 1px; background: var(--line); }
main {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 6px 14px 16px;
    gap: 12px;
}
.stage {
    position: relative;
    width: min(94vw, 62vh, 560px);
    aspect-ratio: 1 / 1;
    border-radius: 22px;
    background: var(--card);
    border: 1px solid var(--line);
    overflow: hidden;
    touch-action: none;
    box-shadow: var(--shadow);
}
canvas { display: block; width: 100%; height: 100%; }
.empty {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background: repeating-linear-gradient(45deg, #fff7fa, #fff7fa 14px, #ffe9f0 14px, #ffe9f0 28px);
    text-align: center;
    padding: 24px;
}
.empty strong { font-size: 16px; }
.empty span { color: var(--muted); font-size: 13px; }
.empty label, .pinkbtn {
    margin-top: 6px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 999px;
    background: var(--pink-deep);
    color: #fff;
    font-size: 14.5px;
    font-weight: 600;
    cursor: pointer;
    border: 0;
    font-family: inherit;
    box-shadow: 0 8px 20px rgba(245, 109, 155, .35);
}
.empty input { display: none; }
.pinkbtn:active { transform: translateY(1px) scale(.99); }
.toolbar {
    width: min(94vw, 62vh, 560px);
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: center;
    align-items: center;
}
button, .tools button {
    appearance: none;
    border: 1px solid var(--line);
    background: var(--card);
    color: var(--ink);
    font: inherit;
    font-size: 14px;
    padding: 10px 16px;
    border-radius: 999px;
    cursor: pointer;
    transition: background .15s ease, color .15s ease, border-color .15s ease, transform .1s ease, opacity .15s;
}
button:hover { border-color: var(--pink); }
button:active { transform: translateY(1px) scale(.99); }
button.on {
    background: var(--pink-deep);
    border-color: var(--pink-deep);
    color: #fff;
    box-shadow: 0 6px 14px rgba(245, 109, 155, .30);
}
button:disabled { opacity: .45; cursor: default; }
.big {
    padding: 12px 26px;
    font-size: 15px;
    font-weight: 600;
}
.toolrow {
    width: min(94vw, 62vh, 560px);
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    justify-content: center;
}
.seg {
    display: inline-flex;
    background: var(--pink-soft);
    border-radius: 999px;
    padding: 3px;
    gap: 2px;
}
.seg button {
    border: 0;
    background: transparent;
    padding: 8px 14px;
    font-size: 13.5px;
    color: var(--pink-deep);
}
.seg button.on {
    background: #fff;
    color: var(--pink-deep);
    box-shadow: 0 2px 8px rgba(245, 109, 155, .25);
}
.slider-wrap {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: var(--card);
    border: 1px solid var(--line);
    border-radius: 999px;
    padding: 8px 16px;
}
.slider-wrap span { font-size: 13px; color: var(--muted); }
input[type="range"] {
    -webkit-appearance: none;
    width: 130px;
    height: 22px;
    background: transparent;
}
input[type="range"]::-webkit-slider-runnable-track {
    height: 6px;
    border-radius: 3px;
    background: var(--pink-soft);
}
input[type="range"]::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: var(--pink-deep);
    margin-top: -7px;
    box-shadow: 0 2px 6px rgba(245, 109, 155, .4);
}
.hint {
    width: min(94vw, 62vh, 560px);
    font-size: 13px;
    color: var(--muted);
    text-align: center;
    min-height: 20px;
}
.card-plain {
    width: min(94vw, 62vh, 560px);
    background: var(--card);
    border: 1px solid var(--line);
    border-radius: 18px;
    padding: 16px;
    box-shadow: var(--shadow);
}
.card-plain h2 { margin: 0 0 10px; font-size: 15px; }
.gallery {
    width: min(94vw, 62vh, 560px);
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(96px, 1fr));
    gap: 8px;
}
.gallery a {
    display: block;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid var(--line);
    background: #fff;
    position: relative;
}
.gallery img { display: block; width: 100%; height: auto; }
.gallery small {
    position: absolute;
    left: 6px;
    bottom: 4px;
    font-size: 10px;
    color: #fff;
    text-shadow: 0 1px 3px rgba(0,0,0,.5);
}
.gallery-empty {
    grid-column: 1 / -1;
    text-align: center;
    color: var(--muted);
    font-size: 13px;
    padding: 14px;
}
.toast {
    position: fixed;
    left: 50%;
    bottom: 26px;
    transform: translate(-50%, 12px);
    background: rgba(58, 43, 47, .94);
    color: #fff;
    font-size: 13px;
    padding: 10px 18px;
    border-radius: 999px;
    opacity: 0;
    pointer-events: none;
    transition: opacity .2s ease, transform .2s ease;
    z-index: 30;
}
.toast.show { opacity: 1; transform: translate(-50%, 0); }
.only-s1, .only-s2, .only-s3 { display: none; }
body[data-step="1"] .only-s1 { display: flex; }
body[data-step="2"] .only-s2 { display: flex; }
body[data-step="3"] .only-s3 { display: flex; }
body[data-step="2"] .only-s2, body[data-step="3"] .only-s3 { flex-wrap: wrap; }
.resetview {
    position: absolute;
    right: 12px;
    top: 12px;
    z-index: 5;
    background: var(--card);
    border: 1px solid var(--line);
    border-radius: 12px;
    padding: 7px 12px;
    font-size: 12.5px;
    color: var(--pink-deep);
    display: none;
}
body[data-step="2"] .resetview { display: inline-flex; }
@media (max-width: 520px) {
    header { padding: 12px 14px 4px; }
    .step { padding: 7px 12px; font-size: 13.5px; }
    button { padding: 9px 13px; font-size: 13.5px; }
    .big { padding: 11px 22px; font-size: 14.5px; }
}
.brush-cursor {
    position: absolute;
    left: 0;
    top: 0;
    border-radius: 50%;
    border: 1.5px solid rgba(245, 109, 155, .85);
    pointer-events: none;
    opacity: 0;
    z-index: 4;
    transition: opacity .12s ease;
    will-change: transform;
}
.brush-cursor.on { opacity: 1; }

.gallery button.gallery-item {
    padding: 0;
    border: 1px solid var(--line);
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    cursor: pointer;
    position: relative;
    display: block;
    width: 100%;
    aspect-ratio: 1 / 1;
}
.gallery button.gallery-item img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.gallery button.gallery-item:hover { border-color: var(--pink); box-shadow: 0 6px 16px rgba(245,109,155,.18); }
.gallery button.gallery-item small {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 0;
    padding: 3px 6px;
    font-size: 10px;
    color: #fff;
    background: linear-gradient(transparent, rgba(58,43,47,.55));
    text-align: left;
}

/* ---------- 简约版式覆盖 ---------- */
body { background: #fff; }
header { padding: 18px 20px 2px; }
header h1 { font-size: 16px; font-weight: 600; letter-spacing: 0; }
header .tag { font-size: 11.5px; opacity: .7; }
.steps { padding: 10px 12px 2px; gap: 2px; }
.step { padding: 6px 12px; font-size: 13px; border-radius: 8px; }
.step b { width: 18px; height: 18px; font-size: 11.5px; }
.step.on { box-shadow: none; background: var(--pink-deep); }
.step.done b { background: transparent; border: 1px solid var(--pink-deep); color: var(--pink-deep); }
main { padding: 8px 14px 20px; gap: 10px; }
.stage { border-radius: 16px; border-color: #f0f0f0; box-shadow: none; background: #fafafa; }
.toolbar { gap: 6px; }
button, .tools button { padding: 8px 14px; font-size: 13.5px; border-radius: 9px; border-color: #eee; }
button.on { background: var(--pink-deep); border-color: var(--pink-deep); box-shadow: none; }
button.big { padding: 9px 18px; font-size: 14px; }
button.pinkbtn, .pinkbtn { border-radius: 9px; box-shadow: none; }
.card-plain { border-radius: 12px; border-color: #f0f0f0; box-shadow: none; padding: 14px; }
.gallery { gap: 6px; grid-template-columns: repeat(auto-fill, minmax(84px, 1fr)); }
.gallery button.gallery-item { border-radius: 9px; }
.seg { background: #f7f7f7; padding: 2px; }
.seg button { padding: 6px 12px; font-size: 13px; border-radius: 7px; }
.slider-wrap { padding: 6px 12px; border-color: #eee; }
input[type="range"] { width: 96px; }
.brush-cursor { border-color: rgba(245,109,155,.55); }
.hint { font-size: 12.5px; opacity: .75; }
a.dl-link {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 9px 18px;
    font-size: 14px;
    color: var(--ink);
    background: var(--card);
    border: 1px solid #eee;
    border-radius: 9px;
    text-decoration: none;
    font-weight: 500;
}
a.dl-link:hover { border-color: var(--pink); color: var(--pink-deep); text-decoration: none; }
a.dl-link:active { transform: translateY(1px); }

</style>
</head>
<body data-step="1">
<header>
    <h1>QQ捏脸</h1>
    <span class="tag">拖动 → 松手 → 啪</span>
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
        <div class="brush-cursor" id="brushCursor"></div>
        <button type="button" class="resetview" id="resetView">重置视图</button>
        <div class="empty" id="empty">
            <strong>先放一张喜欢的图</strong>
            <span>选文件、拖进来，或者直接 Ctrl+V 粘贴</span>
            <label>
                选择图片
                <input type="file" id="file" accept="image/*">
            </label>
            <button type="button" id="sample" class="ghost" style="background:var(--card);color:var(--pink-deep);box-shadow:none;border:1px solid var(--line)">用示例脸蛋</button>
        </div>
    </div>

    <div class="toolbar only-s1">
        <label class="pinkbtn">
            选择图片
            <input type="file" id="file2" accept="image/*" style="display:none">
        </label>
        <button type="button" id="sample2" class="big">用示例脸蛋</button>
        <button type="button" id="toStep2" class="big" disabled>下一步：涂抹</button>
    </div>

    <div class="only-s2" style="flex-direction:column;align-items:center;gap:8px">
        <div class="toolrow">
            <div class="seg" id="toolSeg">
                <button type="button" data-tool="brush" class="on">画笔</button>
                <button type="button" data-tool="eraser">橡皮擦</button>
            </div>
            <div class="seg" id="edgeSeg">
                <button type="button" data-edge="hard" class="on">清晰</button>
                <button type="button" data-edge="soft">柔和</button>
            </div>
            <div class="slider-wrap">
                <span>粗细</span>
                <input type="range" id="radius" min="24" max="120" value="60">
            </div>
            <button type="button" id="clearAll">全部清除</button>
        </div>
        <div class="toolbar">
            <button type="button" id="softAll" class="big">全部软糯</button>
            <button type="button" id="toStep3" class="big pinkbtn">开玩！</button>
        </div>
    </div>

    <div class="toolbar only-s3">
        <button type="button" id="backStep2" class="big">再涂一下</button>
        <button type="button" id="bounce" class="big">重置回弹</button>
        <a href="download.php" class="big dl-link" download>下载源码</a>
        <button type="button" id="save" class="big pinkbtn">保存分享</button>
    </div>

    <div class="hint" id="hint"></div>

    <div class="card-plain" id="galleryCard" style="display:none">
        <h2>大家做的脸蛋</h2>
        <div class="gallery" id="gallery"></div>
    </div>
</main>

<div class="toast" id="toast"></div>

<script>
window.GAME_BASE = <?= json_encode($base) ?>;
</script>
<script src="app.js?v=91"></script>
</body>
</html>
