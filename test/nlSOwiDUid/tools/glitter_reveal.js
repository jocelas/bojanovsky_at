(function () {
  if (window.__concertGlitterInitialized) {
    return;
  }
  window.__concertGlitterInitialized = true;

  // ========================
  // Quick toggles
  // ========================
  // "notes" = musical symbols, "circles" = classic glitter dots.
  var PARTICLE_STYLE = "notes";
  // 0.5 = subtle, 1 = normal, 2 = strong, 3 = very dense.
  var GLITTER_STRENGTH = 0.7;
  var DEBUG = false;

  var strength = Math.max(0.2, Math.min(3.0, GLITTER_STRENGTH));
  var dpr = window.devicePixelRatio || 1;
  var canvas;
  var ctx;
  var width = 0;
  var height = 0;
  var ambientParticles = [];
  var trailParticles = [];
  var pointerX = -1000;
  var pointerY = -1000;
  var lastMove = 0;
  var lastCursorUpdate = 0;
  var hasFinePointer =
    window.matchMedia && window.matchMedia("(pointer: fine)").matches;
  var cursorDot = null;
  var drawParticleFn = null;
  var introParticles = [];
  var introOuterParticles = [];
  var introStartedAt = 0;
  var introExplosionStarted = false;
  var introExplosionAt = 0;
  var introExplosionFrontRadius = 0;
  var violinShapePath = null;
  var violinPlacement = null;
  var lastFrameAt = 0;

  var PI2 = Math.PI * 2;
  var sinLookup = [];
  var noteWeightTotal = 0;

  var INTRO_HOLD_MS = 620;
  var INTRO_EXPLODE_MS = 500;
  var INTRO_REVEAL_EXTRA_MS = 3000;
  var GLITTER_DELAY = 700;
  var INTRO_GROWTH_RATE = 5.0;
  var INTRO_START_SCALE = 0.3;
  var MOBILE_INTRO_START_SCALE = 0.5;
  var GLOBAL_CIRCLE_RADIUS_SCALE = 0.02;
  var OVERLAY_GRADIENT_LENGTH = 0.95;
  var INTRO_REVEAL_FRONT_BLEND = 0.82;
  var INTRO_REVEAL_FRONT_MARGIN = 36;
  var VIOLIN_SCALE_TO_VIEWPORT_HEIGHT = 0.99;
  var MOBILE_VIOLIN_HEIGHT_SCALE_MULTIPLIER = 1;
  var VIOLIN_VIEWBOX_WIDTH = 343.15051;
  var VIOLIN_VIEWBOX_HEIGHT = 915.47546;
  var VIOLIN_GROUP_OFFSET_X = 341.06056;
  var VIOLIN_GROUP_OFFSET_Y = 266;
  var VIOLIN_PATH_D =
    "m 504.6129,1180.9743 c -3.34625,-0.5804 -5.88357,-1.8135 -8.0476,-3.911 -2.66122,-2.5794 -3.94288,-3.0635 -8.11291,-3.0645 -7.9369,0 -30.92004,-2.8898 -44.20833,-5.5548 -91.08223,-18.2668 -125.26915,-87.3031 -88.85011,-179.42194 10.66446,-26.97483 11.65182,-33.56426 6.63005,-44.24762 -3.54536,-7.54242 0.25366,-15.32723 6.88757,-14.11376 27.10093,4.9573 41.29737,-8.95808 48.11321,-47.16068 9.15847,-51.33292 -0.17504,-79.82107 -23.79388,-72.62471 -5.91948,1.80359 -10.97055,-9.02333 -6.61782,-14.18522 6.7861,-8.04763 6.9291,-13.83528 0.81336,-32.91878 -24.90197,-77.70377 11.3777,-135.7419 90.83375,-145.3106 7.18092,-0.86478 6.42075,3.42166 7.70747,-43.46069 1.5093,-54.99172 3.05408,-108.08401 3.8698,-133 0.55366,-16.91177 0.43271,-21.67066 -0.56679,-22.29969 C 488.41672,419.16288 488,416.29329 488,410.95031 488,403 488,403 476.08156,403 c -11.91844,0 -11.91844,0 -14.0192,3.3991 -8.89678,14.39529 -34.87057,3.28685 -31.68884,-13.55264 2.64359,-13.99136 23.0291,-19.09113 31.75333,-7.94362 2.46092,3.14447 2.46092,3.14447 14.63823,3.57192 14.34406,0.50352 12.8632,1.92639 13.76377,-13.22476 C 491.25698,363 491.25698,363 479.19658,363 c -12.06039,0 -12.06039,0 -14.10879,2.87671 -7.73614,10.86441 -27.51296,6.79134 -30.96214,-6.37671 -4.53297,-17.30563 17.67319,-28.71194 29.97074,-15.39462 C 467.69278,348 467.69278,348 480.2482,348 c 12.55543,0 12.55543,0 13.19694,-6.25 0.35284,-3.4375 0.89887,-8.6125 1.21341,-11.5 0.51943,-4.76831 0.38906,-5.25 -1.42088,-5.25 -2.76241,0 -3.87294,-1.72302 -4.6728,-7.25 C 487.89735,313.13749 487.77792,313 484.43873,313 478.87296,313 477,310.27849 477,302.19113 c 0,-8.37455 1.09788,-10.00477 7,-10.39421 4.5,-0.29692 4.5,-0.29692 5.08633,-4.69065 C 489.69185,282.56864 491.8985,280 495.19113,280 496.46004,280 497,279.24415 497,277.46788 497,269.12496 500.9512,266 511.5,266 c 10.5488,0 14.5,3.12496 14.5,11.46788 0,1.7598 0.54215,2.53212 1.77749,2.53212 3.2731,0 6.19357,2.96682 6.82839,6.93677 0.59007,3.69008 0.77674,3.84625 5.19228,4.34395 C 550.61336,292.49975 550.27937,312 539.4433,312 535,312 535,312 535,316.8 c 0,5.12772 -1.23962,7.2 -4.30699,7.2 -1.32808,0 -1.72272,0.48019 -1.3,1.58179 0.33385,0.86998 0.60699,4.01998 0.60699,7 C 530,338 530,338 543.78439,338 c 13.78439,0 13.78439,0 15.48504,-2.87896 5.87577,-9.94689 25.04698,-8.4034 30.18799,2.43047 8.1093,17.08909 -17.57808,32.82349 -29.28633,17.93887 -1.99139,-2.53164 -2.09855,-2.54762 -15.22765,-2.26998 C 531.72128,353.5 531.72128,353.5 531.92075,363.32487 532.19866,377.01277 531.07314,376 546.00699,376 558.8,376 558.8,376 562.2104,372.5896 c 14.31982,-14.31982 38.40475,2.73472 27.88959,19.74861 -5.87667,9.50865 -23.91466,10.42983 -29.00378,1.48119 C 559.5,391.01265 559.5,391.01265 546.43972,391.00632 533.37944,391 533.37944,391 533.81567,405.5 c 0.3039,10.10151 0.10418,14.5 -0.65842,14.5 -0.74212,0 -0.93208,2.49583 -0.58984,7.75 0.46047,7.06939 1.39682,37.93769 4.51215,148.75 0.60304,21.45 1.24803,39.51122 1.43332,40.13605 0.18529,0.62483 4.3082,1.67859 9.16201,2.3417 56.24753,7.68428 93.7669,43.139 96.9433,91.60862 1.11327,16.98759 -1.17055,30.37224 -10.13769,59.41363 -3.51241,11.37544 -3.142,18.18902 1.33403,24.53921 3.83072,5.43468 3.87337,6.16978 0.71912,12.39438 -2.46635,4.86711 -2.46635,4.86711 -7.64014,3.35046 -16.76357,-4.9141 -24.34107,6.81298 -24.3703,37.71595 -0.0517,54.6785 16.18944,86.31176 42.88225,83.52272 13.43512,-1.40378 12.68459,-1.59412 14.90394,3.77971 1.98336,4.80243 1.98336,4.80243 -0.41302,9.92577 -4.83836,10.34415 -3.89273,15.21502 9.65114,49.71213 39.04854,99.45927 -14.19503,171.31927 -132.32167,178.58767 -9.79399,0.6027 -11.41284,0.9529 -12.3305,2.6675 -2.14901,4.0155 -13.11942,6.3683 -22.28245,4.7788 z";

  var goldColors = [
    "#FFD700",
    "#E8D48B",
    "#C9A84C",
    "#FFFACD",
    "#F5EEDD",
    "#DAA520",
    "#FFE066",
  ];

  var noteCatalog = [
    { type: "whole", weight: 12 },
    { type: "half", weight: 16 },
    { type: "quarter", weight: 22 },
    { type: "eighth", weight: 16 },
    { type: "sixteenth", weight: 12 },
    { type: "beamed_two_eighths", weight: 10 },
    { type: "beamed_two_sixteenths", weight: 10 },
    { type: "combo_dotted_half_plus_sixteenth_beamed", weight: 6 },
    { type: "combo_half_plus_beamed_two_sixteenths", weight: 6 },
    { type: "combo_dotted_quarter_plus_beamed_two_eighths", weight: 8 },
  ];

  // Pre-calculate note weight total
  for (var wIdx = 0; wIdx < noteCatalog.length; wIdx++) {
    noteWeightTotal += noteCatalog[wIdx].weight;
  }

  // Pre-generate sin lookup table (360 entries for smooth twinkle)
  for (var sIdx = 0; sIdx < 360; sIdx++) {
    sinLookup[sIdx] = Math.sin((sIdx / 360) * PI2);
  }

  function ensureCanvas() {
    canvas = document.getElementById("glitter-canvas");
    if (!canvas) {
      canvas = document.createElement("canvas");
      canvas.id = "glitter-canvas";
      document.body.appendChild(canvas);
    }

    canvas.style.position = "fixed";
    canvas.style.top = "0";
    canvas.style.left = "0";
    canvas.style.width = "100vw";
    canvas.style.height = "100vh";
    canvas.style.pointerEvents = "none";
    canvas.style.zIndex = "9999";

    ctx = canvas.getContext("2d");
  }

  function ensureCursorDot() {
    if (!hasFinePointer) {
      return;
    }

    cursorDot = document.getElementById("glitter-cursor-dot");
    if (!cursorDot) {
      cursorDot = document.createElement("div");
      cursorDot.id = "glitter-cursor-dot";
      document.body.appendChild(cursorDot);
    }

    cursorDot.style.position = "fixed";
    cursorDot.style.left = "0";
    cursorDot.style.top = "0";
    cursorDot.style.width = "18px";
    cursorDot.style.height = "18px";
    cursorDot.style.borderRadius = "50%";
    cursorDot.style.background =
      "radial-gradient(circle, rgba(255,255,255,1) 0%, rgba(255,255,255,0.95) 30%, rgba(255,255,255,0.35) 62%, rgba(255,255,255,0) 100%)";
    cursorDot.style.boxShadow =
      "0 0 8px rgba(255,255,255,0.95), 0 0 18px rgba(255,255,255,0.8), 0 0 32px rgba(255,255,255,0.5)";
    cursorDot.style.pointerEvents = "none";
    cursorDot.style.zIndex = "10000";
    cursorDot.style.transform = "translate3d(-999px, -999px, 0)";

    document.documentElement.style.cursor = "none";
  }

  function getViewportSize() {
    var vv = window.visualViewport;
    var docEl = document.documentElement;

    var w = window.innerWidth || 0;
    var h = window.innerHeight || 0;

    if (docEl) {
      w = Math.max(w, docEl.clientWidth || 0);
      h = Math.max(h, docEl.clientHeight || 0);
    }

    // On mobile, browser chrome can make innerHeight unstable.
    if (vv) {
      w = Math.max(w, Math.floor(vv.width));
      h = Math.max(h, Math.floor(vv.height));
    }

    return {
      width: Math.max(1, w),
      height: Math.max(1, h),
    };
  }

  function resize() {
    var viewport = getViewportSize();
    width = viewport.width;
    height = viewport.height;
    canvas.width = Math.max(1, Math.floor(width * dpr));
    canvas.height = Math.max(1, Math.floor(height * dpr));
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    violinPlacement = getViolinPlacement();
  }

  function getViolinPlacement() {
    var isPhonePortrait = !hasFinePointer && height > width;
    var scaleToViewportHeight = VIOLIN_SCALE_TO_VIEWPORT_HEIGHT;
    if (isPhonePortrait) {
      scaleToViewportHeight *= MOBILE_VIOLIN_HEIGHT_SCALE_MULTIPLIER;
    }

    var targetHeight = height * Math.min(1.25, scaleToViewportHeight);
    var scale = targetHeight / VIOLIN_VIEWBOX_HEIGHT;
    var targetWidth = VIOLIN_VIEWBOX_WIDTH * scale;
    return {
      left: (width - targetWidth) * 0.5,
      top: (height - targetHeight) * 0.5,
      width: targetWidth,
      height: targetHeight,
      scale: scale,
      cx: width * 0.5,
      cy: height * 0.5,
    };
  }

  function screenPointToViolinPathSpace(x, y, placement) {
    return {
      x: (x - placement.left) / placement.scale + VIOLIN_GROUP_OFFSET_X,
      y: (y - placement.top) / placement.scale + VIOLIN_GROUP_OFFSET_Y,
    };
  }

  function isInsideViolinShape(x, y, placement) {
    if (!violinShapePath || !placement) {
      return false;
    }

    var point = screenPointToViolinPathSpace(x, y, placement);

    // Hit-testing with a neutral transform avoids mobile DPR scaling mismatches.
    ctx.save();
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    var inside = ctx.isPointInPath(violinShapePath, point.x, point.y);
    ctx.restore();
    return inside;
  }

  function samplePointInViolin(placement) {
    var x;
    var y;
    var tries = 0;

    while (tries < 240) {
      x = placement.left + Math.random() * placement.width;
      y = placement.top + Math.random() * placement.height;
      if (isInsideViolinShape(x, y, placement)) {
        return { x: x, y: y };
      }
      tries++;
    }

    return {
      x: placement.cx + (Math.random() - 0.5) * placement.width * 0.12,
      y: placement.cy + (Math.random() - 0.5) * placement.height * 0.12,
    };
  }

  function exponentialEase(progress, rate) {
    var p = Math.max(0, Math.min(1, progress));
    var r = Math.max(0.001, rate);
    var denom = Math.exp(r) - 1;
    return (Math.exp(r * p) - 1) / denom;
  }

  function introGrowthScale(progress) {
    var eased = exponentialEase(progress, INTRO_GROWTH_RATE);
    var startScale = INTRO_START_SCALE;
    var isPhonePortrait = !hasFinePointer && height > width;
    if (isPhonePortrait) {
      startScale = Math.max(INTRO_START_SCALE, MOBILE_INTRO_START_SCALE);
    }
    return startScale + (1 - startScale) * eased;
  }

  function isInsideViolinAtGrowth(x, y, placement, growthScale) {
    var g = Math.max(0.001, growthScale);
    var baseX = placement.cx + (x - placement.cx) / g;
    var baseY = placement.cy + (y - placement.cy) / g;
    return isInsideViolinShape(baseX, baseY, placement);
  }

  function drawDebugViolinOutline(placement, growthScale) {
    if (!DEBUG || !violinShapePath || !placement) {
      return;
    }

    var gs = Math.max(0.001, growthScale || 1);
    ctx.save();
    ctx.translate(placement.cx, placement.cy);
    ctx.scale(gs, gs);
    ctx.translate(-placement.cx, -placement.cy);
    ctx.translate(
      placement.left - VIOLIN_GROUP_OFFSET_X * placement.scale,
      placement.top - VIOLIN_GROUP_OFFSET_Y * placement.scale,
    );
    ctx.scale(placement.scale, placement.scale);
    ctx.strokeStyle = "#FF1A1A";
    ctx.lineWidth = Math.max(2.5 / placement.scale, 1.2);
    ctx.shadowColor = "rgba(255, 0, 0, 0.45)";
    ctx.shadowBlur = Math.max(4 / placement.scale, 2);
    ctx.stroke(violinShapePath);
    ctx.restore();
  }

  function drawIntroOverlayReveal(
    placement,
    growthScale,
    explodeElapsedMs,
    explosionFrontRadius,
  ) {
    var gs = Math.max(0.001, growthScale || INTRO_START_SCALE);
    var elapsed = Math.max(0, explodeElapsedMs || 0);
    var phaseOneProgress = Math.max(0, Math.min(1, elapsed / INTRO_EXPLODE_MS));
    var phaseTwoProgress = 0;

    if (elapsed > INTRO_EXPLODE_MS) {
      phaseTwoProgress = Math.max(
        0,
        Math.min(1, (elapsed - INTRO_EXPLODE_MS) / INTRO_REVEAL_EXTRA_MS),
      );
    }

    // Full opacity page-gradient overlay before explosion.
    var pageGradient = ctx.createLinearGradient(0, 0, width, height);
    pageGradient.addColorStop(0, "rgba(13, 13, 13, 1)");
    pageGradient.addColorStop(0.5, "rgba(26, 16, 5, 1)");
    pageGradient.addColorStop(1, "rgba(13, 13, 13, 1)");

    ctx.save();
    ctx.globalCompositeOperation = "source-over";
    ctx.fillStyle = pageGradient;
    ctx.fillRect(0, 0, width, height);

    // Circle already grows pre-explosion using the same growth curve as the violin.
    var preExplosionRadius = Math.max(14, placement.height * 0.5 * gs);
    var maxRadius = Math.sqrt(width * width + height * height) * 1.05;
    var phaseOneRadius =
      preExplosionRadius +
      (maxRadius - preExplosionRadius) * Math.pow(phaseOneProgress, 0.5);
    var timeRadius = phaseOneRadius;

    if (phaseTwoProgress > 0) {
      timeRadius += (maxRadius - preExplosionRadius) * 2 * phaseTwoProgress;
    }

    timeRadius *= GLOBAL_CIRCLE_RADIUS_SCALE;

    var radius = timeRadius;
    if (elapsed > 0) {
      var frontRadius =
        Math.max(0, explosionFrontRadius || 0) + INTRO_REVEAL_FRONT_MARGIN;
      radius =
        timeRadius * (1 - INTRO_REVEAL_FRONT_BLEND) +
        frontRadius * INTRO_REVEAL_FRONT_BLEND;
      radius = Math.max(
        radius,
        preExplosionRadius * GLOBAL_CIRCLE_RADIUS_SCALE,
      );
    }

    if (radius > 0.5) {
      var gradientLength = Math.max(
        0.05,
        Math.min(0.95, OVERLAY_GRADIENT_LENGTH),
      );
      var feather = Math.max(28, radius * gradientLength);
      var innerRadius = Math.max(0, radius - feather);
      var revealGradient = ctx.createRadialGradient(
        placement.cx,
        placement.cy,
        innerRadius,
        placement.cx,
        placement.cy,
        radius,
      );

      revealGradient.addColorStop(0, "rgba(0, 0, 0, 1)");
      revealGradient.addColorStop(0.2, "rgba(0, 0, 0, 0.82)");
      revealGradient.addColorStop(0.5, "rgba(0, 0, 0, 0.45)");
      revealGradient.addColorStop(0.8, "rgba(0, 0, 0, 0.16)");
      revealGradient.addColorStop(1, "rgba(0, 0, 0, 0)");

      ctx.globalCompositeOperation = "destination-out";
      ctx.fillStyle = revealGradient;
      ctx.beginPath();
      ctx.arc(placement.cx, placement.cy, radius, 0, PI2);
      ctx.fill();
    }

    ctx.restore();
  }

  function pickColor() {
    return goldColors[(Math.random() * goldColors.length) | 0];
  }

  function pickNoteType() {
    var r = Math.random() * noteWeightTotal;
    for (var j = 0; j < noteCatalog.length; j++) {
      r -= noteCatalog[j].weight;
      if (r <= 0) {
        return noteCatalog[j].type;
      }
    }
    return "quarter";
  }

  function makeParticle(base) {
    base.symbolType = pickNoteType();
    base.rotation = (Math.random() - 0.5) * 0.6;
    return base;
  }

  function seedAmbient() {
    ambientParticles = [];
    var count = Math.max(
      12,
      Math.min(80, Math.floor((width * height) / (32000 / strength))),
    );
    for (var i = 0; i < count; i++) {
      ambientParticles.push(
        makeParticle({
          x: Math.random() * width,
          y: Math.random() * height,
          r: (Math.random() * 2 + 0.7) * (0.9 + strength * 0.25),
          color: pickColor(),
          alpha: Math.random() * 0.35 + 0.08,
          twinkle: Math.random() * 0.05 + 0.015,
          phase: (Math.random() * 360) | 0,
          vx: (Math.random() - 0.5) * 0.08,
          vy: Math.random() * 0.12 + 0.03,
        }),
      );
    }
  }

  function spawnIntroBurst() {
    introParticles = [];
    introOuterParticles = [];

    var count = Math.max(
      420,
      Math.min(1700, Math.floor((width * height) / (1 / strength))),
    );
    var outerCount = Math.max(
      26,
      Math.min(110, Math.floor((width * height) / (18500 / strength))),
    );
    var placement = violinPlacement || getViolinPlacement();

    if (!violinShapePath) {
      if (typeof Path2D === "function") {
        violinShapePath = new Path2D(VIOLIN_PATH_D);
      } else {
        return;
      }
    }

    for (var i = 0; i < count; i++) {
      var pt = samplePointInViolin(placement);
      var pathPt = screenPointToViolinPathSpace(pt.x, pt.y, placement);
      introParticles.push(
        makeParticle({
          anchorX: pt.x,
          anchorY: pt.y,
          pathX: pathPt.x,
          pathY: pathPt.y,
          x: pt.x,
          y: pt.y,
          vx: 0,
          vy: 0,
          r: (Math.random() * 2.7 + 0.8) * (0.9 + strength * 0.2),
          color: pickColor(),
          alpha: Math.random() * 0.5 + 0.25,
          life: Math.random() * 140 + 140,
          decay: Math.random() * 0.007 + 0.004,
          gravity: Math.random() * 0.013 + 0.004,
          jitterPhase: Math.random() * PI2,
          jitterSpeed: Math.random() * 0.012 + 0.01,
          motionAmp: Math.random() * 2.4 + 0.7,
          motionAmpY: Math.random() * 2.4 + 0.7,
          motionRateX: Math.random() * 0.02 + 0.014,
          motionRateY: Math.random() * 0.02 + 0.016,
          exploded: false,
        }),
      );
    }

    for (i = 0; i < outerCount; i++) {
      var ox;
      var oy;
      var tries = 0;
      do {
        ox = Math.random() * width;
        oy = Math.random() * height;
        tries++;
      } while (tries < 120 && isInsideViolinShape(ox, oy, placement));

      introOuterParticles.push(
        makeParticle({
          x: ox,
          y: oy,
          baseX: ox,
          baseY: oy,
          r: (Math.random() * 1.7 + 0.55) * (0.85 + strength * 0.15),
          color: pickColor(),
          alpha: Math.random() * 0.25 + 0.08,
          phase: Math.random() * PI2,
          driftX: (Math.random() - 0.5) * 0.22,
          driftY: (Math.random() - 0.5) * 0.18,
          pulseSpeed: Math.random() * 0.012 + 0.006,
        }),
      );
    }

    introStartedAt = performance.now();
    introExplosionStarted = false;
    introExplosionAt = 0;
    introExplosionFrontRadius = 0;
  }

  function runIntroSequence(now, frameScale) {
    if (!introStartedAt) {
      return;
    }

    var elapsed = now - introStartedAt;
    var placement = violinPlacement || getViolinPlacement();
    var holdProgress = Math.max(0, Math.min(1, elapsed / INTRO_HOLD_MS));
    var growthScale = introGrowthScale(holdProgress);
    var i;
    var p;
    var maxDim = Math.max(width, height);

    var explodeElapsed = introExplosionStarted ? now - introExplosionAt : 0;
    var revealDuration = INTRO_EXPLODE_MS + INTRO_REVEAL_EXTRA_MS;

    if (elapsed >= INTRO_HOLD_MS && !introExplosionStarted) {
      introExplosionStarted = true;
      introExplosionAt = now;
      introExplosionFrontRadius = 0;
      explodeElapsed = 0;
      for (i = 0; i < introParticles.length; i++) {
        p = introParticles[i];
        var dx = p.x - placement.cx;
        var dy = p.y - placement.cy;
        var dist = Math.max(12, Math.sqrt(dx * dx + dy * dy));
        var nx = dx / dist;
        var ny = dy / dist;
        var radialBoost = 4.2 + Math.random() * 8.1 + (dist / maxDim) * 6.6;
        p.vx = nx * radialBoost + (Math.random() - 0.5) * 4.2;
        p.vy = ny * radialBoost + (Math.random() - 0.5) * 4.0;
        p.exploded = true;
      }
    }

    if (!introExplosionStarted || explodeElapsed < revealDuration) {
      drawIntroOverlayReveal(
        placement,
        growthScale,
        explodeElapsed,
        introExplosionFrontRadius,
      );
    }

    drawDebugViolinOutline(placement, growthScale);

    var frameStep = Math.max(0.2, frameScale || 1);
    var currentFront = 0;
    for (i = 0; i < introParticles.length; i++) {
      p = introParticles[i];

      if (!p.exploded) {
        var baseX;
        var baseY;
        if (typeof p.pathX === "number" && typeof p.pathY === "number") {
          baseX =
            (p.pathX - VIOLIN_GROUP_OFFSET_X) * placement.scale +
            placement.left;
          baseY =
            (p.pathY - VIOLIN_GROUP_OFFSET_Y) * placement.scale + placement.top;
        } else {
          // Fallback for particles created before path-space mapping existed.
          baseX = p.anchorX;
          baseY = p.anchorY;
        }

        var grownX = placement.cx + (baseX - placement.cx) * growthScale;
        var grownY = placement.cy + (baseY - placement.cy) * growthScale;
        var motionX =
          Math.sin(now * p.motionRateX + p.jitterPhase) * p.motionAmp;
        var motionY =
          Math.cos(now * p.motionRateY + p.jitterPhase * 1.23) * p.motionAmpY;
        var candidateX = grownX + motionX;
        var candidateY = grownY + motionY;

        if (
          isInsideViolinAtGrowth(candidateX, candidateY, placement, growthScale)
        ) {
          p.x = candidateX;
          p.y = candidateY;
        } else {
          p.x = grownX;
          p.y = grownY;
        }
      } else {
        p.x += p.vx * frameStep;
        p.y += p.vy * frameStep;
        p.vy += p.gravity * frameStep;
        p.vx *= Math.pow(0.994, frameStep);
        p.alpha -= p.decay * frameStep;

        if (p.alpha > 0.02) {
          var fdx = p.x - placement.cx;
          var fdy = p.y - placement.cy;
          var distFront = Math.sqrt(fdx * fdx + fdy * fdy);
          if (distFront > currentFront) {
            currentFront = distFront;
          }
        }
      }

      if (p.alpha > 0.02) {
        drawParticleFn(p, p.alpha);
      }
    }

    if (currentFront > introExplosionFrontRadius) {
      introExplosionFrontRadius = currentFront;
    }

    var introLive = 0;
    for (i = 0; i < introParticles.length; i++) {
      p = introParticles[i];
      if (p.alpha > 0.02) {
        introParticles[introLive] = p;
        introLive++;
      }
    }
    introParticles.length = introLive;
  }

  function spawnTrail(x, y, amount) {
    var count = Math.max(1, Math.floor((amount || 3) * strength * 0.6));
    for (var i = 0; i < count; i++) {
      trailParticles.push(
        makeParticle({
          x: x + (Math.random() - 0.5) * 20,
          y: y + (Math.random() - 0.5) * 20,
          vx: (Math.random() - 0.5) * 1.6,
          vy: (Math.random() - 0.7) * 1.4,
          r: (Math.random() * 4.2 + 0.6) * (0.8 + strength * 0.35),
          color: pickColor(),
          alpha: Math.random() * 0.45 + 0.35,
          life: Math.random() * 220 + 28,
          decay: Math.random() * 0.009 + 0.012,
          gravity: Math.random() * 0.0018 + 0.0035,
        }),
      );
    }

    var maxTrail = Math.floor(240 + 280 * strength);
    if (trailParticles.length > maxTrail) {
      trailParticles.length = maxTrail;
    }
  }

  function onPointerMove(x, y) {
    pointerX = x;
    pointerY = y;
    var now = performance.now();
    if (now - lastMove > 16) {
      spawnTrail(x, y, 3);
      lastMove = now;
    }

    if (cursorDot && now - lastCursorUpdate > 10) {
      cursorDot.style.transform =
        "translate3d(" +
        Math.round(x - 9) +
        "px, " +
        Math.round(y - 9) +
        "px, 0)";
      lastCursorUpdate = now;
    }
  }

  function bindInput() {
    window.addEventListener(
      "mousemove",
      function (event) {
        onPointerMove(event.clientX, event.clientY);
      },
      { passive: true },
    );

    window.addEventListener(
      "touchstart",
      function (event) {
        for (var i = 0; i < event.touches.length; i++) {
          var t = event.touches[i];
          spawnTrail(t.clientX, t.clientY, 8);
          onPointerMove(t.clientX, t.clientY);
        }
      },
      { passive: true },
    );

    window.addEventListener(
      "touchmove",
      function (event) {
        for (var i = 0; i < event.touches.length; i++) {
          var t = event.touches[i];
          spawnTrail(t.clientX, t.clientY, 6);
          onPointerMove(t.clientX, t.clientY);
        }
      },
      { passive: true },
    );
  }

  function drawCircleParticle(p, alpha) {
    ctx.fillStyle = p.color;
    ctx.globalAlpha = alpha;
    ctx.beginPath();
    ctx.arc(p.x, p.y, p.r, 0, PI2);
    ctx.fill();
  }

  function drawNoteHead(size, filled) {
    ctx.beginPath();
    ctx.ellipse(0, 0, size * 1.05, size * 0.75, -0.35, 0, PI2);
    if (filled) {
      ctx.fill();
    } else {
      ctx.stroke();
    }
  }

  function drawStem(size, up) {
    var stemX = up ? size * 0.95 : -size * 0.95;
    var topY = up ? -size * 4.0 : size * 4.0;
    ctx.beginPath();
    ctx.moveTo(stemX, 0);
    ctx.lineTo(stemX, topY);
    ctx.stroke();
    return { stemX: stemX, topY: topY };
  }

  function drawFlag(stemX, topY, size, order) {
    var y = topY + order * size * 0.95;
    ctx.beginPath();
    ctx.moveTo(stemX, y);
    ctx.quadraticCurveTo(
      stemX + size * 1.6,
      y + size * 0.4,
      stemX + size * 0.5,
      y + size * 1.7,
    );
    ctx.stroke();
  }

  function drawBeam(x1, y1, x2, y2, thickness) {
    ctx.beginPath();
    ctx.moveTo(x1, y1);
    ctx.lineTo(x2, y2);
    ctx.lineTo(x2, y2 + thickness);
    ctx.lineTo(x1, y1 + thickness);
    ctx.closePath();
    ctx.fill();
  }

  function drawSingleNote(type, size) {
    if (type === "whole") {
      drawNoteHead(size * 1.1, false);
      return;
    }

    var hollow = type === "half";
    drawNoteHead(size, !hollow);
    var stem = drawStem(size, true);

    if (type === "eighth") {
      drawFlag(stem.stemX, stem.topY, size, 0);
    } else if (type === "sixteenth") {
      drawFlag(stem.stemX, stem.topY, size, 0);
      drawFlag(stem.stemX, stem.topY, size, 1);
    }
  }

  function drawTwoBeamed(size, sixteenthBeams) {
    var xL = -size * 1.5;
    var xR = size * 1.5;

    ctx.save();
    ctx.translate(xL, 0.6 * size);
    drawNoteHead(size * 0.95, true);
    ctx.restore();

    ctx.save();
    ctx.translate(xR, -0.3 * size);
    drawNoteHead(size * 0.95, true);
    ctx.restore();

    var stemLx = xL + size * 0.9;
    var stemRx = xR + size * 0.9;
    var topLy = -size * 3.5;
    var topRy = -size * 4.1;

    ctx.beginPath();
    ctx.moveTo(stemLx, 0.6 * size);
    ctx.lineTo(stemLx, topLy);
    ctx.moveTo(stemRx, -0.3 * size);
    ctx.lineTo(stemRx, topRy);
    ctx.stroke();

    drawBeam(stemLx, topLy, stemRx, topRy, Math.max(1.2, size * 0.34));
    if (sixteenthBeams) {
      drawBeam(
        stemLx,
        topLy + size * 0.72,
        stemRx,
        topRy + size * 0.72,
        Math.max(1.1, size * 0.3),
      );
    }
  }

  function drawCombo(type, size) {
    var leftX = -size * 1.7;
    var rightX = size * 1.7;

    if (type === "combo_dotted_half_plus_sixteenth_beamed") {
      ctx.save();
      ctx.translate(leftX, 0.2 * size);
      drawNoteHead(size, false);
      var leftStem = drawStem(size, true);
      ctx.beginPath();
      ctx.arc(size * 1.7, -0.25 * size, size * 0.18, 0, PI2);
      ctx.fill();
      ctx.restore();

      ctx.save();
      ctx.translate(rightX, -0.15 * size);
      drawNoteHead(size * 0.95, true);
      var rightStem = drawStem(size * 0.95, true);
      ctx.restore();

      var x1 = leftX + leftStem.stemX;
      var y1 = leftStem.topY;
      var x2 = rightX + rightStem.stemX;
      var y2 = rightStem.topY - size * 0.5;
      drawBeam(x1, y1, x2, y2, Math.max(1.2, size * 0.33));
      drawBeam(
        x1,
        y1 + size * 0.7,
        x2,
        y2 + size * 0.7,
        Math.max(1.1, size * 0.28),
      );
      return;
    }

    if (type === "combo_half_plus_beamed_two_sixteenths") {
      ctx.save();
      ctx.translate(leftX - size * 0.4, 0.2 * size);
      drawNoteHead(size * 1.05, false);
      drawStem(size * 1.05, true);
      ctx.restore();

      ctx.save();
      ctx.translate(rightX, 0);
      drawTwoBeamed(size * 0.86, true);
      ctx.restore();
      return;
    }

    if (type === "combo_dotted_quarter_plus_beamed_two_eighths") {
      ctx.save();
      ctx.translate(leftX, 0.2 * size);
      drawNoteHead(size, true);
      drawStem(size, true);
      ctx.beginPath();
      ctx.arc(size * 1.7, -0.25 * size, size * 0.18, 0, PI2);
      ctx.fill();
      ctx.restore();

      ctx.save();
      ctx.translate(rightX, 0);
      drawTwoBeamed(size * 0.86, false);
      ctx.restore();
    }
  }

  function drawMusicalParticle(p, alpha) {
    var size = Math.max(1.2, p.r);

    ctx.save();
    ctx.translate(p.x, p.y);
    ctx.rotate(p.rotation || 0);
    ctx.globalAlpha = alpha;
    ctx.fillStyle = p.color;
    ctx.strokeStyle = p.color;
    ctx.lineWidth = Math.max(1.1, size * 0.2);
    ctx.lineCap = "round";
    ctx.lineJoin = "round";

    var t = p.symbolType;
    if (
      t === "whole" ||
      t === "half" ||
      t === "quarter" ||
      t === "eighth" ||
      t === "sixteenth"
    ) {
      drawSingleNote(t, size);
    } else if (t === "beamed_two_eighths") {
      drawTwoBeamed(size, false);
    } else if (t === "beamed_two_sixteenths") {
      drawTwoBeamed(size, true);
    } else if (
      t === "combo_dotted_half_plus_sixteenth_beamed" ||
      t === "combo_half_plus_beamed_two_sixteenths" ||
      t === "combo_dotted_quarter_plus_beamed_two_eighths"
    ) {
      drawCombo(t, size);
    } else {
      ctx.beginPath();
      ctx.arc(0, 0, size, 0, PI2);
      ctx.fill();
    }

    ctx.restore();
  }

  function step(now) {
    ctx.clearRect(0, 0, width, height);

    if (!lastFrameAt) {
      lastFrameAt = now;
    }
    var dt = now - lastFrameAt;
    lastFrameAt = now;
    if (dt < 1) {
      dt = 16.666;
    }
    if (dt > 50) {
      dt = 50;
    }
    var frameScale = dt / 16.666;

    var i, a, j, p, twinkle, liveCount, sinIdx;
    var glitterDelayElapsed =
      introStartedAt && now - introStartedAt >= GLITTER_DELAY;

    if (glitterDelayElapsed) {
      // Update ambient particles
      for (i = 0; i < ambientParticles.length; i++) {
        a = ambientParticles[i];
        a.x += a.vx;
        a.y += a.vy;

        if (a.y > height + 8) {
          a.y = -8;
          a.x = Math.random() * width;
        }
        if (a.x < -8 || a.x > width + 8) {
          a.x = a.x < -8 ? width + 8 : -8;
        }
      }
    }

    // Draw the intro violin hold-and-explode sequence
    runIntroSequence(now, frameScale);

    // Draw normal ambient above the intro overlay once delay has elapsed.
    if (glitterDelayElapsed) {
      for (i = 0; i < ambientParticles.length; i++) {
        a = ambientParticles[i];
        sinIdx = ((now * a.twinkle * 0.05 + a.phase) % 360) | 0;
        twinkle = (sinLookup[sinIdx] + 1) * 0.5;
        drawParticleFn(a, a.alpha * (0.35 + twinkle * 0.65));
      }
    }

    // Draw trail particles with efficient removal
    liveCount = 0;
    for (i = 0; i < trailParticles.length; i++) {
      p = trailParticles[i];
      p.x += p.vx;
      p.y += p.vy;
      p.vy += p.gravity;
      p.alpha -= p.decay;
      p.life -= 1;

      if (p.alpha > 0 && p.life > 0) {
        drawParticleFn(p, p.alpha);
        trailParticles[liveCount] = p;
        liveCount++;
      }
    }
    trailParticles.length = liveCount;

    requestAnimationFrame(step);
  }

  function init() {
    ensureCanvas();
    ensureCursorDot();
    if (typeof Path2D === "function") {
      violinShapePath = new Path2D(VIOLIN_PATH_D);
    }
    resize();

    // Bind draw function at init time to avoid branching in loop
    if (PARTICLE_STYLE === "circles") {
      drawParticleFn = drawCircleParticle;
    } else {
      drawParticleFn = drawMusicalParticle;
    }

    seedAmbient();
    spawnIntroBurst();
    bindInput();
    requestAnimationFrame(step);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }

  window.addEventListener("resize", function () {
    resize();
    seedAmbient();
    if (!introExplosionStarted && introParticles.length === 0) {
      spawnIntroBurst();
    }
  });

  if (window.visualViewport) {
    window.visualViewport.addEventListener(
      "resize",
      function () {
        resize();
        if (!introExplosionStarted && introParticles.length === 0) {
          spawnIntroBurst();
        }
      },
      { passive: true },
    );

    window.visualViewport.addEventListener(
      "scroll",
      function () {
        resize();
      },
      { passive: true },
    );
  }
})();
