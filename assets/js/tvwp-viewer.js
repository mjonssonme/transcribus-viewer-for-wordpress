/**
 * Transcribus Viewer for WordPress
 *
 * @version 1.7.0
 */

// This is the core initialization logic.
function initializeViewer(element) {
    // Add a check to prevent re-initializing a viewer
    if (element.dataset.initialized) {
        return;
    }

    if (element) {
        element.dataset.initialized = 'true';
        new TVWP_Viewer(element);
    }
}

// --- THIS IS THE FIX ---

// 1. Find all viewers that *already exist* on the page right now.
// This is for the frontend (CPT page, shortcode, post preview).
// We wrap it in DOMContentLoaded to make sure the footer script
// sees the body HTML.
document.addEventListener('DOMContentLoaded', () => {
    const viewers = document.querySelectorAll('.tvwp-viewer');
    viewers.forEach(initializeViewer);
});

// 2. Create a MutationObserver to watch for new blocks being added by the editor.
// This is for the block editor preview.
const observer = new MutationObserver((mutationsList) => {
    for (const mutation of mutationsList) {
        if (mutation.type === 'childList') {
            mutation.addedNodes.forEach(node => {
                if (node.nodeType !== 1) return; // Not an element

                if (node.classList && node.classList.contains('tvwp-viewer')) {
                    initializeViewer(node);
                }

                if (node.querySelector) {
                    const viewers = node.querySelectorAll('.tvwp-viewer');
                    if (viewers.length > 0) {
                        viewers.forEach(initializeViewer);
                    }
                }
            });
        }
    }
});

// Start observing the entire document for changes
observer.observe(document.body, { childList: true, subtree: true });
// --- END FIX ---


/**
 * Main Viewer Class
 * (The rest of this file is 100% identical to the previous version)
 */
class TVWP_Viewer {

    constructor(element) {
        this.viewer = element;
        this.postId = this.viewer.dataset.postId;
        this.restUrl = tvwp_data.rest_url;
        this.nonce = tvwp_data.nonce;
        this.i18n = tvwp_data.i18n;

        this.currentPage = 1;
        this.totalPages = 0;
        this.pageData = {};

        this.image = this.viewer.querySelector('.tvwp-image');
        this.svgOverlay = this.viewer.querySelector('.tvwp-overlay');
        this.textPane = this.viewer.querySelector('.tvwp-text-pane');
        this.controls = this.viewer.querySelector('.tvwp-controls');
        this.jumpSelect = this.viewer.querySelector('.tvwp-nav-jump');
        this.totalPagesSpan = this.viewer.querySelector('.tvwp-total-pages');
        this.imagePane = this.viewer.querySelector('.tvwp-image-pane');
        this.imageWrapper = this.viewer.querySelector('.tvwp-image-wrapper');

        // Zoom and pan state
        this.zoom = 1;
        this.minZoom = 1;
        this.maxZoom = 5;
        this.panX = 0;
        this.panY = 0;
        this.isPanning = false;
        this.startPanX = 0;
        this.startPanY = 0;

        if (!this.image || !this.textPane || !this.controls || !this.imagePane || !this.imageWrapper) {
            console.error('TVWP Error: Viewer HTML structure is missing elements.', this.viewer);
            return;
        }

        this.init();
    }

    async init() {
        this.showLoading(this.textPane);

        try {
            const tocUrl = `${this.restUrl}tvwp/v1/document/${this.postId}/toc`;
            const tocResponse = await fetch(tocUrl, { headers: { 'X-WP-Nonce': this.nonce } });
            if (!tocResponse.ok) {
                throw new Error(`Could not load TOC. Server responded ${tocResponse.status}`);
            }
            const tocData = await tocResponse.json();

            if (!tocData.page_count) {
                throw new Error('TOC data is invalid. Page count is 0.');
            }

            this.totalPages = tocData.page_count;
            this.totalPagesSpan.textContent = this.totalPages;
            this.populateJumpSelect();
            this.loadPage(1);
            this.addEventListeners();

        } catch (error) {
            console.error('TVWP Init Error:', error, this.viewer);
            this.textPane.innerHTML = `<p>${this.i18n.loadingError}</p>`;
        }
    }

