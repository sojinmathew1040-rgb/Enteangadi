/**
 * Enteangadi - Marketplace Discovery Logic
 * Handles global wishlist toggling and card interactions.
 */

async function toggleWishlist(event, productId) {
    event.preventDefault();
    event.stopPropagation();

    // sessionUserId must be defined globally in index.php
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
                icon.classList.remove('far'); 
                icon.classList.add('fas'); 
                icon.classList.add('active');
                icon.style.color = 'var(--primary-green)';
            } else {
                icon.classList.remove('fas'); 
                icon.classList.add('far'); 
                icon.classList.remove('active');
                icon.style.color = '#94a3b8';
            }
        }
    } catch (e) { 
        console.error('Wishlist toggle failed:', e); 
    }
}
