/**
 * Transcribus Viewer for WordPress
 *
 * @version 1.1.0
 */
document.addEventListener('DOMContentLoaded', function () {
    // V1.1: Find all viewer instances on the page
    const viewerElements = document.querySelectorAll('.tvwp-viewer');
    
    // Create a new Viewer object for each one
    viewerElements.forEach(viewerElement => {
        if (viewerElement) {
            new TVWP_Viewer(viewerElement);
        }
    });
});

/**
 * Main Viewer Class
 * This class now manages a single viewer instance.
 */
class TVWP_Viewer {

    constructor(element) {
        this.viewer = element;
        this.postId = this.viewer.dataset.postId;
        this.restUrl = tvwp_data.rest_url;
        this.i18n = tvwp_data.i18n;
        
        this.currentPage = 1;
        this.totalPages = 0;
        this.pageData = {};

        // V1.1: Find elements *inside* this specific viewer instance
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
            const tocResponse = await fetch(`${this.restUrl}tvwp/v1/document/${this.postId}/toc`);
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

        // Note: Resize needs to be debounced globally, but drawOverlays is instance-specific
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
        this.svgOverlay.innerHTML = ''; // Clear overlays
        this.image.src = '';

        try {
            const pageResponse = await fetch(`${this.restUrl}tvwp/v1/document/${this.postId}/page/${this.currentPage}`);
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
            return; // Stop function to prevent crash
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
        
        // V1.1: Find elements *inside this instance*
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