    addEventListeners() {
        this.controls.addEventListener('click', (e) => {
            const target = e.target.closest('.tvwp-nav');
            if (!target) return;
            let newPage = this.currentPage;
            if (target.dataset.navGoto === 'first') {
                newPage = 1;
            } else if (target.dataset.navGoto === 'last') {
                newPage = this.totalPages;
            } else if (target.dataset.navStep) {
                newPage += parseInt(target.dataset.navStep, 10);
            } else if (target.dataset.navSkip) {
                newPage += parseInt(target.dataset.navSkip, 10);
            }
            this.loadPage(newPage);
        });

        this.jumpSelect.addEventListener('change', (e) => {
            this.loadPage(parseInt(e.target.value, 10));
        });

        this.image.addEventListener('load', () => {
            this.drawOverlays();
        });

        window.addEventListener('resize', this.debounce(() => {
            if (this.pageData.lines) {
                this.drawOverlays();
            }
        }, 250));

        // Dragging the pane's native resize handle changes its box size without
        // firing a window resize event, so the overlay never redrew on drag/release.
        if (window.ResizeObserver) {
            const redrawOnResize = this.debounce(() => {
                if (this.pageData.lines) {
                    this.drawOverlays();
                }
            }, 100);
            this.resizeObserver = new ResizeObserver(redrawOnResize);
            this.resizeObserver.observe(this.imagePane);
        }

        this.textPane.addEventListener('mouseenter', this.handleHighlight.bind(this), true);
        this.textPane.addEventListener('mouseleave', this.handleHighlight.bind(this), true);
        this.svgOverlay.addEventListener('mouseenter', this.handleHighlight.bind(this), true);
        this.svgOverlay.addEventListener('mouseleave', this.handleHighlight.bind(this), true);

        // Zoom with mouse wheel
        this.imagePane.addEventListener('wheel', this.handleWheel.bind(this), { passive: false });

        // Pan with mouse drag
        this.imageWrapper.addEventListener('mousedown', this.handlePanStart.bind(this));
        this.imageWrapper.addEventListener('mousemove', this.handlePanMove.bind(this));
        this.imageWrapper.addEventListener('mouseup', this.handlePanEnd.bind(this));
        this.imageWrapper.addEventListener('mouseleave', this.handlePanEnd.bind(this));

        // Click text line to center on image
        this.textPane.addEventListener('click', this.handleTextClick.bind(this));
    }

    populateJumpSelect() {
        let options = '';
        for (let i = 1; i <= this.totalPages; i++) {
            options += `<option value="${i}">${i}</option>`;
        }
        this.jumpSelect.innerHTML = options;
    }

    async loadPage(pageNumber) {
        pageNumber = Math.max(1, Math.min(this.totalPages, pageNumber));
        if (pageNumber === this.currentPage && this.pageData.lines) {
            return;
        }

        this.currentPage = pageNumber;
        this.jumpSelect.value = pageNumber;
        this.showLoading(this.textPane);
        this.svgOverlay.innerHTML = '';
        this.image.src = '';

        // Reset zoom and pan when loading new page
        this.zoom = 1;
        this.panX = 0;
        this.panY = 0;
        this.updateTransform();

        try {
            const pageUrl = `${this.restUrl}tvwp/v1/document/${this.postId}/page/${this.currentPage}`;
            const pageResponse = await fetch(pageUrl, { headers: { 'X-WP-Nonce': this.nonce } });
            if (!pageResponse.ok) {
                throw new Error(`Could not load page data. Server responded ${pageResponse.status}`);
            }

            this.pageData = await pageResponse.json();

            if (!this.pageData.image_url) {
                throw new Error('Page data is missing image URL.');
            }

            this.image.src = this.pageData.image_url;
            this.renderText();

        } catch (error) {
            console.error('TVWP LoadPage Error:', error, this.viewer);
            this.textPane.innerHTML = `<p>Error loading page ${this.currentPage}.</p>`;
        }
    }

    renderText() {
        this.textPane.replaceChildren();
        if (!this.pageData.lines) {
            return;
        }
        this.pageData.lines.forEach(line => {
            const span = document.createElement('span');
            span.className = 'tvwp-line';
            span.dataset.lineId = line.id || '';
            if (line.text) {
                span.textContent = line.text;
            } else {
                span.innerHTML = '&nbsp;';
            }
            this.textPane.appendChild(span);
        });
    }

    drawOverlays() {
        this.svgOverlay.replaceChildren();
        if (!this.pageData.lines) {
            return;
        }

        const originalWidth = this.pageData.image_width || 0;
        const displayWidth = this.image.clientWidth;

        if (originalWidth === 0 || displayWidth === 0) {
            return;
        }

        const ratio = displayWidth / originalWidth;
        const svgNs = 'http://www.w3.org/2000/svg';

        this.pageData.lines.forEach(line => {
            if (!line.coords) return;

            const scaledPoints = line.coords.split(' ').map(pair => {
                const [x, y] = pair.split(',');
                const numX = parseFloat(x) || 0;
                const numY = parseFloat(y) || 0;
                return `${numX * ratio},${numY * ratio}`;
            }).join(' ');

            const polygon = document.createElementNS(svgNs, 'polygon');
            polygon.setAttribute('class', 'tvwp-line');
            polygon.dataset.lineId = line.id || '';
            polygon.setAttribute('points', scaledPoints);
            this.svgOverlay.appendChild(polygon);
        });
    }

