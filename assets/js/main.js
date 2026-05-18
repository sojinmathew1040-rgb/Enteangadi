// main.js - Global Enteangadi Scripts and UX Enhancements

document.addEventListener('DOMContentLoaded', () => {
    // 1. Hide/Show Bottom Navigation on Scroll
    const bottomNav = document.querySelector('.mobile-bottom-nav');
    if (bottomNav) {
        let lastScrollY = window.scrollY;
        const scrollThreshold = 8; // min scroll distance to trigger

        window.addEventListener('scroll', () => {
            const currentScrollY = window.scrollY;
            const scrollDifference = currentScrollY - lastScrollY;

            // Don't trigger if scroll is close to top or close to bottom
            if (currentScrollY < 80) {
                bottomNav.classList.remove('mobile-bottom-nav--hidden');
                return;
            }

            // Prevent hiding if at the very bottom of the page
            if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 50) {
                bottomNav.classList.remove('mobile-bottom-nav--hidden');
                return;
            }

            if (Math.abs(scrollDifference) > scrollThreshold) {
                if (scrollDifference > 0) {
                    // Scrolling down - hide bottom nav
                    bottomNav.classList.add('mobile-bottom-nav--hidden');
                } else {
                    // Scrolling up - show bottom nav
                    bottomNav.classList.remove('mobile-bottom-nav--hidden');
                }
                lastScrollY = currentScrollY;
            }
        }, { passive: true });
    }

    // 2. Active Tab Tap Feedback for Mobile Nav
    const navItems = document.querySelectorAll('.mobile-bottom-nav .nav-item');
    navItems.forEach(item => {
        item.addEventListener('touchstart', function () {
            const icon = this.querySelector('i');
            const circle = this.querySelector('.sell-circle');
            if (circle) {
                circle.style.transform = 'scale(0.88)';
            } else if (icon) {
                icon.style.transform = 'scale(0.85)';
            }
        }, { passive: true });

        item.addEventListener('touchend', function () {
            const icon = this.querySelector('i');
            const circle = this.querySelector('.sell-circle');
            if (circle) {
                circle.style.transform = 'scale(1)';
            } else if (icon) {
                icon.style.transform = 'scale(1)';
            }
        }, { passive: true });
    });

    // 3. Category Carousel Smooth Horizontal Scrolling Swipe Feedback for mouse devices
    const categoryScroll = document.querySelector('.category-scroll-container');
    if (categoryScroll) {
        let isDown = false;
        let startX;
        let scrollLeft;

        categoryScroll.addEventListener('mousedown', (e) => {
            isDown = true;
            startX = e.pageX - categoryScroll.offsetLeft;
            scrollLeft = categoryScroll.scrollLeft;
            categoryScroll.style.scrollBehavior = 'auto'; // Disable smooth snap while dragging
        });
        categoryScroll.addEventListener('mouseleave', () => {
            isDown = false;
        });
        categoryScroll.addEventListener('mouseup', () => {
            isDown = false;
            categoryScroll.style.scrollBehavior = 'smooth';
        });
        categoryScroll.addEventListener('mousemove', (e) => {
            if (!isDown) return;
            e.preventDefault();
            const x = e.pageX - categoryScroll.offsetLeft;
            const walk = (x - startX) * 2; // Scroll multiplier
            categoryScroll.scrollLeft = scrollLeft - walk;
        });
    }
});
