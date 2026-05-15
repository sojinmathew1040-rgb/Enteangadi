/**
 * Enteangadi Infinite Scroll & Skeleton Loader
 */
class EnteangadiInfiniteScroll {
    constructor(options) {
        this.container = document.querySelector(options.containerSelector);
        this.loader = document.querySelector(options.loaderSelector);
        this.apiUrl = options.apiUrl;
        this.params = options.params || {};
        this.page = 1;
        this.loading = false;
        this.hasMore = true;
        this.translations = options.translations || {};

        if (!this.container) return;

        this.init();
    }

    init() {
        this.observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && !this.loading && this.hasMore) {
                this.loadMore();
            }
        }, { threshold: 0.1 });

        if (this.loader) {
            this.observer.observe(this.loader);
        }
    }

    async loadMore() {
        this.loading = true;
        this.showSkeletons();

        try {
            const urlParams = new URLSearchParams({
                ...this.params,
                page: this.page + 1
            });
            const response = await fetch(`${this.apiUrl}?${urlParams.toString()}`);
            const data = await response.json();

            if (data.success) {
                this.removeSkeletons();
                this.renderProducts(data.products);
                this.page++;
                this.hasMore = data.has_more;

                if (!this.hasMore && this.loader) {
                    this.loader.style.display = 'none';
                }
            }
        } catch (e) {
            console.error('Failed to load products', e);
        } finally {
            this.loading = false;
        }
    }

    showSkeletons() {
        const skeletonHtml = `
            <div class="skeleton-card">
                <div class="skeleton skeleton-img"></div>
                <div class="skeleton skeleton-text"></div>
                <div class="skeleton skeleton-text short"></div>
            </div>
        `.repeat(4);

        const skeletonContainer = document.createElement('div');
        skeletonContainer.id = 'skeleton-container';
        skeletonContainer.className = 'product-grid';
        skeletonContainer.style.marginTop = '24px';
        skeletonContainer.innerHTML = skeletonHtml;
        this.container.after(skeletonContainer);
    }

    removeSkeletons() {
        const skeletonContainer = document.getElementById('skeleton-container');
        if (skeletonContainer) skeletonContainer.remove();
    }

    renderProducts(products) {
        if (!products || products.length === 0) return;

        const html = products.map(p => this.createProductCard(p)).join('');
        this.container.insertAdjacentHTML('beforeend', html);
    }

    createProductCard(p) {
        const price = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(p.price);
        const distanceHtml = p.distance ? `<span class="distance-badge">${Math.round(p.distance * 10) / 10} ${this.translations.distance_away}</span>` : '';
        const badgeClass = p.type === 'buy' ? 'badge-wanted' : 'badge-selling';
        const badgeText = p.type === 'buy' ? this.translations.wanted : this.translations.for_sale;

        return `
            <a href="product.php?id=${p.id}" class="product-card">
                <div class="${badgeClass}">${badgeText}</div>
                ${p.main_image ? `<img src="${p.main_image}" class="product-card-image">` : `
                    <div class="product-card-image placeholder-bg">
                        <i class="fa fa-image placeholder-icon"></i>
                    </div>
                `}
                <div class="product-card-content">
                    <div class="product-card-price">${price}</div>
                    <div class="product-card-title">
                        ${p.title}
                        ${p.is_verified ? `<span class="verified-badge" title="Verified Listing"><i class="fa fa-check"></i></span>` : ''}
                    </div>
                    <div class="product-card-meta">
                        <span><i class="fa fa-map-marker-alt meta-icon-sm"></i> ${p.location_name || 'Unknown'}</span>
                        ${distanceHtml}
                    </div>
                </div>
            </a>
        `;
    }
}
