/**
 * Panels Module
 * Handles panel collapse/expand and resizing functionality
 */

const STORAGE_KEY = 'paint_panel_state';

// Panel state storage
let panelStates = {
    'color-palette': { collapsed: false, height: null },
    'tool-settings': { collapsed: false, height: null },
    'layers': { collapsed: false, height: null }
};

/**
 * Initialize panel controls
 */
export function initPanels() {
    loadPanelStates();

    // Initialize toggle buttons
    document.querySelectorAll('.panel-section').forEach(panel => {
        const panelId = panel.dataset.panel;
        if (!panelId) return;

        const header = panel.querySelector('.panel-header');
        const toggleBtn = panel.querySelector('.panel-toggle');
        const content = panel.querySelector('.panel-content');
        const resizeHandle = panel.querySelector('.panel-resize-handle');

        // Apply saved state
        const state = panelStates[panelId];
        if (state) {
            if (state.collapsed) {
                panel.classList.add('collapsed');
            }
            if (state.height && !state.collapsed) {
                content.style.maxHeight = state.height + 'px';
                panel.style.flex = `0 0 auto`;
            }
        } else {
            // Set initial max-height for animation
            content.style.maxHeight = content.scrollHeight + 'px';
        }

        // Toggle functionality
        if (header && toggleBtn) {
            header.addEventListener('click', (e) => {
                // Don't toggle if clicking on content inside header
                if (e.target !== header && e.target !== toggleBtn && !toggleBtn.contains(e.target)) {
                    return;
                }
                togglePanel(panel, panelId);
            });
        }

        // Resize functionality
        if (resizeHandle) {
            initResizeHandle(panel, content, resizeHandle, panelId);
        }
    });
}

/**
 * Toggle panel collapse/expand
 */
function togglePanel(panel, panelId) {
    const content = panel.querySelector('.panel-content');
    const isCollapsed = panel.classList.contains('collapsed');

    if (isCollapsed) {
        // Expand
        panel.classList.remove('collapsed');
        const savedHeight = panelStates[panelId]?.height;
        content.style.maxHeight = savedHeight ? savedHeight + 'px' : content.scrollHeight + 'px';
        panelStates[panelId].collapsed = false;
    } else {
        // Collapse
        panel.classList.add('collapsed');
        content.style.maxHeight = '0';
        panelStates[panelId].collapsed = true;
    }

    savePanelStates();
}

/**
 * Initialize resize handle for a panel
 */
function initResizeHandle(panel, content, handle, panelId) {
    let startY = 0;
    let startHeight = 0;
    let isResizing = false;

    const onMouseDown = (e) => {
        e.preventDefault();
        isResizing = true;
        startY = e.clientY;
        startHeight = content.offsetHeight;

        document.body.style.cursor = 'ns-resize';
        document.body.style.userSelect = 'none';

        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
    };

    const onMouseMove = (e) => {
        if (!isResizing) return;

        const deltaY = e.clientY - startY;
        const newHeight = Math.max(100, Math.min(800, startHeight + deltaY));

        content.style.maxHeight = newHeight + 'px';
        panel.style.flex = '0 0 auto';
    };

    const onMouseUp = () => {
        if (!isResizing) return;

        isResizing = false;
        document.body.style.cursor = '';
        document.body.style.userSelect = '';

        document.removeEventListener('mousemove', onMouseMove);
        document.removeEventListener('mouseup', onMouseUp);

        // Save the new height
        const finalHeight = content.offsetHeight;
        panelStates[panelId].height = finalHeight;
        savePanelStates();
    };

    handle.addEventListener('mousedown', onMouseDown);
}

/**
 * Save panel states to localStorage
 */
function savePanelStates() {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(panelStates));
    } catch (e) {
        console.warn('Failed to save panel states:', e);
    }
}

/**
 * Load panel states from localStorage
 */
function loadPanelStates() {
    try {
        const saved = localStorage.getItem(STORAGE_KEY);
        if (saved) {
            const loaded = JSON.parse(saved);
            // Merge with defaults
            panelStates = {
                ...panelStates,
                ...loaded
            };
        }
    } catch (e) {
        console.warn('Failed to load panel states:', e);
    }
}

/**
 * Reset all panels to default state
 */
export function resetPanels() {
    panelStates = {
        'color-palette': { collapsed: false, height: null },
        'tool-settings': { collapsed: false, height: null },
        'layers': { collapsed: false, height: null }
    };

    localStorage.removeItem(STORAGE_KEY);

    // Reset UI
    document.querySelectorAll('.panel-section').forEach(panel => {
        panel.classList.remove('collapsed');
        const content = panel.querySelector('.panel-content');
        if (content) {
            content.style.maxHeight = content.scrollHeight + 'px';
        }
        panel.style.flex = '';
    });
}
