/**
 * Canvas Transform Module
 * Handles zoom, pan, rotate, flip, and resize operations
 */

import { state, elements } from './state.js';

/**
 * Zoom in/out by delta
 * @param {number} delta - Amount to zoom (positive = in, negative = out)
 * @param {Function} setStatus - Callback to update status bar
 * @param {Object} options - Optional {centerX, centerY} for zoom center point
 */
export function zoom(delta, setStatus, options = {}) {
    const oldZoom = state.zoomLevel;
    const newZoom = Math.max(0.1, Math.min(8, state.zoomLevel + delta));

    if (newZoom === oldZoom) return;

    // If center point is provided, adjust pan to keep that point steady
    if (options.centerX !== undefined && options.centerY !== undefined) {
        const canvasArea = elements.canvasWrap.parentElement;
        const areaRect = canvasArea.getBoundingClientRect();

        // Mouse position relative to viewport center (screen coordinates)
        const mouseX = (options.centerX - areaRect.left) - areaRect.width / 2;
        const mouseY = (options.centerY - areaRect.top) - areaRect.height / 2;

        // Zoom factor
        const factor = newZoom / oldZoom;

        // Adjust pan to keep the point under mouse steady
        // New pan = mouse * (1 - factor) + old pan * factor
        state.panOffset.x = mouseX * (1 - factor) + state.panOffset.x * factor;
        state.panOffset.y = mouseY * (1 - factor) + state.panOffset.y * factor;
    }

    state.zoomLevel = newZoom;
    applyTransform();
    setStatus(`ズーム: ${Math.round(state.zoomLevel * 100)}%`);
    updateZoomDisplay();
}

/**
 * Reset zoom and pan to default (centered, 100%)
 */
export function resetView() {
    state.zoomLevel = 1;
    state.panOffset = { x: 0, y: 0 };
    applyTransform();
    updateZoomDisplay();
}

/**
 * Fit canvas to viewport with optimal zoom
 */
export function zoomFit() {
    const canvasArea = elements.canvasWrap.parentElement;
    const canvasWidth = state.layers[0].width;
    const canvasHeight = state.layers[0].height;

    const areaWidth = canvasArea.clientWidth - 40; // padding
    const areaHeight = canvasArea.clientHeight - 40;

    const scaleX = areaWidth / canvasWidth;
    const scaleY = areaHeight / canvasHeight;

    state.zoomLevel = Math.min(scaleX, scaleY, 1); // Don't zoom beyond 100%
    state.panOffset = { x: 0, y: 0 };
    applyTransform();
    updateZoomDisplay();
}

/**
 * Apply current zoom level and pan offset to canvas
 * @param {boolean} instant - If true, apply without transition
 */
function applyTransform(instant = false) {
    const scale = state.zoomLevel;
    // Use matrix for precise control: translate first, then scale around center
    // This keeps pan in screen coordinates
    const transform = `translate(${state.panOffset.x}px, ${state.panOffset.y}px) scale(${scale})`;

    if (instant) {
        elements.canvasWrap.style.transition = 'none';
        elements.canvasWrap.style.transform = transform;
        // Force reflow
        void elements.canvasWrap.offsetHeight;
        elements.canvasWrap.style.transition = '';
    } else {
        elements.canvasWrap.style.transform = transform;
    }
}

/**
 * Update zoom display in UI
 */
function updateZoomDisplay() {
    const canvasInfo = document.querySelector('.canvas-info');
    if (canvasInfo) {
        const width = state.layers[0].width;
        const height = state.layers[0].height;
        const zoomPercent = Math.round(state.zoomLevel * 100);
        canvasInfo.textContent = `${width} x ${height} px | ${zoomPercent}%`;
    }
}

/**
 * Initialize canvas panning and zooming with mouse
 * @param {Function} setTool - Callback to set current tool
 * @param {Function} setStatus - Callback to update status bar
 */
