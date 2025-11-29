/**
 * Draw primitives shared by timelapse player and editor
 */
import { hexToRgb } from './color_utils.js';

export function drawStrokePrimitive(ctx, frame, layerStates = {}) {
    if (!frame.path || frame.path.length === 0) return;

    // Check layer visibility only - do NOT apply layer opacity here
    // Layer opacity is applied during compositing in TimelapsePlayer.compositeToMain()
    if (typeof frame.layer !== 'undefined') {
        const li = Number(frame.layer);
        const st = layerStates[li];
        if (st && st.visible === false) return;
    }

    ctx.save();

    if (frame.tool === 'watercolor') {
        // Tuning constants — tweak these if playback looks too thin or faint
        const WATER_PRESSURE_BASE = 0.6; // baseline multiplier for radius when pressure is 0
        const WATER_PRESSURE_SCALE = 0.4; // additional multiplier scaling by pressure
        const CONNECTOR_WIDTH_MULT = 2.2; // connector stroke width multiplier (was 1.6)
        const CONNECTOR_ALPHA_MULT = 0.7; // alpha multiplier for connector stroke (was 0.55)

        const maxRadius = (frame.size || 40) / 2;
        const rawHardness = (frame.watercolorHardness !== undefined ? Number(frame.watercolorHardness) : 50);
        const hardness = Number.isFinite(rawHardness) ? Math.min(Math.max(rawHardness / 100, 0), 1) : 0.5;
        const rawBaseOpacity = (frame.watercolorOpacity !== undefined ? Number(frame.watercolorOpacity) : 0.3);
        const baseOpacity = Number.isFinite(rawBaseOpacity) ? Math.min(Math.max(rawBaseOpacity, 0), 1) : 0.3;
        const rawOpacityMultiplier = (frame.opacity !== undefined ? Number(frame.opacity) : 1);
        const opacityMultiplier = Number.isFinite(rawOpacityMultiplier) ? Math.min(Math.max(rawOpacityMultiplier, 0), 1) : 1;
            const effectiveBaseOpacity = baseOpacity * opacityMultiplier;
            const SINGLE_POINT_MIN_OPACITY = 0.025; // perceptual minimum for single taps
            const usedBaseOpacity = (frame.path && frame.path.length <= 1)
                ? Math.max(effectiveBaseOpacity, SINGLE_POINT_MIN_OPACITY)
                : effectiveBaseOpacity;

        // Debug info when enabled in browser
        try {
            if (typeof window !== 'undefined' && window.DEBUG_TIMELAPSE) {
                console.debug('[drawStrokePrimitive] watercolor frame', { size: frame.size, baseOpacity, opacityMultiplier, effectiveBaseOpacity, hardness, tool: frame.tool });
            }
        } catch (e) {}

        const colorRgb = hexToRgb(frame.color || '#000000');
        if (!colorRgb) {
            ctx.restore();
            return;
        }

        // Estimate average spacing between samples so we can reduce per-sample
        // alpha when samples are dense. Use a radius-relative heuristic so that
        // the scale reflects how much fills overlap: overlaps ≈ (2*R)/spacing.
        // Therefore per-sample alpha ~ spacing / (2*R).
        //
        // IMPORTANT: For watercolor brush, we DO NOT apply density-based opacity
        // scaling because live drawing renders each sample individually (path.length=1)
        // without density adjustment. To match live rendering, timelapse playback
        // must also skip density scaling for watercolor strokes.
        let perSampleScale = 1;
        let avgSpacing = null;
        // Skip density scaling for watercolor to match live drawing behavior
        if (frame.path.length > 1 && frame.tool !== 'watercolor') {
            let totalDist = 0;
            for (let i = 1; i < frame.path.length; i++) {
                const a = frame.path[i - 1];
                const b = frame.path[i];
                const dx = b.x - a.x;
                const dy = b.y - a.y;
                totalDist += Math.sqrt(dx * dx + dy * dy);
            }
            avgSpacing = totalDist / (frame.path.length - 1);
            const diameter = Math.max(1, maxRadius * 2);
            perSampleScale = Math.min(1, avgSpacing / diameter);
            perSampleScale = Math.max(perSampleScale, 0.05);
        }

        // Debug density information for tuning
        try {
            if (typeof window !== 'undefined' && window.DEBUG_TIMELAPSE) {
                console.debug('[drawStrokePrimitive] density', { avgSpacing, perSampleScale, maxRadius, pathLen: frame.path.length });
            }
        } catch (e) {}

        for (let i = 0; i < frame.path.length; i++) {
            const pt = frame.path[i];
            const pressure = (pt.pressure !== undefined ? pt.pressure : 1);
            const pressuredRadius = maxRadius * (WATER_PRESSURE_BASE + WATER_PRESSURE_SCALE * pressure);

            const sampleOpacity = usedBaseOpacity * perSampleScale;

            const gradient = ctx.createRadialGradient(pt.x, pt.y, 0, pt.x, pt.y, pressuredRadius);
            const solidStop = hardness * 0.8;

            gradient.addColorStop(0, `rgba(${colorRgb.r}, ${colorRgb.g}, ${colorRgb.b}, ${sampleOpacity})`);
            if (solidStop > 0) {
                gradient.addColorStop(solidStop, `rgba(${colorRgb.r}, ${colorRgb.g}, ${colorRgb.b}, ${sampleOpacity})`);
            }

            const midStop = solidStop + (1 - solidStop) * 0.5;
            const midOpacity = sampleOpacity * 0.3;
            gradient.addColorStop(midStop, `rgba(${colorRgb.r}, ${colorRgb.g}, ${colorRgb.b}, ${midOpacity})`);
            gradient.addColorStop(1, `rgba(${colorRgb.r}, ${colorRgb.g}, ${colorRgb.b}, 0)`);

            ctx.fillStyle = gradient;
            ctx.globalCompositeOperation = 'source-over';
            ctx.beginPath();
            ctx.arc(pt.x, pt.y, pressuredRadius, 0, Math.PI * 2);
            ctx.fill();
        }

        if (frame.path.length > 1) {
            try {
                ctx.save();
                ctx.globalCompositeOperation = 'source-over';
                    const connectorAlpha = Math.max(0.06, Math.min(0.95, effectiveBaseOpacity * CONNECTOR_ALPHA_MULT * perSampleScale));
                    ctx.strokeStyle = `rgba(${colorRgb.r}, ${colorRgb.g}, ${colorRgb.b}, ${connectorAlpha})`;
                ctx.lineCap = 'round';
                ctx.lineJoin = 'round';

                // Draw connector segments using per-point pressure so playback
                // better matches live rendering where width varies by pressure.
                for (let i = 1; i < frame.path.length; i++) {
                    const p0 = frame.path[i - 1];
                    const p1 = frame.path[i];
                    const pr0 = (p0.pressure !== undefined ? p0.pressure : 1);
                    const pr1 = (p1.pressure !== undefined ? p1.pressure : 1);
                    const avgRadius = ((maxRadius * (WATER_PRESSURE_BASE + WATER_PRESSURE_SCALE * pr0)) + (maxRadius * (WATER_PRESSURE_BASE + WATER_PRESSURE_SCALE * pr1))) / 2;
                    const lineW = Math.max(1, avgRadius * CONNECTOR_WIDTH_MULT);
                    ctx.lineWidth = lineW;
                    ctx.beginPath();
                    ctx.moveTo(p0.x, p0.y);
                    ctx.lineTo(p1.x, p1.y);
                    ctx.stroke();
                }
            } catch (e) {
                console.warn('Connector stroke render failed:', e);
            } finally {
                ctx.restore();
            }
        }
    } else {
        ctx.strokeStyle = frame.color || '#000000';
        ctx.lineWidth = frame.size || 5;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.globalAlpha = frame.opacity !== undefined ? frame.opacity : 1;

        // Debug log for pen/eraser frames
        try {
            if (typeof window !== 'undefined' && window.DEBUG_TIMELAPSE) {
                const pressures = (frame.path || []).slice(0, 5).map(p => p.pressure !== undefined ? p.pressure : null);
                console.debug('[drawStrokePrimitive] non-water frame', { tool: frame.tool, size: frame.size, opacity: frame.opacity, pressures });
            }
        } catch (e) {}

        if (frame.tool === 'eraser') {
            ctx.globalCompositeOperation = 'destination-out';
        } else {
            ctx.globalCompositeOperation = 'source-over';
        }

        if (frame.path.length === 1) {
            const p = frame.path[0];
            const baseSize = frame.size || 5;
            const r = (baseSize) / 2 * (p.pressure !== undefined ? (0.3 + 0.7 * p.pressure) : 1);
            ctx.beginPath();
            ctx.arc(p.x, p.y, Math.max(1, r), 0, Math.PI * 2);
            if (frame.tool === 'eraser') {
                ctx.globalCompositeOperation = 'destination-out';
                ctx.fill();
            } else {
                ctx.fillStyle = frame.color || ctx.strokeStyle;
                ctx.fill();
            }
        } else {
            // Draw each segment with width influenced by per-point pressure so
            // playback approximates live pressure-sensitive strokes.
            for (let i = 1; i < frame.path.length; i++) {
                const p0 = frame.path[i - 1];
                const p1 = frame.path[i];
                const pr0 = (p0.pressure !== undefined ? p0.pressure : 1);
                const pr1 = (p1.pressure !== undefined ? p1.pressure : 1);
                const avgPressure = (pr0 + pr1) / 2;
                const baseSize = frame.size || 5;
                const width = Math.max(1, baseSize * (0.3 + 0.7 * avgPressure));
                ctx.lineWidth = width;
                ctx.beginPath();
                ctx.moveTo(p0.x, p0.y);
                ctx.lineTo(p1.x, p1.y);
                ctx.stroke();
            }
        }
    }

    ctx.restore();
}