    handleHighlight(e) {
        const target = e.target.closest('.tvwp-line');
        if (!target) return;

        const lineId = target.dataset.lineId;
        if (!lineId) return;

        const isEntering = (e.type === 'mouseenter');

        const textSpan = this.textPane.querySelector(`.tvwp-line[data-line-id="${lineId}"]`);
        const svgPolygon = this.svgOverlay.querySelector(`.tvwp-line[data-line-id="${lineId}"]`);

        if (textSpan && svgPolygon) {
            if (isEntering) {
                textSpan.classList.add('tvwp-highlight');
                svgPolygon.classList.add('tvwp-highlight');
            } else {
                textSpan.classList.remove('tvwp-highlight');
                svgPolygon.classList.remove('tvwp-highlight');
            }
        }
    }

    handleWheel(e) {
        e.preventDefault();

        const delta = -e.deltaY;
        const zoomIntensity = 0.1;
        const newZoom = Math.max(this.minZoom, Math.min(this.maxZoom, this.zoom + (delta > 0 ? zoomIntensity : -zoomIntensity)));

        if (newZoom === this.zoom) return;

        // Get mouse position relative to the image pane
        const rect = this.imagePane.getBoundingClientRect();
        const mouseX = e.clientX - rect.left;
        const mouseY = e.clientY - rect.top;

        // Calculate zoom point relative to current transform
        const zoomPointX = (mouseX - this.panX) / this.zoom;
        const zoomPointY = (mouseY - this.panY) / this.zoom;

        // Update zoom
        this.zoom = newZoom;

        // Adjust pan to keep zoom point under mouse
        this.panX = mouseX - zoomPointX * this.zoom;
        this.panY = mouseY - zoomPointY * this.zoom;

        this.updateTransform();
    }

    handlePanStart(e) {
        if (this.zoom <= 1) return; // Only pan when zoomed in

        e.preventDefault();
        this.isPanning = true;
        this.startPanX = e.clientX - this.panX;
        this.startPanY = e.clientY - this.panY;
        this.imageWrapper.style.cursor = 'grabbing';
    }

    handlePanMove(e) {
        if (!this.isPanning) return;

        e.preventDefault();
        this.panX = e.clientX - this.startPanX;
        this.panY = e.clientY - this.startPanY;
        this.updateTransform();
    }

    handlePanEnd() {
        this.isPanning = false;
        this.imageWrapper.style.cursor = this.zoom > 1 ? 'grab' : 'default';
    }

    handleTextClick(e) {
        const target = e.target.closest('.tvwp-line');
        if (!target) return;

        const lineId = target.dataset.lineId;
        if (!lineId) return;

        this.centerOnLine(lineId);
    }

    centerOnLine(lineId) {
        // Find the line data
        const line = this.pageData.lines?.find(l => l.id === lineId);
        if (!line || !line.coords) return;

        // Parse coordinates to find bounding box
        const points = line.coords.split(' ').map(pair => {
            const [x, y] = pair.split(',');
            return { x: parseFloat(x) || 0, y: parseFloat(y) || 0 };
        });

        if (points.length === 0) return;

        // Find center of the line
        const minX = Math.min(...points.map(p => p.x));
        const maxX = Math.max(...points.map(p => p.x));
        const minY = Math.min(...points.map(p => p.y));
        const maxY = Math.max(...points.map(p => p.y));

        const centerX = (minX + maxX) / 2;
        const centerY = (minY + maxY) / 2;

        // Scale to current display size
        const originalWidth = this.pageData.image_width || 0;
        const displayWidth = this.image.clientWidth;
        const ratio = displayWidth / originalWidth;

        const scaledCenterX = centerX * ratio;
        const scaledCenterY = centerY * ratio;

        // Calculate pan to center this point in the viewport
        const paneRect = this.imagePane.getBoundingClientRect();
        const viewportCenterX = paneRect.width / 2;
        const viewportCenterY = paneRect.height / 2;

        // Apply zoom scaling to the target center point
        this.panX = viewportCenterX - (scaledCenterX * this.zoom);
        this.panY = viewportCenterY - (scaledCenterY * this.zoom);

        this.updateTransform(true);
    }

    updateTransform(smooth = false) {
        if (smooth) {
            this.imageWrapper.style.transition = 'transform 0.3s ease-out';
            setTimeout(() => {
                this.imageWrapper.style.transition = '';
            }, 300);
        }

        this.imageWrapper.style.transform = `translate(${this.panX}px, ${this.panY}px) scale(${this.zoom})`;
        this.imageWrapper.style.cursor = this.zoom > 1 ? 'grab' : 'default';
    }

    showLoading(element) {
        if (!element) return;
        if (element.tagName === 'svg') {
            element.innerHTML = `<text x="10" y="20" fill="#888">${this.i18n.loading}</text>`;
        } else {
            element.innerHTML = `<p>${this.i18n.loading}</p>`;
        }
    }

    debounce(func, wait) {
        let timeout;
        return function(...args) {
            const context = this;
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(context, args), wait);
        };
    }
}