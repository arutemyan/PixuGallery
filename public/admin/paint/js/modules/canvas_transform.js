/**
 * Canvas Transform Module
 * Handles zoom, pan, rotate, flip, and resize operations
 */

import { state, elements } from './state.js';

/**
 * Zoom in/out by delta
 * @param {number} delta - Amount to zoom (positive = in, negative = out)
 * @param {Function} setStatus - Callback to update status bar
 */
export function zoom(delta, setStatus) {
    state.zoomLevel = Math.max(0.25, Math.min(4, state.zoomLevel + delta));
    applyZoom();
    setStatus(`ズーム: ${Math.round(state.zoomLevel * 100)}%`);
}

/**
 * Reset zoom to 1:1
 */
export function zoomFit() {
    state.zoomLevel = 1;
    applyZoom();
}

/**
 * Apply current zoom level to canvas
 */
function applyZoom() {
    const scale = state.zoomLevel;
    const transform = `scale(${scale}) translate(${state.panOffset.x / scale}px, ${state.panOffset.y / scale}px)`;
    elements.canvasWrap.style.transform = transform;
}

/**
 * Apply current pan offset to canvas
 */
function applyPan() {
    const transform = `scale(${state.zoomLevel}) translate(${state.panOffset.x / state.zoomLevel}px, ${state.panOffset.y / state.zoomLevel}px)`;
    elements.canvasWrap.style.transform = transform;
}

/**
 * Initialize canvas panning with spacebar
 * @param {Function} setTool - Callback to set current tool
 */
export function initCanvasPan(setTool) {
    document.addEventListener('keydown', (e) => {
        if (e.code === 'Space' && !state.spaceKeyPressed) {
            state.spaceKeyPressed = true;
            state.layers.forEach(canvas => {
                canvas.style.cursor = 'grab';
            });
        }
    });

    document.addEventListener('keyup', (e) => {
        if (e.code === 'Space') {
            state.spaceKeyPressed = false;
            state.isPanning = false;
            setTool(state.currentTool); // Restore cursor
        }
    });

    elements.canvasWrap.addEventListener('mousedown', (e) => {
        if (state.spaceKeyPressed) {
            state.isPanning = true;
            state.panStart = { x: e.clientX, y: e.clientY };
            state.layers.forEach(canvas => {
                canvas.style.cursor = 'grabbing';
            });
            e.preventDefault();
        }
    });

    document.addEventListener('mousemove', (e) => {
        if (state.isPanning && state.spaceKeyPressed) {
            const dx = e.clientX - state.panStart.x;
            const dy = e.clientY - state.panStart.y;

            state.panOffset.x += dx;
            state.panOffset.y += dy;
            state.panStart = { x: e.clientX, y: e.clientY };

            applyPan();
            e.preventDefault();
        }
    });

    document.addEventListener('mouseup', () => {
        if (state.isPanning) {
            state.isPanning = false;
            if (state.spaceKeyPressed) {
                state.layers.forEach(canvas => {
                    canvas.style.cursor = 'grab';
                });
            }
        }
    });
}

/**
 * Initialize zoom and transform tools
 * @param {Function} setStatus - Callback to update status bar
 * @param {Function} pushUndo - Callback to push undo state
 */
export function initTransformTools(setStatus, pushUndo) {
    // Zoom controls
    if (elements.toolZoomIn) {
        elements.toolZoomIn.addEventListener('click', () => zoom(0.25, setStatus));
    }

    if (elements.toolZoomOut) {
        elements.toolZoomOut.addEventListener('click', () => zoom(-0.25, setStatus));
    }

    if (elements.toolZoomFit) {
        elements.toolZoomFit.addEventListener('click', zoomFit);
    }

    // Rotation and flip
    if (elements.toolRotateCW) {
        elements.toolRotateCW.addEventListener('click', () => rotateCanvas(90, setStatus, pushUndo));
    }

    if (elements.toolRotateCCW) {
        elements.toolRotateCCW.addEventListener('click', () => rotateCanvas(-90, setStatus, pushUndo));
    }

    if (elements.toolFlipH) {
        elements.toolFlipH.addEventListener('click', () => flipCanvas('horizontal', setStatus, pushUndo));
    }

    if (elements.toolFlipV) {
        elements.toolFlipV.addEventListener('click', () => flipCanvas('vertical', setStatus, pushUndo));
    }
}

/**
 * Rotate the active canvas layer
 * @param {number} degrees - Degrees to rotate (90, -90, 180, etc.)
 * @param {Function} setStatus - Callback to update status bar
 * @param {Function} pushUndo - Callback to push undo state
 */
