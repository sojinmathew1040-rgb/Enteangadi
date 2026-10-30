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
            p === '/' ||
            p.endsWith('index.php') ||
            p.endsWith('my_ads.php') ||
            p.endsWith('inbox.php') ||
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
                `${base}/user/profile.php`,
                `${base}/user/post_ad.php`,
                `${base}/privacy-policy`
            ]
            : [
                `${base}/login.php`,
                `${base}/register.php`,
                `${base}/privacy-policy`
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

    // Trigger loader ONLY on crucial navigation links (e.g. logout or explicit data-crucial-loader)
    document.addEventListener('click', (e) => {
        if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
        const a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
        if (!a || a.target === '_blank' || a.hasAttribute('download')) return;
        const raw = a.getAttribute('href') || '';
        if (!raw || raw.startsWith('#') || raw.startsWith('javascript:') || raw.startsWith('tel:') || raw.startsWith('mailto:') || raw.startsWith('whatsapp:')) return;

        let dest;
        try { dest = new URL(a.href, window.location.href); } catch (err) { return; }
        if (dest.origin !== window.location.origin) return;

        const isCrucialLink = a.dataset.crucialLoader === '1' || dest.pathname.endsWith('logout.php');
        if (isCrucialLink && window.EnteangadiLoader) {
            window.EnteangadiLoader.startNavigation(dest.pathname.endsWith('logout.php') ? 'Signing out...' : 'Loading...');
        }
    });

    // Trigger loader ONLY on crucial form submissions (auth, account security, or explicit data-crucial-loader)
    document.addEventListener('submit', (e) => {
        if (e.defaultPrevented) return;
        const form = e.target;
        if (!form || form.target === '_blank' || form.dataset.ajax === '1') return;
        if (form.id === 'postAdForm' || form.id === 'editAdForm') return; // Handled by dedicated #loadingOverlay

        const actionUrl = (form.getAttribute('action') || window.location.pathname || '').toLowerCase();
        const method = (form.getAttribute('method') || 'get').toLowerCase();
        const isCrucialForm =
            form.dataset.crucialLoader === '1' ||
            (method === 'post' && (
                actionUrl.includes('login.php') ||
                actionUrl.includes('register.php') ||
                actionUrl.includes('forgot_password') ||
                actionUrl.includes('reset_password') ||
                form.id === 'delete_pic_form' ||
                form.id === 'profile_pic_form'
            ));

        if (isCrucialForm && window.EnteangadiLoader) {
            window.EnteangadiLoader.startNavigation('Processing...');
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

    // Global Yes / No Photo Deletion Confirmation Helper
    window.confirmPhotoDelete = function (options = {}) {
        const title = options.title || 'Delete Photo?';
        const message = options.message || 'Do you want to delete this photo?';
        const imgSrc = options.imgSrc || '';
        const yesText = options.yesText || 'Yes';
        const noText = options.noText || 'No';
        const isRestore = Boolean(options.isRestore);

        return new Promise((resolve) => {
            let modal = document.getElementById('photoConfirmModal');
            if (!modal) {
                modal = document.createElement('div');
                modal.id = 'photoConfirmModal';
                modal.className = 'photo-confirm-modal-overlay';
                modal.style.display = 'none';
                modal.innerHTML = `
                    <div class="photo-confirm-card">
                        <div class="photo-confirm-thumb-wrapper" id="photoConfirmThumbWrap">
                            <img id="photoConfirmImg" src="" alt="Photo Preview">
                            <div class="photo-confirm-badge" id="photoConfirmBadge">
                                <i class="fa fa-trash-alt"></i>
                            </div>
                        </div>
                        <h3 id="photoConfirmTitle">Delete Photo?</h3>
                        <p id="photoConfirmDesc">Do you want to delete this photo?</p>
                        <div class="photo-confirm-actions">
                            <button type="button" class="btn-confirm-no" id="photoConfirmNoBtn">No</button>
                            <button type="button" class="btn-confirm-yes" id="photoConfirmYesBtn">Yes</button>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
            }

            const thumbWrap = modal.querySelector('#photoConfirmThumbWrap');
            const imgEl = modal.querySelector('#photoConfirmImg');
            const badgeEl = modal.querySelector('#photoConfirmBadge');
            const titleEl = modal.querySelector('#photoConfirmTitle');
            const descEl = modal.querySelector('#photoConfirmDesc');
            const yesBtn = modal.querySelector('#photoConfirmYesBtn');
            const noBtn = modal.querySelector('#photoConfirmNoBtn');

            if (thumbWrap && imgEl) {
                if (imgSrc) {
                    imgEl.src = imgSrc;
                    thumbWrap.style.display = 'block';
                    thumbWrap.style.borderColor = isRestore ? '#bbf7d0' : '#fee2e2';
                } else {
                    thumbWrap.style.display = 'none';
                }
            }
            if (badgeEl) {
                badgeEl.style.background = isRestore ? '#16a34a' : '#ef4444';
                badgeEl.innerHTML = isRestore ? '<i class="fa fa-undo"></i>' : '<i class="fa fa-trash-alt"></i>';
            }
            if (titleEl) titleEl.textContent = title;
            if (descEl) descEl.textContent = message;
            if (yesBtn) {
                yesBtn.textContent = yesText;
                yesBtn.style.background = isRestore ? '#16a34a' : '#ef4444';
            }
            if (noBtn) noBtn.textContent = noText;

            modal.style.display = 'flex';

            const finish = (ans) => {
                modal.style.display = 'none';
                if (yesBtn) yesBtn.onclick = null;
                if (noBtn) noBtn.onclick = null;
                modal.onclick = null;
                resolve(ans);
            };

            if (yesBtn) yesBtn.onclick = () => finish(true);
            if (noBtn) noBtn.onclick = () => finish(false);
            modal.onclick = (e) => {
                if (e.target === modal) finish(false);
            };
        });
    };
})();
