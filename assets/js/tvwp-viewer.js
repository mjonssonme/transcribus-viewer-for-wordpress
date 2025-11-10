/**
 * Transcribus Viewer for WordPress
 *
 * @version 1.2.5
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
    console.log('TVWP: DOMContentLoaded fired, running initial check.');
    const viewers = document.querySelectorAll('.tvwp-viewer');
    console.log(`TVWP: Initial check found ${viewers.length} viewer(s).`);
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
                    console.log('TVWP: MutationObserver found a new viewer.');
                    initializeViewer(node);
                }
                
                if (node.querySelector) {
                    const viewers = node.querySelectorAll('.tvwp-viewer');
                    if (viewers.length > 0) {
                        console.log(`TVWP: MutationObserver found ${viewers.length} nested viewer(s).`);
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
        this.i18n = tvwp_data.i18n;       
        
        console.log(`TVWP: Initializing viewer for Post ID: ${this.postId}`);
        
        this.currentPage = 1;
        this.totalPages = 0;
        this.pageData = {};

        this.image = this.viewer.querySelector('.tvwp-image');
        this.svgOverlay = this.viewer.querySelector('.tvwp-overlay');
        this.textPane = this.viewer.querySelector('.tvwp-text-pane');
        this.controls = this.viewer.querySelector('.tvwp-controls');
        this.jumpSelect = this.viewer.querySelector('.tvwp-nav-jump');
        this.totalPagesSpan = this.viewer.querySelector('.tvwp-total-pages');

        if (!this.image || !this.textPane || !this.controls) {
            console.error('TVWP Error: Viewer HTML structure is missing elements.', this.viewer);
            return;
        }

        this.init();
    }

    async init() {
        this.showLoading(this.textPane);
        
        try {
            const tocUrl = `${this.restUrl}tvwp/v1/document/${this.postId}/toc`;
            console.log(`TVWP: Fetching TOC: ${tocUrl}`);
            
            const tocResponse = await fetch(tocUrl);
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
            if (target.dataset.navStep) {
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

        this.textPane.addEventListener('mouseenter', this.handleHighlight.bind(this), true);
        this.textPane.addEventListener('mouseleave', this.handleHighlight.bind(this), true);
        this.svgOverlay.addEventListener('mouseenter', this.handleHighlight.bind(this), true);
        this.svgOverlay.addEventListener('mouseleave', this.handleHighlight.bind(this), true);
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

        try {
            const pageUrl = `${this.restUrl}tvwp/v1/document/${this.postId}/page/${this.currentPage}`;
            console.log(`TVWP: Fetching Page: ${pageUrl}`);

            const pageResponse = await fetch(pageUrl);
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
        if (!this.pageData.lines) {
            this.textPane.innerHTML = '';
            return;
        }
        const html = this.pageData.lines.map(line => 
            `<span class="tvwp-line" data-line-id="${line.id}">${line.text || '&nbsp;'}</span>`
        ).join('');
        this.textPane.innerHTML = html;
    }

    drawOverlays() {
        if (!this.pageData.lines) {
            this.svgOverlay.innerHTML = '';
            return;
        }

        const originalWidth = this.pageData.image_width || 0;
        const displayWidth = this.image.clientWidth;
        
        if (originalWidth === 0 || displayWidth === 0) {
            return;
        }
        
        const ratio = displayWidth / originalWidth;

        let svgHtml = '';
        
        this.pageData.lines.forEach(line => {
            if (!line.coords) return; 

            const scaledPoints = line.coords.split(' ').map(pair => {
                const [x, y] = pair.split(',');
                const numX = parseFloat(x) || 0;
                const numY = parseFloat(y) || 0;
                return `${numX * ratio},${numY * ratio}`;
            }).join(' ');

            svgHtml += `<polygon class="tvwp-line" data-line-id="${line.id}" points="${scaledPoints}" />`;
        });

        this.svgOverlay.innerHTML = svgHtml;
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