export function rotateCanvas(degrees, setStatus, pushUndo) {
    // Rotate the entire canvas (all layers) by `degrees`.
    try {
        const deg = Number(degrees) || 0;
        const absDeg = Math.abs(deg) % 360;
        const is90 = absDeg === 90 || absDeg === 270;

        const oldW = state.layers[0].width;
        const oldH = state.layers[0].height;
        const newW = is90 ? oldH : oldW;
        const newH = is90 ? oldW : oldH;

        // Push undo for all layers
        state.layers.forEach((_, idx) => pushUndo(idx));

        // Prepare promises to transform each layer image
        const transforms = state.layers.map((canvas) => new Promise((resolve) => {
            const img = new Image();
            img.onload = () => {
                const tmp = document.createElement('canvas');
                tmp.width = newW;
                tmp.height = newH;
                const ctx = tmp.getContext('2d');
                ctx.save();
                ctx.translate(newW / 2, newH / 2);
                ctx.rotate((deg * Math.PI) / 180);
                ctx.drawImage(img, -oldW / 2, -oldH / 2);
                ctx.restore();
                resolve(tmp.toDataURL());
            };
            img.onerror = () => resolve(null);
            img.src = canvas.toDataURL();
        }));

        Promise.all(transforms).then((results) => {
            results.forEach((dataUrl, idx) => {
                if (!dataUrl) return;
                const canvas = state.layers[idx];
                const ctx = state.contexts[idx];
                canvas.width = newW;
                canvas.height = newH;
                canvas.style.width = `${newW}px`;
                canvas.style.height = `${newH}px`;
                const img = new Image();
                img.onload = () => { try { ctx.clearRect(0, 0, canvas.width, canvas.height); ctx.drawImage(img, 0, 0); } catch (e) { console.warn('rotate draw failed', e); } };
                img.src = dataUrl;
            });

            // Update wrapper sizes and UI
            if (elements.canvasWrap) {
                elements.canvasWrap.style.width = `${newW}px`;
                elements.canvasWrap.style.height = `${newH}px`;
            }
            const canvasInfo = document.querySelector('.canvas-info');
            if (canvasInfo) canvasInfo.textContent = `${newW} x ${newH} px`;
            if (elements.timelapseCanvas) { elements.timelapseCanvas.width = newW; elements.timelapseCanvas.height = newH; }

            setStatus(`${deg}度回転しました`);
        }).catch((err) => { console.error('rotateCanvas error', err); setStatus('回転に失敗しました'); });
    } catch (err) { console.error('rotateCanvas exception', err); setStatus('回転に失敗しました'); }
}

/**
 * Flip the active canvas layer
 * @param {string} direction - 'horizontal' or 'vertical'
 * @param {Function} setStatus - Callback to update status bar
 * @param {Function} pushUndo - Callback to push undo state
 */
export function flipCanvas(direction, setStatus, pushUndo) {
    // Flip all layers horizontally or vertically
    try {
        // Push undo for all layers
        state.layers.forEach((_, idx) => pushUndo(idx));

        const w = state.layers[0].width;
        const h = state.layers[0].height;

        const ops = state.layers.map((canvas) => new Promise((resolve) => {
            const img = new Image();
            img.onload = () => {
                const tmp = document.createElement('canvas');
                tmp.width = w;
                tmp.height = h;
                const ctx = tmp.getContext('2d');
                ctx.save();
                if (direction === 'horizontal') {
                    ctx.translate(w, 0);
                    ctx.scale(-1, 1);
                    ctx.drawImage(img, 0, 0);
                } else {
                    ctx.translate(0, h);
                    ctx.scale(1, -1);
                    ctx.drawImage(img, 0, 0);
                }
                ctx.restore();
                resolve(tmp.toDataURL());
            };
            img.onerror = () => resolve(null);
            img.src = canvas.toDataURL();
        }));

        Promise.all(ops).then((results) => {
            results.forEach((dataUrl, idx) => {
                if (!dataUrl) return;
                const canvas = state.layers[idx];
                const ctx = state.contexts[idx];
                const img = new Image();
                img.onload = () => { try { ctx.clearRect(0, 0, canvas.width, canvas.height); ctx.drawImage(img, 0, 0); } catch (e) { console.warn('flip draw failed', e); } };
                img.src = dataUrl;
            });
            setStatus(`${direction === 'horizontal' ? '左右' : '上下'}反転しました`);
        }).catch((err) => { console.error('flipCanvas error', err); setStatus('反転に失敗しました'); });
    } catch (err) { console.error('flipCanvas exception', err); setStatus('反転に失敗しました'); }
}

/**
 * Resize all canvas layers
 * @param {number} newWidth - New canvas width
 * @param {number} newHeight - New canvas height
 * @param {Function} savePersistedState - Callback to save state
 */
export function resizeCanvas(newWidth, newHeight, savePersistedState) {
    // Save current layer data
    const layerData = state.layers.map((canvas, idx) => {
        return canvas.toDataURL();
    });

    // Update canvas-wrap container size
    if (elements.canvasWrap) {
        elements.canvasWrap.style.width = `${newWidth}px`;
        elements.canvasWrap.style.height = `${newHeight}px`;
    }

    // Resize all layers
    state.layers.forEach((canvas, idx) => {
        // Update both canvas internal size and CSS size
        canvas.width = newWidth;
        canvas.height = newHeight;
        canvas.style.width = `${newWidth}px`;
        canvas.style.height = `${newHeight}px`;

        // Redraw layer content
        const img = new Image();
        img.onload = () => {
            state.contexts[idx].drawImage(img, 0, 0);
        };
        img.src = layerData[idx];
    });

    // Update canvas info
    const canvasInfo = document.querySelector('.canvas-info');
    if (canvasInfo) {
        canvasInfo.textContent = `${newWidth} x ${newHeight} px`;
    }

    // Update timelapse canvas
    if (elements.timelapseCanvas) {
        elements.timelapseCanvas.width = newWidth;
        elements.timelapseCanvas.height = newHeight;
    }

    if (savePersistedState) {
        savePersistedState();
    }
}
