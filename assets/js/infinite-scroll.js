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
        if (this.container) this.init();
    }

    init() {
        this.observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting && !this.loading && this.hasMore) this.loadMore();
        }, { threshold: 0.1 });
        if (this.loader) this.observer.observe(this.loader);
    }

    async loadMore() {
        this.loading = true;
        this.showSkeletons();
        try {
            const urlParams = new URLSearchParams({ ...this.params, page: this.page + 1 });
            const data = await (await fetch(`${this.apiUrl}?${urlParams.toString()}`)).json();
            if (data.success) {
                this.removeSkeletons();
                this.renderProducts(data.products);
                this.page++;
                this.hasMore = data.has_more;
                if (!this.hasMore && this.loader) this.loader.style.display = 'none';
            }
        } catch (e) {
            this.removeSkeletons();
        } finally {
            this.loading = false;
        }
    }

    showSkeletons() {
        const sc = document.createElement('div');
        sc.id = 'skeleton-container';
        sc.className = 'product-grid';
        sc.style.marginTop = '24px';
        sc.innerHTML = `<div class="skeleton-card"><div class="skeleton skeleton-img"></div><div class="skeleton skeleton-text"></div><div class="skeleton skeleton-text short"></div></div>`.repeat(4);
        this.container.after(sc);
    }

    removeSkeletons() {
        const sc = document.getElementById('skeleton-container');
        if (sc) sc.remove();
    }

    renderProducts(products) {
        if (!products || !products.length) return;
        this.container.insertAdjacentHTML('beforeend', products.map((p) => this.createProductCard(p)).join(''));
        if (window.EnteangadiPreloader) window.EnteangadiPreloader.scanLinks(this.container);
    }

    createProductCard(p) {
        const prefix = (window.location.pathname.includes('/user/') || window.location.pathname.includes('/guest/')) ? '../' : '';
        const price = new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: 0 }).format(p.price);
        const distanceHtml = p.distance ? `<span class="distance-badge">${Math.round(p.distance * 10) / 10} ${this.translations.distance_away || 'km'}</span>` : '';
        const badgeClass = p.type === 'buy' ? 'badge-wanted' : (p.type === 'rent' ? 'badge-rent' : 'badge-selling');
        const badgeText = p.type === 'buy' ? this.translations.wanted : (p.type === 'rent' ? this.translations.for_rent : this.translations.for_sale);
        const imgHtml = p.main_image
            ? `<img src="${prefix}${p.main_image}" loading="lazy" decoding="async" class="product-card-image">`
            : `<div class="product-card-image placeholder-bg"><i class="fa fa-image placeholder-icon"></i></div>`;

        return `<a href="${prefix}product.php?id=${p.id}" class="product-card"><div class="${badgeClass}">${badgeText}</div>${imgHtml}<div class="product-card-content"><div class="product-card-price">${price}</div><div class="product-card-title">${p.title}${p.is_verified ? `<span class="verified-badge" title="Verified Listing"><i class="fa fa-check"></i></span>` : ''}</div><div class="product-card-meta"><span><i class="fa fa-map-marker-alt meta-icon-sm"></i> ${p.location_name || 'Unknown'}</span>${distanceHtml}</div></div></a>`;
    }
}
