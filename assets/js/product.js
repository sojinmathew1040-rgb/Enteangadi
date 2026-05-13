/**
 * Enteangadi - Product Detail Logic
 * Handles image gallery sliders, lightbox interactions, and wishlist/sharing features.
 */

let currentIdx = 0;

function updateSliderCounter(el) {
    currentIdx = Math.round(el.scrollLeft / el.clientWidth);
    const counter = document.getElementById('currentImg');
    if (counter) counter.innerText = currentIdx + 1;
}

function scrollGallery(dir) {
    const slider = document.getElementById('mainCarousel');
    if (!slider) return;
    // imgCount must be defined globally
    if (typeof imgCount === 'undefined') return;
    currentIdx = Math.max(0, Math.min(imgCount - 1, currentIdx + dir));
    slider.scrollTo({ left: currentIdx * slider.clientWidth, behavior: 'smooth' });
}

function jumpToImage(idx) {
    const slider = document.getElementById('mainCarousel');
    if (!slider) return;
    currentIdx = idx;
    slider.scrollTo({ left: idx * slider.clientWidth, behavior: 'smooth' });
}

function openLightbox() {
    const modal = document.getElementById('lightbox');
    const gallery = document.getElementById('lightboxGallery');
    if (!modal || !gallery) return;

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    // Native Fullscreen API
    if (modal.requestFullscreen) {
        modal.requestFullscreen();
    } else if (modal.webkitRequestFullscreen) {
        modal.webkitRequestFullscreen();
    } else if (modal.msRequestFullscreen) {
        modal.msRequestFullscreen();
    }

    // Jump to the current image in the lightbox
    setTimeout(() => {
        gallery.scrollTo({ left: currentIdx * gallery.clientWidth, behavior: 'instant' });
        const lbCounter = document.getElementById('lbCurrent');
        if (lbCounter) lbCounter.innerText = currentIdx + 1;
    }, 10);
}

function closeLightbox() {
    const modal = document.getElementById('lightbox');
    if (!modal) return;
    modal.style.display = 'none';
    document.body.style.overflow = 'auto';

    // Exit Fullscreen
    if (document.fullscreenElement) {
        if (document.exitFullscreen) {
            document.exitFullscreen();
        } else if (document.webkitExitFullscreen) {
            document.webkitExitFullscreen();
        } else if (document.msExitFullscreen) {
            document.msExitFullscreen();
        }
    }
}

function updateLightboxCounter(el) {
    const idx = Math.round(el.scrollLeft / el.clientWidth);
    const lbCounter = document.getElementById('lbCurrent');
    if (lbCounter) lbCounter.innerText = idx + 1;
}

function scrollLightbox(dir) {
    const gallery = document.getElementById('lightboxGallery');
    if (!gallery) return;
    if (typeof imgCount === 'undefined') return;
    const idx = Math.round(gallery.scrollLeft / gallery.clientWidth);
    const nextIdx = Math.max(0, Math.min(imgCount - 1, idx + dir));
    gallery.scrollTo({ left: nextIdx * gallery.clientWidth, behavior: 'smooth' });
}

async function toggleWishlist(event, productId) {
    event.preventDefault();
    
    // sessionUserId must be defined globally
    if (typeof sessionUserId === 'undefined' || sessionUserId === null) {
        window.location.href = 'login.php';
        return;
    }

    const btn = event.currentTarget;
    const icon = btn.querySelector('i');

    try {
        const formData = new FormData();
        formData.append('product_id', productId);
        const resp = await fetch('user/toggle_wishlist.php', { method: 'POST', body: formData });
        const data = await resp.json();

        if (data.status === 'success') {
            if (data.action === 'added') {
                icon.classList.replace('far', 'fas');
                btn.classList.add('active');
            } else {
                icon.classList.replace('fas', 'far');
                btn.classList.remove('active');
            }
        }
    } catch (e) { console.error(e); }
}

function shareProduct() {
    if (navigator.share) {
        navigator.share({
            title: document.title,
            url: window.location.href
        });
    } else {
        const dummy = document.createElement('input');
        document.body.appendChild(dummy);
        dummy.value = window.location.href;
        dummy.select();
        document.execCommand('copy');
        document.body.removeChild(dummy);
        alert('Link copied to clipboard!');
    }
}
