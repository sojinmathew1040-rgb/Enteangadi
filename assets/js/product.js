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

async function getProductImageFile(imageUrl) {
    try {
        const response = await fetch(imageUrl);
        const blob = await response.blob();
        const filename = imageUrl.substring(imageUrl.lastIndexOf('/') + 1) || 'product.jpg';
        return new File([blob], filename, { type: blob.type });
    } catch (e) {
        console.error("Failed to fetch image file for sharing", e);
        return null;
    }
}

let currentShareUrl = '';

async function shareProduct(productId, productTitle) {
    // 1. Build the share URL
    let shareUrl = window.location.origin + window.location.pathname + '?id=' + productId;
    
    // 2. If running inside the mobile app wrapper, append &shared_from=app
    if (window.EnteangadiMobile && window.EnteangadiMobile.isRunningInMobile()) {
        shareUrl += '&shared_from=app';
    }

    currentShareUrl = shareUrl;

    const firstImgUrl = document.querySelector('.carousel-item-premium img')?.src;

    // 3. Try to share via native Web Share API
    if (navigator.share) {
        try {
            const shareData = {
                title: productTitle || document.title,
                text: "Check out this product on Enteangadi: " + (productTitle || document.title) + "\n" + shareUrl,
                url: shareUrl
            };

            if (firstImgUrl) {
                const file = await getProductImageFile(firstImgUrl);
                if (file && navigator.canShare && navigator.canShare({ files: [file] })) {
                    shareData.files = [file];
                }
            }

            await navigator.share(shareData);
            console.log("Shared successfully");
            return;
        } catch (err) {
            console.log("Native share failed or dismissed, falling back to modal", err);
        }
    }

    // Show fallback custom share modal
    openCustomShare(shareUrl, productTitle || document.title);
}

function openCustomShare(url, title) {
    const modal = document.getElementById('customShareModal');
    if (!modal) return;

    // Construct premium Manglish WhatsApp Share Card text
    const priceText = document.querySelector('.price-value')?.innerText.trim() || '';
    const locText = document.querySelector('.meta-tag.location-interactive')?.innerText.trim() || 'Kerala';
    
    let manglishMsg = `*Enteangadi Local listing* 🛍️🌟\n\n`;
    manglishMsg += `*Item:* ${title}\n`;
    if (priceText) manglishMsg += `*Price:* ${priceText}\n`;
    manglishMsg += `*Location:* ${locText}\n\n`;
    manglishMsg += `Nalla smart deal aanu! 🥳 Nokki thiranj vaangan click cheyoo:\n`;
    manglishMsg += `${url}`;
    
    const textMsg = encodeURIComponent(manglishMsg);
    const whatsapp = document.getElementById('share-whatsapp');
    const telegram = document.getElementById('share-telegram');
    const facebook = document.getElementById('share-facebook');
    const twitter = document.getElementById('share-twitter');
    const linkedin = document.getElementById('share-linkedin');
    const email = document.getElementById('share-email');

    if (whatsapp) whatsapp.href = "https://api.whatsapp.com/send?text=" + textMsg;
    if (telegram) telegram.href = "https://telegram.me/share/url?url=" + encodeURIComponent(url) + "&text=" + encodeURIComponent(title);
    if (facebook) facebook.href = "https://www.facebook.com/sharer/sharer.php?u=" + encodeURIComponent(url);
    if (twitter) twitter.href = "https://twitter.com/intent/tweet?url=" + encodeURIComponent(url) + "&text=" + encodeURIComponent(title);
    if (linkedin) linkedin.href = "https://www.linkedin.com/sharing/share-offsite/?url=" + encodeURIComponent(url);
    if (email) email.href = "mailto:?subject=" + encodeURIComponent(title) + "&body=" + textMsg;

    modal.style.display = 'flex';
    setTimeout(() => {
        modal.classList.add('active');
    }, 10);
}

function closeCustomShare() {
    const modal = document.getElementById('customShareModal');
    if (!modal) return;
    modal.classList.remove('active');
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
}

function copyShareLink() {
    if (!currentShareUrl) return;
    const dummy = document.createElement('input');
    document.body.appendChild(dummy);
    dummy.value = currentShareUrl;
    dummy.select();
    try {
        document.execCommand('copy');
        const copyBtn = document.querySelector('.custom-share-item.copy-link span');
        const origText = copyBtn ? copyBtn.innerText : 'Copy Link';
        if (copyBtn) {
            copyBtn.innerText = 'Copied!';
            copyBtn.style.color = 'var(--primary-green)';
            setTimeout(() => {
                copyBtn.innerText = origText;
                copyBtn.style.color = '';
            }, 2000);
        } else {
            alert('Link copied to clipboard!');
        }
    } catch (e) {
        console.error('Failed to copy to clipboard', e);
    } finally {
        document.body.removeChild(dummy);
    }
}

// Redirect overlay logic for Android user opening a mobile-app shared link
function checkAppRedirect() {
    const params = new URLSearchParams(window.location.search);
    if (params.get('shared_from') === 'app') {
        const isAndroid = /android/i.test(navigator.userAgent);
        if (isAndroid) {
            const redirectModal = document.getElementById('appRedirectModal');
            const installBtn = document.getElementById('btn-redirect-install');
            
            if (redirectModal) {
                if (installBtn && typeof playStoreUrl !== 'undefined') {
                    installBtn.href = playStoreUrl;
                }
                redirectModal.style.display = 'flex';
                setTimeout(() => {
                    redirectModal.classList.add('active');
                }, 10);
            }
        }
    }
}

function closeRedirectModal() {
    const redirectModal = document.getElementById('appRedirectModal');
    if (!redirectModal) return;
    redirectModal.classList.remove('active');
    setTimeout(() => {
        redirectModal.style.display = 'none';
    }, 300);
}

// Register redirect check on page load
document.addEventListener('DOMContentLoaded', checkAppRedirect);
