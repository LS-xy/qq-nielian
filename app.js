(function () {
    'use strict';

    /* ================= 配置 ================= */

    var N = 84;
    var VN = N + 1;
    var NV = VN * VN;

    var K = 0.115;
    var DAMP = 0.245;
    var COHERE = 0.10;
    var COHERE_PASSES = 2;
    var MAX_PULL = 44;
    var SUBSTEPS = 3;
    var REST_EPS = 0.05;

    var physicsOn = false;

    var GRAB_RATIO = 0.16;

    /* ================= 元素 ================= */

    var stage = document.getElementById('stage');
    var canvas = document.getElementById('gl');
    var empty = document.getElementById('empty');
    var hint = document.getElementById('hint');
    var toastEl = document.getElementById('toast');
    var radiusInput = document.getElementById('radius');
    var brushCursor = document.getElementById('brushCursor');

    var gl = canvas.getContext('webgl', { premultipliedAlpha: false, alpha: false, preserveDrawingBuffer: true });
    if (!gl) {
        hint.textContent = '这个浏览器不支持 WebGL，换个浏览器试试。';
        return;
    }

    var cssW = 1;
    var cssH = 1;
    var dpr = 1;

    var rx = new Float32Array(NV);
    var ry = new Float32Array(NV);
    var px = new Float32Array(NV);
    var py = new Float32Array(NV);
    var vx = new Float32Array(NV);
    var vy = new Float32Array(NV);
    var wt = new Float32Array(NV);
    var uv = new Float32Array(NV * 2);
    var vert = new Float32Array(NV * 5);

    var imgW = 0;
    var imgH = 0;
    var offsetX = 0;
    var offsetY = 0;
    var drawScale = 1;
    var baseScale = 1;
    var baseOffsetX = 0;
    var baseOffsetY = 0;
    var viewScale = 1;
    var viewShiftX = 0;
    var viewShiftY = 0;

    var hasImage = false;
    var step = 1;
    var tool = 'brush';
    var edge = 'hard';
    var brushR = 60;

    var pointers = new Map();
    var pinch = null;
    var dirty = true;

    /* ================= 着色器 ================= */

    var VS = [
        'attribute vec2 a_pos;',
        'attribute vec2 a_uv;',
        'attribute float a_w;',
        'varying vec2 v_uv;',
        'varying float v_w;',
        'void main() {',
        '  v_uv = a_uv;',
        '  v_w = a_w;',
        '  gl_Position = vec4(a_pos, 0.0, 1.0);',
        '}'
    ].join('\n');

    var FS = [
        'precision mediump float;',
        'uniform sampler2D u_tex;',
        'uniform float u_paint;',
        'varying vec2 v_uv;',
        'varying float v_w;',
        'void main() {',
        '  vec4 c = texture2D(u_tex, v_uv);',
        '  float w = clamp(v_w, 0.0, 1.0);',
        '  float veil = 1.0 - smoothstep(0.20, 0.86, w);',
        '  c.rgb = mix(c.rgb, vec3(1.0), veil * u_paint * 0.28);',
        '  float band = smoothstep(0.72, 0.90, w) * (1.0 - smoothstep(0.93, 0.995, w));',
        '  c.rgb *= (1.0 - band * u_paint * 0.10);',        '  gl_FragColor = c;',
        '}'
    ].join('\n');

    function compile(type, src) {
        var sh = gl.createShader(type);
        gl.shaderSource(sh, src);
        gl.compileShader(sh);
        if (!gl.getShaderParameter(sh, gl.COMPILE_STATUS)) {
            throw new Error(gl.getShaderInfoLog(sh));
        }
        return sh;
    }

    var prog = gl.createProgram();
    gl.attachShader(prog, compile(gl.VERTEX_SHADER, VS));
    gl.attachShader(prog, compile(gl.FRAGMENT_SHADER, FS));
    gl.linkProgram(prog);
    gl.useProgram(prog);

    var aPos = gl.getAttribLocation(prog, 'a_pos');
    var aUv = gl.getAttribLocation(prog, 'a_uv');
    var aW = gl.getAttribLocation(prog, 'a_w');
    var uTex = gl.getUniformLocation(prog, 'u_tex');
    var uPaint = gl.getUniformLocation(prog, 'u_paint');

    var vbo = gl.createBuffer();
    var ibo = gl.createBuffer();

    var indices = new Uint16Array(N * N * 6);
    (function () {
        var k = 0;
        for (var y = 0; y < N; y++) {
            for (var x = 0; x < N; x++) {
                var a = y * VN + x;
                var b = a + 1;
                var c = a + VN;
                var d = c + 1;
                indices[k++] = a; indices[k++] = c; indices[k++] = b;
                indices[k++] = b; indices[k++] = c; indices[k++] = d;
            }
        }
        gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, ibo);
        gl.bufferData(gl.ELEMENT_ARRAY_BUFFER, indices, gl.STATIC_DRAW);
    })();

    var tex = gl.createTexture();
    gl.bindTexture(gl.TEXTURE_2D, tex);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
    gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, 1, 1, 0, gl.RGBA, gl.UNSIGNED_BYTE,
        new Uint8Array([255, 240, 244, 255]));

    gl.clearColor(1, 1, 1, 1);

    /* ================= 布局 ================= */

    function layout() {
        var r = stage.getBoundingClientRect();
        cssW = Math.max(1, r.width);
        cssH = Math.max(1, r.height);
        dpr = Math.min(window.devicePixelRatio || 1, 3);
        canvas.width = Math.round(cssW * dpr);
        canvas.height = Math.round(cssH * dpr);
        gl.viewport(0, 0, canvas.width, canvas.height);

        for (var y = 0; y < VN; y++) {
            for (var x = 0; x < VN; x++) {
                var i = y * VN + x;
                rx[i] = (x / N) * cssW;
                ry[i] = (y / N) * cssH;
                px[i] = rx[i];
                py[i] = ry[i];
            }
        }
        recomputeView();
        dirty = true;
    }

    function recomputeView() {
        if (!imgW) return;
        baseScale = Math.max(cssW / imgW, cssH / imgH);
        drawScale = baseScale * viewScale;
        offsetX = (cssW - imgW * drawScale) / 2 + viewShiftX;
        offsetY = (cssH - imgH * drawScale) / 2 + viewShiftY;
        updateUV();
    }

    function updateUV() {
        for (var i = 0; i < NV; i++) {
            if (!imgW) {
                uv[i * 2] = rx[i] / cssW;
                uv[i * 2 + 1] = ry[i] / cssH;
                continue;
            }
            var sx = (rx[i] - offsetX) / drawScale;
            var sy = (ry[i] - offsetY) / drawScale;
            uv[i * 2] = sx / imgW;
            uv[i * 2 + 1] = sy / imgH;
        }
    }

    /* ================= 图片 ================= */

    function uploadTexture(img) {
        /* 缩放到 2 的幂（≤2048），再生成 mipmap，避免大图缩小时发糊 */
        var maxSide = 2048;
        var w = img.width;
        var h = img.height;
        var scale = Math.min(1, maxSide / Math.max(w, h));
        w = Math.max(1, Math.round(w * scale));
        h = Math.max(1, Math.round(h * scale));
        w = pow2floor(w);
        h = pow2floor(h);

        var c = document.createElement('canvas');
        c.width = w;
        c.height = h;
        var ctx = c.getContext('2d');
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(img, 0, 0, w, h);

        gl.bindTexture(gl.TEXTURE_2D, tex);
        gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, false);
        gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, c);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
        gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR_MIPMAP_LINEAR);
        gl.generateMipmap(gl.TEXTURE_2D);
    }

    function pow2floor(v) {
        var p = 1;
        while (p * 2 <= v) p *= 2;
        return p;
    }

    function applyImage(img) {
        imgW = img.width;
        imgH = img.height;
        viewScale = 1;
        viewShiftX = 0;
        viewShiftY = 0;
        recomputeView();

        uploadTexture(img);

        wt.fill(0);
        resetNodes();
        hasImage = true;
        empty.style.display = 'none';
        if (typeof setStep === 'function' && step === 1) {
            setStep(2);
        }
        document.getElementById('toStep2').disabled = false;
        dirty = true;
    }

    function loadFromUrl(url) {
        var img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = function () {
            applyImage(img);
            setStep(2);
            toast('已载入，可以为这张图重新涂抹');
        };
        img.onerror = function () {
            toast('这张图读不出来');
        };
        img.src = url;
    }

    function loadBlob(blob) {
        var url = URL.createObjectURL(blob);
        var img = new Image();
        img.onload = function () {
            applyImage(img);
            URL.revokeObjectURL(url);
            toast('图片已放好');
        };
        img.onerror = function () {
            toast('这张图读不出来');
            URL.revokeObjectURL(url);
        };
        img.src = url;
    }

    function loadFile(f) {
        if (!f) return;
        if (!/^image\//.test(f.type)) {
            toast('请选图片文件');
            return;
        }
        loadBlob(f);
    }

    function sampleImage() {
        var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512">' +
            '<rect width="512" height="512" fill="#ffe9ef"/>' +
            '<circle cx="256" cy="272" r="152" fill="#fff8fa"/>' +
            '<circle cx="206" cy="238" r="25" fill="#3a2b2f"/>' +
            '<circle cx="306" cy="238" r="25" fill="#3a2b2f"/>' +
            '<circle cx="213" cy="230" r="8" fill="#fff"/>' +
            '<circle cx="313" cy="230" r="8" fill="#fff"/>' +
            '<ellipse cx="256" cy="298" rx="32" ry="21" fill="#ffb3c6"/>' +
            '<path d="M228 318 q28 20 56 0" stroke="#c86b84" stroke-width="7" fill="none" stroke-linecap="round"/>' +
            '<circle cx="152" cy="308" r="21" fill="#ffc9d6" opacity=".8"/>' +
            '<circle cx="360" cy="308" r="21" fill="#ffc9d6" opacity=".8"/>' +
            '</svg>';
        var img = new Image();
        img.onload = function () {
            applyImage(img);
            toast('用示例脸蛋玩一下');
        };
        img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
    }

    var fileInput = document.getElementById('file');
    fileInput.addEventListener('change', function (e) { loadFile(e.target.files[0]); });
    document.getElementById('sample').addEventListener('click', sampleImage);
    document.getElementById('sample2').addEventListener('click', sampleImage);

    var pickBtn = document.getElementById('pickBtn');
    if (pickBtn) pickBtn.addEventListener('click', function () { fileInput.click(); });

    window.addEventListener('paste', function (e) {
        var items = e.clipboardData && e.clipboardData.items;
        if (!items) return;
        for (var i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') === 0) {
                loadFile(items[i].getAsFile());
                break;
            }
        }
    });

    stage.addEventListener('dragover', function (e) { e.preventDefault(); });
    stage.addEventListener('drop', function (e) {
        e.preventDefault();
        var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
        if (f) loadFile(f);
    });

    /* ================= 物理 ================= */

    function resetNodes() {
        for (var i = 0; i < NV; i++) {
            px[i] = rx[i];
            py[i] = ry[i];
            vx[i] = 0;
            vy[i] = 0;
        }
        dirty = true;
    }

    function stepPhysics() {
        if (!physicsOn) return;
        var moving = false;
        var alive = false;

        for (var sub = 0; sub < SUBSTEPS; sub++) {
            for (var i = 0; i < NV; i++) {
                var w = wt[i];
                if (w <= 0.001) continue;
                var kk = K / SUBSTEPS;
                var dd = DAMP / SUBSTEPS;
                var ax = (rx[i] - px[i]) * kk * w - vx[i] * dd;
                var ay = (ry[i] - py[i]) * kk * w - vy[i] * dd;
                vx[i] += ax;
                vy[i] += ay;
                var sp = vx[i] * vx[i] + vy[i] * vy[i];
                var off = (rx[i] - px[i]) * (rx[i] - px[i]) + (ry[i] - py[i]) * (ry[i] - py[i]);
                if (sp > 0.02 || off > REST_EPS * REST_EPS) {
                    moving = true;
                    alive = true;
                }
                px[i] += vx[i];
                py[i] += vy[i];

                var ox = px[i] - rx[i];
                var oy = py[i] - ry[i];
                var d = Math.hypot(ox, oy);
                if (d > MAX_PULL) {
                    px[i] = rx[i] + ox / d * MAX_PULL;
                    py[i] = ry[i] + oy / d * MAX_PULL;
                    vx[i] *= 0.5;
                    vy[i] *= 0.5;
                }
            }
        }

        for (var p = 0; p < COHERE_PASSES; p++) {
            for (var y = 1; y < VN - 1; y++) {
                var row = y * VN;
                for (var x = 1; x < VN - 1; x++) {
                    var j = row + x;
                    var wj = wt[j];
                    if (wj <= 0.001) continue;
                    var sx = (px[j - 1] + px[j + 1] + px[j - VN] + px[j + VN]) * 0.25;
                    var sy = (py[j - 1] + py[j + 1] + py[j - VN] + py[j + VN]) * 0.25;
                    var k2 = COHERE * wj;
                    px[j] += (sx - px[j]) * k2;
                    py[j] += (sy - py[j]) * k2;
                }
            }
        }

        if (!alive) {
            for (var q = 0; q < NV; q++) {
                if (wt[q] > 0.001) {
                    px[q] = rx[q];
                    py[q] = ry[q];
                    vx[q] = 0;
                    vy[q] = 0;
                }
            }
            physicsOn = false;
            moving = true;
        }

        if (moving) dirty = true;
    }

    function smoothWeights() {
        var tmp = new Float32Array(wt);
        for (var pass = 0; pass < 2; pass++) {
            for (var y = 1; y < VN - 1; y++) {
                var row = y * VN;
                for (var x = 1; x < VN - 1; x++) {
                    var j = row + x;
                    var v = (tmp[j] * 2 + tmp[j - 1] + tmp[j + 1] + tmp[j - VN] + tmp[j + VN]) / 6;
                    wt[j] = v;
                }
            }
            tmp.set(wt);
        }
        dirty = true;
    }

    /* ================= 笔刷 ================= */

    function smoothstep(t) {
        if (t <= 0) return 0;
        if (t >= 1) return 1;
        return t * t * (3 - 2 * t);
    }

    function paintAt(cx, cy) {
        var r = brushR * (cssW / 560);
        if (r < 12) r = 12;
        var inner = r * 0.44;
        var r2 = r * r;
        var minx = Math.max(0, Math.floor(((cx - r) / cssW) * N) - 1);
        var maxx = Math.min(N, Math.ceil(((cx + r) / cssW) * N) + 1);
        var miny = Math.max(0, Math.floor(((cy - r) / cssH) * N) - 1);
        var maxy = Math.min(N, Math.ceil(((cy + r) / cssH) * N) + 1);

        for (var y = miny; y <= maxy; y++) {
            for (var x = minx; x <= maxx; x++) {
                var i = y * VN + x;
                var dx = rx[i] - cx;
                var dy = ry[i] - cy;
                var d2 = dx * dx + dy * dy;
                if (d2 > r2) continue;

                var d = Math.sqrt(d2);
                var a;
                if (d <= inner) {
                    a = 1;
                } else {
                    a = 1 - smoothstep((d - inner) / Math.max(1, r - inner));
                }

                if (tool === 'eraser') {
                    wt[i] = Math.max(0, wt[i] - a * 1.2);
                    if (wt[i] < 0.02) {
                        wt[i] = 0;
                        if (step !== 3) {
                            px[i] += (rx[i] - px[i]) * 0.4;
                            py[i] += (ry[i] - py[i]) * 0.4;
                        }
                    }
                } else {
                    if (a > wt[i]) wt[i] = a;
                }
                dirty = true;
            }
        }
    }

    function pullAt(ox, oy, dx, dy, limit) {
        var len = Math.hypot(dx, dy);
        if (len < 0.5) {
            len = 0;
        }

        var ux = len ? dx / len : 0;
        var uy = len ? dy / len : 0;

        /* 皮肤式饱和：一开始跟手，越拉越费劲，最终扯不动 */
        var eff = limit * Math.tanh(len / (limit * 0.82));

        /* 无硬边界的衰减尺度：随拉力缓慢变大 */
        var scale = cssW * (0.135 + 0.085 * (eff / limit));
        var inv = 1 / (scale * scale);

        for (var i = 0; i < NV; i++) {
            var w = wt[i];
            if (w <= 0.004) continue;

            var ddx = rx[i] - ox;
            var ddy = ry[i] - oy;
            var d2 = ddx * ddx + ddy * ddy;

            /* 高斯衰减：任何距离都有作用，但远处迅速趋近 0，无可见圆形边界 */
            var fall = Math.exp(-d2 * inv);
            fall *= w;

            if (fall < 0.0008) continue;

            px[i] = rx[i] + ux * eff * fall;
            py[i] = ry[i] + uy * eff * fall;
            vx[i] = 0;
            vy[i] = 0;
            dirty = true;
        }
    }

    function relaxDrag() {
        for (var p = 0; p < 2; p++) {
            for (var y = 1; y < VN - 1; y++) {
                var row = y * VN;
                for (var x = 1; x < VN - 1; x++) {
                    var j = row + x;
                    if (wt[j] <= 0.004) continue;
                    var sx = (px[j - 1] + px[j + 1] + px[j - VN] + px[j + VN]) * 0.25;
                    var sy = (py[j - 1] + py[j + 1] + py[j - VN] + py[j + VN]) * 0.25;
                    px[j] += (sx - px[j]) * 0.16;
                    py[j] += (sy - py[j]) * 0.16;
                }
            }
        }
    }
    function releaseGrab(ox, oy) {
        var moved = false;
        for (var i = 0; i < NV; i++) {
            if (wt[i] <= 0.001) continue;
            if (Math.abs(px[i] - rx[i]) > 0.5 || Math.abs(py[i] - ry[i]) > 0.5) {
                moved = true;
                break;
            }
        }
        if (moved) {
            physicsOn = true;
            dirty = true;
        }
    }

    function bounceAll() {
        for (var i = 0; i < NV; i++) {
            var w = wt[i];
            if (w <= 0.001) continue;
            vx[i] = (Math.random() - 0.5) * 1.6 * w;
            vy[i] = (Math.random() - 0.5) * 1.6 * w;
            px[i] += vx[i];
            py[i] += vy[i];
        }
        physicsOn = true;
        dirty = true;
        toast('啪！');
    }

    function releaseBounce() {
        var pulled = 0;
        for (var i = 0; i < NV; i++) {
            if (wt[i] <= 0.001) continue;
            vx[i] = 0;
            vy[i] = 0;
            var d = Math.hypot(px[i] - rx[i], py[i] - ry[i]);
            if (d > pulled) pulled = d;
        }
        if (pulled < 0.5) {
            physicsOn = false;
            return;
        }
        physicsOn = true;
        dirty = true;
    }
    /* ================= 指针 ================= */

    function toLocal(e) {
        var r = stage.getBoundingClientRect();
        return { x: e.clientX - r.left, y: e.clientY - r.top };
    }

    function moveCursor(x, y) {
        if (!brushCursor) return;
        var r = Math.max(10, brushR * (cssW / 560));
        brushCursor.style.width = (r * 2) + 'px';
        brushCursor.style.height = (r * 2) + 'px';
        brushCursor.style.transform = 'translate(' + (x - r) + 'px,' + (y - r) + 'px)';
    }

    stage.addEventListener('pointerdown', function (e) {
        if (!hasImage) return;
        try { stage.setPointerCapture(e.pointerId); } catch (err) {}
        var p = toLocal(e);
        pointers.set(e.pointerId, { x: p.x, y: p.y, sx: p.x, sy: p.y });

        if (pointers.size === 2 && step === 2) {
            var arr = Array.from(pointers.values());
            pinch = {
                d: Math.hypot(arr[0].x - arr[1].x, arr[0].y - arr[1].y),
                scale: viewScale,
                cx: (arr[0].x + arr[1].x) / 2,
                cy: (arr[0].y + arr[1].y) / 2,
                shiftX: viewShiftX,
                shiftY: viewShiftY
            };
        }

        physicsOn = false;

        if (step === 2) {
            if (brushCursor) brushCursor.classList.add('on');
            moveCursor(p.x, p.y);
            if (pointers.size === 1) paintAt(p.x, p.y);
        }
        e.preventDefault();
    });

    stage.addEventListener('pointermove', function (e) {
        if (!hasImage) return;

        var pp = toLocal(e);
        if (step === 2) {
            moveCursor(pp.x, pp.y);
            if (brushCursor) brushCursor.classList.add('on');
        }

        var prev = pointers.get(e.pointerId);
        if (!prev) return;
        var dx = pp.x - prev.x;
        var dy = pp.y - prev.y;

        if (step === 2) {
            if (pointers.size >= 2 && pinch) {
                var arr = Array.from(pointers.values());
                var nd = Math.hypot(arr[0].x - arr[1].x, arr[0].y - arr[1].y);
                viewScale = Math.min(4, Math.max(1, pinch.scale * (nd / Math.max(1, pinch.d))));
                viewShiftX = pinch.shiftX + (arr[0].x + arr[1].x) / 2 - pinch.cx;
                viewShiftY = pinch.shiftY + (arr[0].y + arr[1].y) / 2 - pinch.cy;
                recomputeView();
                dirty = true;
            } else {
                var steps = Math.max(1, Math.ceil(Math.hypot(dx, dy) / Math.max(4, brushR * 0.35)));
                for (var k = 1; k <= steps; k++) {
                    paintAt(prev.x + dx * k / steps, prev.y + dy * k / steps);
                }
            }
        } else if (step === 3) {
            pullAt(prev.sx, prev.sy, pp.x - prev.sx, pp.y - prev.sy, MAX_PULL);
            relaxDrag();
        }

        prev.x = pp.x;
        prev.y = pp.y;
        e.preventDefault();
    });

    function endPointer(e) {
        if (step !== 2 && brushCursor) brushCursor.classList.remove('on');
        pointers.delete(e.pointerId);
        if (pointers.size < 2) pinch = null;
        if (step === 2 && pointers.size === 0) smoothWeights();
        if (pointers.size === 0 && step === 3) {
            releaseBounce();
        }
    }

    stage.addEventListener('pointerup', endPointer);
    window.addEventListener('pointerup', endPointer);
    window.addEventListener('pointercancel', endPointer);
    stage.addEventListener('pointercancel', endPointer);

    /* ================= 渲染 ================= */

    function draw() {
        var o = 0;
        for (var i = 0; i < NV; i++) {
            vert[o++] = (px[i] / cssW) * 2 - 1;
            vert[o++] = 1 - (py[i] / cssH) * 2;
            vert[o++] = uv[i * 2];
            vert[o++] = uv[i * 2 + 1];
            vert[o++] = wt[i];
        }

        gl.bindBuffer(gl.ARRAY_BUFFER, vbo);
        gl.bufferData(gl.ARRAY_BUFFER, vert, gl.DYNAMIC_DRAW);

        var stride = 20;
        gl.enableVertexAttribArray(aPos);
        gl.vertexAttribPointer(aPos, 2, gl.FLOAT, false, stride, 0);
        gl.enableVertexAttribArray(aUv);
        gl.vertexAttribPointer(aUv, 2, gl.FLOAT, false, stride, 8);
        gl.enableVertexAttribArray(aW);
        gl.vertexAttribPointer(aW, 1, gl.FLOAT, false, stride, 16);

        gl.bindTexture(gl.TEXTURE_2D, tex);
        gl.uniform1i(uTex, 0);
        gl.uniform1f(uPaint, step === 2 ? 1 : 0);

        gl.bindBuffer(gl.ELEMENT_ARRAY_BUFFER, ibo);
        gl.drawElements(gl.TRIANGLES, indices.length, gl.UNSIGNED_SHORT, 0);
    }

    function loop() {
        if (hasImage) {
            stepPhysics();
            if (dirty) {
                gl.clear(gl.COLOR_BUFFER_BIT);
                draw();
                dirty = false;
            }
        }
        requestAnimationFrame(loop);
    }

    /* ================= 步骤 ================= */

    function setStep(n) {
        if (n > 1 && !hasImage) {
            toast('先放一张图片');
            return;
        }
        step = n;
        physicsOn = false;
        if (brushCursor) brushCursor.classList.remove('on');
        document.body.setAttribute('data-step', String(n));

        var s1 = document.getElementById('st1');
        var s2 = document.getElementById('st2');
        var s3 = document.getElementById('st3');
        [s1, s2, s3].forEach(function (el) { el.classList.remove('on', 'done'); });

        if (n === 1) {
            s1.classList.add('on');
            hint.textContent = '先选一张图，等会儿就能把它做成会晃的脸蛋。';
        } else if (n === 2) {
            s1.classList.add('done');
            s2.classList.add('on');
            hint.textContent = '把想变软的地方涂一遍。涂得越满，弹起来越软。';
        } else {
            s1.classList.add('done');
            s2.classList.add('done');
            s3.classList.add('on');
            hint.textContent = '按住脸蛋往外拉，松手就是「啪」的一下。';
        }
        dirty = true;
    }

    document.getElementById('st1').addEventListener('click', function () { setStep(1); });
    document.getElementById('st2').addEventListener('click', function () { setStep(2); });
    document.getElementById('st3').addEventListener('click', function () { setStep(3); });
    document.getElementById('toStep3').addEventListener('click', function () { setStep(3); });
    document.getElementById('backStep2').addEventListener('click', function () { setStep(2); });

    /* ================= 工具 ================= */

    var toolButtons = document.querySelectorAll('[data-tool]');
    Array.prototype.forEach.call(toolButtons, function (b) {
        b.addEventListener('click', function () {
            tool = b.getAttribute('data-tool');
            Array.prototype.forEach.call(toolButtons, function (x) {
                x.classList.toggle('on', x === b);
            });
            dirty = true;
        });
    });

    radiusInput.addEventListener('input', function () {
        brushR = parseInt(radiusInput.value, 10) || 60;
    });

    var clearBtn = document.getElementById('clearAll');
    if (clearBtn) clearBtn.addEventListener('click', function () {
        wt.fill(0);
        resetNodes();
        toast('已经全部清空');
    });

    var softBtn = document.getElementById('softAll');
    if (softBtn) softBtn.addEventListener('click', function () {
        wt.fill(1);
        toast('整张脸都软了');
        dirty = true;
    });

    /* ---------- 抽屉 ---------- */
    var drawer = document.getElementById('drawer');
    var drawerMask = document.getElementById('drawerMask');
    var aboutDrawer = document.getElementById('aboutDrawer');
    var aboutMask = document.getElementById('aboutMask');

    function openPanel(el, mask) {
        if (!el || !mask) return;
        el.classList.add('show');
        mask.classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closePanel(el, mask) {
        if (!el || !mask) return;
        el.classList.remove('show');
        mask.classList.remove('show');
        document.body.style.overflow = '';
    }

    var menuBtn = document.getElementById('menuBtn');
    if (menuBtn) menuBtn.addEventListener('click', function () { openPanel(drawer, drawerMask); });
    if (drawerMask) drawerMask.addEventListener('click', function () { closePanel(drawer, drawerMask); });
    var drawerClose = document.getElementById('drawerClose');
    if (drawerClose) drawerClose.addEventListener('click', function () { closePanel(drawer, drawerMask); });

    var aboutBtn = document.getElementById('openAbout');
    if (aboutBtn) aboutBtn.addEventListener('click', function () {
        closePanel(drawer, drawerMask);
        openPanel(aboutDrawer, aboutMask);
    });
    var aboutClose = document.getElementById('aboutClose');
    if (aboutClose) aboutClose.addEventListener('click', function () { closePanel(aboutDrawer, aboutMask); });
    if (aboutMask) aboutMask.addEventListener('click', function () { closePanel(aboutDrawer, aboutMask); });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closePanel(drawer, drawerMask);
            closePanel(aboutDrawer, aboutMask);
        }
    });

    /* 抽屉里点「开玩」时关闭抽屉 */
    var goPlay = document.getElementById('toStep3');
    if (goPlay) goPlay.addEventListener('click', function () {
        setStep(3);
        closePanel(drawer, drawerMask);
    });

    function toast(msg) {
        toastEl.textContent = msg;
        toastEl.classList.add('show');
        clearTimeout(toast._t);
        toast._t = setTimeout(function () { toastEl.classList.remove('show'); }, 1800);
    }

    /* ================= 保存分享 ================= */

    function snapshot() {
        var keep = step;
        var wasPhysics = physicsOn;
        step = 3;
        var keepPhysics = physicsOn;
        physicsOn = false;
        dirty = true;
        gl.clear(gl.COLOR_BUFFER_BIT);
        draw();
        var size = 480;
        var c = document.createElement('canvas');
        c.width = size;
        c.height = size;
        var ctx = c.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, size, size);
        ctx.drawImage(canvas, 0, 0, size, size);
        step = keep;
        physicsOn = typeof wasPhysics !== 'undefined' ? wasPhysics : physicsOn;
        dirty = true;
        return c.toDataURL('image/png');
    }

    function downloadSnapshot() {
        var keep = step;
        var wasPhysics = physicsOn;
        step = 3;
        physicsOn = false;
        dirty = true;
        gl.clear(gl.COLOR_BUFFER_BIT);
        draw();

        var size = 1024;
        var c = document.createElement('canvas');
        c.width = size;
        c.height = size;
        var ctx = c.getContext('2d');
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, size, size);
        ctx.imageSmoothingEnabled = true;
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(canvas, 0, 0, size, size);

        step = keep;
        physicsOn = wasPhysics;
        dirty = true;

        c.toBlob(function (blob) {
            if (!blob) {
                toast('下载失败');
                return;
            }
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = 'qq-nielian-' + Date.now() + '.png';
            a.click();
            setTimeout(function () { URL.revokeObjectURL(a.href); }, 3000);
            toast('已保存到相册/下载');
        }, 'image/png');
    }

    var dlBtn = document.getElementById('download');
    if (dlBtn) dlBtn.addEventListener('click', downloadSnapshot);

    document.getElementById('save').addEventListener('click', function () {
        if (!hasImage) return;
        var btn = this;
        btn.disabled = true;
        var data;
        try {
            data = snapshot();
        } catch (e) {
            toast('截图失败：' + e.message);
            btn.disabled = false;
            return;
        }

        fetch((window.GAME_BASE || '') + '/api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ image: data })
        })
            .then(function (r) { return r.json(); })
            .then(function (j) {
                btn.disabled = false;
                if (j && j.ok) {
                    toast('已保存，链接已复制');
                    if (navigator.clipboard && j.url) {
                        navigator.clipboard.writeText(j.url).catch(function () {});
                    }
                    var permalink = location.origin + location.pathname + '?img=' + encodeURIComponent(j.url);
                    try { history.replaceState(null, '', permalink); } catch (e) {}
                    hint.innerHTML = '分享链接：<a href="' + permalink + '" style="color:var(--pink-deep)">' + permalink + '</a>';
                                } else {
                    toast((j && j.msg) || '保存失败');
                }
            })
            .catch(function () {
                btn.disabled = false;
                toast('保存失败，检查网络');
            });
    });

    /* ================= 启动 ================= */

    window.addEventListener('resize', function () { layout(); });

    layout();
    setStep(1);

    (function () {
        var p = new URLSearchParams(location.search).get('img');
        if (p) {
            loadFromUrl(p);
        }
    })();
    requestAnimationFrame(loop);
})();