export function drawFillPrimitive(ctx, frame, canvasWidth, canvasHeight, layerStates = {}, options = {}) {
    if (frame.x === undefined || frame.y === undefined) return;
    // Check layer visibility only - do NOT apply layer opacity here
    // Layer opacity is applied during compositing in TimelapsePlayer.compositeToMain()
    if (typeof frame.layer !== 'undefined') {
        const li = Number(frame.layer);
        const st = layerStates[li];
        if (st && st.visible === false) return;
    }

    // Flood fill implementation that operates on the provided context
    const imageData = ctx.getImageData(0, 0, canvasWidth, canvasHeight);
    const data = imageData.data;

    const startX = Math.floor(frame.x);
    const startY = Math.floor(frame.y);

    if (startX < 0 || startX >= canvasWidth || startY < 0 || startY >= canvasHeight) {
        return;
    }

    const startPos = (startY * canvasWidth + startX) * 4;
    const targetR = data[startPos];
    const targetG = data[startPos + 1];
    const targetB = data[startPos + 2];
    const targetA = data[startPos + 3];

    const fillColor = frame.color || '#000000';
    let fillR, fillG, fillB;
    if (fillColor.startsWith('#')) {
        const hex = fillColor.substring(1);
        fillR = parseInt(hex.substring(0, 2), 16);
        fillG = parseInt(hex.substring(2, 4), 16);
        fillB = parseInt(hex.substring(4, 6), 16);
    } else {
        fillR = fillG = fillB = 0;
    }

    // tolerance: allow slight RGB differences (used by editor bucket tool)
    const tolerance = Number(options.tolerance) || 0;
    function colorMatchLocal(c1, c2, tol) {
        if (!c2) return false;
        return Math.abs(c1.r - c2.r) <= tol &&
            Math.abs(c1.g - c2.g) <= tol &&
            Math.abs(c1.b - c2.b) <= tol;
    }

    if (targetA === 255 && colorMatchLocal({ r: targetR, g: targetG, b: targetB }, { r: fillR, g: fillG, b: fillB }, tolerance)) {
        return;
    }

    const stack = [[startX, startY]];
    const visited = new Set();

    while (stack.length > 0) {
        const [x, y] = stack.pop();
        if (x < 0 || x >= canvasWidth || y < 0 || y >= canvasHeight) continue;
        const key = `${x},${y}`;
        if (visited.has(key)) continue;
        visited.add(key);

        const pos = (y * canvasWidth + x) * 4;
    const current = { r: data[pos], g: data[pos + 1], b: data[pos + 2], a: data[pos + 3] };
    if (!colorMatchLocal(current, { r: targetR, g: targetG, b: targetB }, tolerance)) continue;

        data[pos] = fillR;
        data[pos + 1] = fillG;
        data[pos + 2] = fillB;
        data[pos + 3] = 255;

        stack.push([x + 1, y]);
        stack.push([x - 1, y]);
        stack.push([x, y + 1]);
        stack.push([x, y - 1]);
    }

    ctx.putImageData(imageData, 0, 0);
}