export function initCanvasPan(setTool, setStatus) {
    // Spacebar panning
    document.addEventListener('keydown', (e) => {
        if (e.code === 'Space' && !state.spaceKeyPressed && !e.target.matches('input, textarea')) {
            state.spaceKeyPressed = true;
            state.layers.forEach(canvas => {
                canvas.style.cursor = 'grab';
            });
            e.preventDefault();
        }
    });

    document.addEventListener('keyup', (e) => {
        if (e.code === 'Space') {
            state.spaceKeyPressed = false;
            state.isPanning = false;
            setTool(state.currentTool); // Restore cursor
        }
    });

    // Mouse wheel zoom (Ctrl + wheel or just wheel over canvas)
    const canvasArea = elements.canvasWrap.parentElement;
    canvasArea.addEventListener('wheel', (e) => {
        if (e.ctrlKey || e.metaKey) {
            e.preventDefault();
            const delta = e.deltaY > 0 ? -0.1 : 0.1;
            zoom(delta, setStatus, { centerX: e.clientX, centerY: e.clientY });
        }
    }, { passive: false });

    // Middle mouse button panning
    let middleButtonPanning = false;

    // Handle mousedown on canvas area (for both space+click and middle button)
    canvasArea.addEventListener('mousedown', (e) => {
        // Space + left click OR middle mouse button
        if ((state.spaceKeyPressed && e.button === 0) || e.button === 1) {
            state.isPanning = true;
            middleButtonPanning = e.button === 1;
            state.panStart = { x: e.clientX, y: e.clientY };

            // Set cursor
            state.layers.forEach(canvas => {
                canvas.style.cursor = 'grabbing';
            });
            canvasArea.classList.add('panning');
            canvasArea.style.cursor = 'grabbing';

            e.preventDefault();
            e.stopPropagation();
        }
    }, { passive: false });

    // Also prevent default behavior for auxclick (middle button)
    canvasArea.addEventListener('auxclick', (e) => {
        if (e.button === 1) {
            e.preventDefault();
            e.stopPropagation();
        }
    }, { passive: false });

    document.addEventListener('mousemove', (e) => {
        if (state.isPanning) {
            const dx = e.clientX - state.panStart.x;
            const dy = e.clientY - state.panStart.y;

            state.panOffset.x += dx;
            state.panOffset.y += dy;
            state.panStart = { x: e.clientX, y: e.clientY };

            applyTransform(true); // Instant during panning for smooth feedback
            e.preventDefault();
        }
    });

    document.addEventListener('mouseup', (e) => {
        if (state.isPanning && (e.button === 0 || e.button === 1)) {
            state.isPanning = false;
            canvasArea.classList.remove('panning');
            canvasArea.style.cursor = '';

            if (middleButtonPanning) {
                middleButtonPanning = false;
                setTool(state.currentTool);
            } else if (state.spaceKeyPressed) {
                state.layers.forEach(canvas => {
                    canvas.style.cursor = 'grab';
                });
            } else {
                setTool(state.currentTool);
            }
        }
    });

    // Prevent context menu on canvas area when panning
    canvasArea.addEventListener('contextmenu', (e) => {
        if (state.isPanning || middleButtonPanning) {
            e.preventDefault();
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
        elements.toolZoomFit.addEventListener('click', () => {
            zoomFit();
            setStatus('キャンバスをフィット表示');
        });
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

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => {
        // Ctrl+0 or Cmd+0: Reset view
        if ((e.ctrlKey || e.metaKey) && e.key === '0') {
            e.preventDefault();
            resetView();
            setStatus('ビューをリセット (100%)');
        }
        // Ctrl+= or Ctrl++: Zoom in
        else if ((e.ctrlKey || e.metaKey) && (e.key === '=' || e.key === '+')) {
            e.preventDefault();
            zoom(0.25, setStatus);
        }
        // Ctrl+-: Zoom out
        else if ((e.ctrlKey || e.metaKey) && e.key === '-') {
            e.preventDefault();
            zoom(-0.25, setStatus);
        }
    });
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
            if (elements.timelapseCanvas) {
                elements.timelapseCanvas.width = newW;
                elements.timelapseCanvas.height = newH;
            }

            updateZoomDisplay();
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
            updateZoomDisplay();
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
    const layerData = state.layers.map((canvas) => {
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

    // Update timelapse canvas
    if (elements.timelapseCanvas) {
        elements.timelapseCanvas.width = newWidth;
        elements.timelapseCanvas.height = newHeight;
    }

    updateZoomDisplay();

    if (savePersistedState) {
        savePersistedState();
    }
}
