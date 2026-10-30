// main.js - Global Enteangadi UX & Background Page Preloader

(function () {
    const preloadedUrls = new Set();
    const inFlight = new Set();
    const queue = [];
    let activeWorkers = 0;
    const MAX_CONCURRENCY = 2;
    const MAX_VIEWPORT_PRELOADS = 20;
    let viewportCount = 0;
    let linkObserver = null;

    const conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
    const isSaveData = Boolean(conn && (conn.saveData || /2g/.test(conn.effectiveType || '')));

    function shouldPreloadUrl(rawHref) {
        if (!rawHref) return null;
        let u;
        try { u = new URL(rawHref, window.location.href); } catch (e) { return null; }
        if (u.origin !== window.location.origin) return null;
        if (u.href === window.location.href.split('#')[0]) return null;
        u.hash = '';
        const p = u.pathname;
        if (
            p.includes('/api/') ||
            p.includes('api_') ||
            p.includes('/admin/') ||
            p.endsWith('logout.php') ||
            p.endsWith('chat.php') ||
            p.endsWith('delete_ad.php') ||
            u.searchParams.has('lang') ||
            u.searchParams.has('logged_out')
        ) {
            return null;
        }
        return u.href;
    }

    function pumpQueue() {
        while (activeWorkers < MAX_CONCURRENCY && queue.length > 0) {
            const target = queue.shift();
            if (preloadedUrls.has(target) || inFlight.has(target)) continue;
            inFlight.add(target);
            activeWorkers++;

            fetch(target, {
                method: 'GET',
                credentials: 'include',
                headers: { 'X-Enteangadi-Preload': '1' },
                priority: 'low'
            })
                .then((res) => {
                    if (res && res.ok) {
                        preloadedUrls.add(target);
                        if (res.url) preloadedUrls.add(res.url);
                    }
                })
                .catch(() => {})
                .finally(() => {
                    inFlight.delete(target);
                    activeWorkers--;
                    pumpQueue();
                });
        }
    }

    function enqueuePreload(rawHref, highPriority = false) {
        const clean = shouldPreloadUrl(rawHref);
        if (!clean || preloadedUrls.has(clean) || inFlight.has(clean)) return;
        if (highPriority) {
            const idx = queue.indexOf(clean);
            if (idx !== -1) queue.splice(idx, 1);
            queue.unshift(clean);
        } else if (!queue.includes(clean)) {
            queue.push(clean);
        }
        pumpQueue();
    }

    function scanLinks(root = document) {
        if (isSaveData || !('IntersectionObserver' in window)) return;
        if (!linkObserver) {
            linkObserver = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (!entry.isIntersecting) return;
                    linkObserver.unobserve(entry.target);
                    if (viewportCount >= MAX_VIEWPORT_PRELOADS) return;
                    const href = entry.target.getAttribute('href');
                    if (shouldPreloadUrl(href)) {
                        viewportCount++;
                        enqueuePreload(href, false);
                    }
                });
            }, { rootMargin: '220px 0px' });
        }

        root.querySelectorAll('a.product-card[href], a.category-card[href], a.recently-viewed-card[href]').forEach((a) => {
            if (!a.dataset.preloadObserved && !a.dataset.noPreload) {
                a.dataset.preloadObserved = '1';
                linkObserver.observe(a);
            }
        });
    }

    function preloadCoreRoutes() {
        if (isSaveData || typeof EnteangadiConfig === 'undefined') return;
        const base = EnteangadiConfig.baseUrl || '';
        const core = EnteangadiConfig.isLoggedIn
            ? [
                `${base}/user/index.php`,
                `${base}/user/inbox.php`,
                `${base}/user/my_ads.php`,
                `${base}/user/profile.php`,
                `${base}/user/post_ad.php`
            ]
            : [
                `${base}/guest/index.php`,
                `${base}/login.php`,
                `${base}/register.php`
            ];
        core.forEach((u) => enqueuePreload(u, false));
    }

    window.EnteangadiPreloader = {
        preload: enqueuePreload,
        scanLinks,
        isPreloaded: (href) => {
            const c = shouldPreloadUrl(href);
            return c ? preloadedUrls.has(c) : false;
        }
    };

    // High-priority instant intent preload on touchstart / pointerdown / hover
    const onIntent = (e) => {
        const a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (!a || a.dataset.noPreload || a.target === '_blank' || a.hasAttribute('download')) return;
        const raw = a.getAttribute('href') || '';
        if (!raw || raw.startsWith('#') || raw.startsWith('javascript:') || raw.startsWith('tel:') || raw.startsWith('mailto:') || raw.startsWith('whatsapp:')) return;
        enqueuePreload(a.href, true);
    };
    document.addEventListener('touchstart', onIntent, { passive: true });
    document.addEventListener('pointerdown', onIntent, { passive: true });
    document.addEventListener('mouseover', onIntent, { passive: true });

    // Coordinate click navigation with Counting Percentage + Breathing Logo Loader
    document.addEventListener('click', (e) => {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        const a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (!a || a.target === '_blank' || a.hasAttribute('download')) return;
        const raw = a.getAttribute('href') || '';
        if (!raw || raw.startsWith('#') || raw.startsWith('javascript:') || raw.startsWith('tel:') || raw.startsWith('mailto:') || raw.startsWith('whatsapp:')) return;
        if (a.getAttribute('onclick') && a.getAttribute('onclick').includes('applyFilterAjax')) return;

        let dest;
        try { dest = new URL(a.href, window.location.href); } catch (err) { return; }
        if (dest.origin !== window.location.origin) return;
        if (dest.pathname === window.location.pathname && dest.search === window.location.search) return;

        const cleanHref = dest.href.split('#')[0];
        if (preloadedUrls.has(cleanHref)) {
            try { sessionStorage.setItem('enteangadi_preloaded_hit', '1'); } catch (err) {}
        } else if (window.EnteangadiLoader) {
            window.EnteangadiLoader.startNavigation('Loading page...');
        }
    });

    // Show Counting Percentage + Breathing Logo Loader on full-page form submissions
    document.addEventListener('submit', (e) => {
        if (e.defaultPrevented) return;
        const form = e.target;
        if (!form || form.target === '_blank' || form.dataset.ajax === '1') return;
        if (window.EnteangadiLoader) {
            window.EnteangadiLoader.startNavigation('Loading...');
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        // 1. Hide/Show Bottom Navigation on Scroll
        const bottomNav = document.querySelector('.mobile-bottom-nav');
        if (bottomNav) {
            let lastScrollY = window.scrollY;
            window.addEventListener('scroll', () => {
                const currentScrollY = window.scrollY;
                const diff = currentScrollY - lastScrollY;
                if (currentScrollY < 80 || (window.innerHeight + currentScrollY) >= document.body.offsetHeight - 50) {
                    bottomNav.classList.remove('mobile-bottom-nav--hidden');
                    return;
                }
                if (Math.abs(diff) > 8) {
                    bottomNav.classList.toggle('mobile-bottom-nav--hidden', diff > 0);
                    lastScrollY = currentScrollY;
                }
            }, { passive: true });
        }

        // 2. Active Tab Tap Feedback for Mobile Nav
        document.querySelectorAll('.mobile-bottom-nav .nav-item').forEach((item) => {
            const scaleEl = (s) => {
                const target = item.querySelector('.sell-circle') || item.querySelector('i');
                if (target) target.style.transform = `scale(${s})`;
            };
            item.addEventListener('touchstart', () => scaleEl(0.86), { passive: true });
            item.addEventListener('touchend', () => scaleEl(1), { passive: true });
        });

        // 3. Category Carousel Mouse Drag
        const categoryScroll = document.querySelector('.category-scroll-container');
        if (categoryScroll) {
            let isDown = false, startX = 0, scrollLeft = 0;
            categoryScroll.addEventListener('mousedown', (e) => {
                isDown = true;
                startX = e.pageX - categoryScroll.offsetLeft;
                scrollLeft = categoryScroll.scrollLeft;
                categoryScroll.style.scrollBehavior = 'auto';
            });
            categoryScroll.addEventListener('mouseleave', () => { isDown = false; });
            categoryScroll.addEventListener('mouseup', () => { isDown = false; categoryScroll.style.scrollBehavior = 'smooth'; });
            categoryScroll.addEventListener('mousemove', (e) => {
                if (!isDown) return;
                e.preventDefault();
                categoryScroll.scrollLeft = scrollLeft - (e.pageX - categoryScroll.offsetLeft - startX) * 2;
            });
        }

        // 4. Schedule Background Page Preloading once main UI is idle
        const runIdle = window.requestIdleCallback || ((cb) => setTimeout(cb, 350));
        runIdle(() => {
            preloadCoreRoutes();
            scanLinks(document);
        });
    });
})();
