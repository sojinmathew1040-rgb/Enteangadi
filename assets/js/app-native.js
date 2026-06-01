/**
 * Native integration for Capacitor Mobile Builds
 * Exposes window.EnteangadiMobile and manages Camera, Location, Mic, and Media permissions.
 */

// Inject Action Sheet CSS Styles dynamically for modularity
(function() {
    const style = document.createElement('style');
    style.innerHTML = `
    .mobile-action-sheet-backdrop {
        position: fixed;
        top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(15, 23, 42, 0.4);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        z-index: 99999;
        display: flex;
        align-items: flex-end;
        opacity: 0;
        pointer-events: none;
        transition: opacity 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .mobile-action-sheet-backdrop.active {
        opacity: 1;
        pointer-events: auto;
    }
    .mobile-action-sheet {
        width: 100%;
        background: var(--white, #ffffff);
        border-radius: 24px 24px 0 0;
        padding: 24px 24px 34px 24px; /* extra bottom padding for mobile safe area */
        box-shadow: 0 -8px 32px rgba(15, 23, 42, 0.15);
        transform: translateY(100%);
        transition: transform 0.3s cubic-bezier(0.1, 0.8, 0.3, 1);
        max-width: 500px;
        margin: 0 auto;
    }
    .mobile-action-sheet-backdrop.active .mobile-action-sheet {
        transform: translateY(0);
    }
    .mobile-action-sheet h3 {
        margin: 0 0 16px 0;
        font-size: 18px;
        font-weight: 700;
        text-align: center;
        color: var(--text-dark, #1e293b);
    }
    .mobile-action-sheet-btn {
        width: 100%;
        padding: 16px;
        margin-bottom: 12px;
        border: none;
        border-radius: 16px;
        font-size: 16px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 12px;
        cursor: pointer;
        transition: background 0.2s ease, transform 0.1s ease;
    }
    .mobile-action-sheet-btn.primary {
        background: linear-gradient(135deg, #2E7D32 0%, #1B5E20 100%);
        color: #ffffff;
    }
    .mobile-action-sheet-btn.secondary {
        background: var(--background, #f8fafc);
        color: var(--text-dark, #1e293b);
        border: 1px solid var(--border-color, #e2e8f0);
    }
    .mobile-action-sheet-btn.cancel {
        background: #fee2e2;
        color: #ef4444;
        margin-bottom: 0;
    }
    .mobile-action-sheet-btn:active {
        transform: scale(0.97);
    }
    
    /* Dark mode adjustment support */
    [data-theme="dark"] .mobile-action-sheet {
        background: #1e293b;
        box-shadow: 0 -8px 32px rgba(0, 0, 0, 0.3);
    }
    [data-theme="dark"] .mobile-action-sheet h3 {
        color: #f8fafc;
    }
    [data-theme="dark"] .mobile-action-sheet-btn.secondary {
        background: #0f172a;
        color: #cbd5e1;
        border-color: #334155;
    }
    [data-theme="dark"] .mobile-action-sheet-btn.cancel {
        background: rgba(239, 68, 68, 0.15);
        color: #fca5a5;
    }
    `;
    document.head.appendChild(style);
})();

// Expose unified Mobile Camera and Permissions manager
window.EnteangadiMobile = {
    /**
     * Checks if the app is currently running inside Capacitor WebView container.
     */
    isRunningInMobile: function() {
        return !!(window.Capacitor && window.Capacitor.Plugins);
    },
    
    /**
     * Automatically requests permissions for Location, Camera, Microphone (Mike), and Media files.
     */
    requestAllPermissions: async function() {
        if (!this.isRunningInMobile()) return;
        
        console.log("Initializing premium permission checks for mobile application...");
        
        // 1. Geolocation Native Permission request
        if (window.Capacitor.Plugins.Geolocation) {
            try {
                await window.Capacitor.Plugins.Geolocation.requestPermissions();
            } catch (e) {
                console.warn("Capacitor native Geolocation permission request error:", e);
            }
        }
        
        // 2. Camera and Photos Native Permission request
        if (window.Capacitor.Plugins.Camera) {
            try {
                await window.Capacitor.Plugins.Camera.requestPermissions();
            } catch (e) {
                console.warn("Capacitor native Camera/Photo Library permission request error:", e);
            }
        }
        
        // 3. Microphone & Camera via browser interface (automatically binds native container prompts)
        try {
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: true });
                stream.getTracks().forEach(track => track.stop()); // Immediately shut down to free hardware
            }
        } catch (e) {
            console.warn("Web API native media (Camera/Mic) permission mapping error:", e);
        }
    },
    
    /**
     * Helper to render Action Sheet slide-up menu on mobile viewports
     */
    showPhotoSourceSelection: function(onSuccess) {
        const backdrop = document.createElement('div');
        backdrop.className = 'mobile-action-sheet-backdrop';
        
        backdrop.innerHTML = `
            <div class="mobile-action-sheet">
                <h3>Select Image Source</h3>
                <button class="mobile-action-sheet-btn primary" id="btn-take-photo">
                    <i class="fa fa-camera"></i> Take Photo (Camera)
                </button>
                <button class="mobile-action-sheet-btn secondary" id="btn-choose-gallery">
                    <i class="fa fa-images"></i> Choose from Gallery
                </button>
                <button class="mobile-action-sheet-btn cancel" id="btn-cancel-sheet">
                    Cancel
                </button>
            </div>
        `;
        
        document.body.appendChild(backdrop);
        
        // Trigger smooth slide animation
        setTimeout(() => backdrop.classList.add('active'), 10);
        
        const closeSheet = () => {
            backdrop.classList.remove('active');
            setTimeout(() => backdrop.remove(), 300);
        };
        
        backdrop.querySelector('#btn-cancel-sheet').onclick = closeSheet;
        
        backdrop.querySelector('#btn-take-photo').onclick = async () => {
            closeSheet();
            await this.capturePhotoNatively('CAMERA', onSuccess);
        };
        
        backdrop.querySelector('#btn-choose-gallery').onclick = async () => {
            closeSheet();
            await this.capturePhotoNatively('PHOTOS', onSuccess);
        };
        
        backdrop.onclick = (e) => {
            if (e.target === backdrop) closeSheet();
        };
    },
    
    capturePhotoNatively: async function(sourceType, onSuccess) {
        try {
            if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.Camera) {
                // Secure camera/photos permissions first
                await window.Capacitor.Plugins.Camera.requestPermissions();
                
                const photo = await window.Capacitor.Plugins.Camera.getPhoto({
                    quality: 80,
                    allowEditing: false,
                    resultType: 'dataUrl', // return base64 dataUrl string
                    source: sourceType     // 'CAMERA' or 'PHOTOS'
                });
                
                if (photo && photo.dataUrl) {
                    onSuccess(photo.dataUrl);
                }
            } else {
                console.warn("Capacitor native Camera plugin is not linked. Falling back to default browser input picker.");
                // Fallback: Trigger standard click on input
                const isProfile = document.getElementById('profile_picture_input');
                const targetInput = (sourceType === 'CAMERA' && isProfile) ? isProfile : document.getElementById('images');
                if (targetInput) {
                    // Temporarily disable our interceptor to avoid infinite loop
                    const prevClick = targetInput.onclick;
                    targetInput.onclick = null;
                    targetInput.click();
                    // Restore after a short delay
                    setTimeout(() => {
                        targetInput.onclick = prevClick;
                    }, 500);
                }
            }
        } catch (err) {
            console.error("Error capturing native photo:", err);
        }
    }
};

// Convert base64 dataURL to standard HTML5 File object
function dataURLtoFile(dataurl, filename) {
    const arr = dataurl.split(',');
    const mime = arr[0].match(/:(.*?);/)[1];
    const bstr = atob(arr[1]);
    let n = bstr.length;
    const u8arr = new Uint8Array(n);
    while (n--) {
        u8arr[n] = bstr.charCodeAt(n);
    }
    return new File([u8arr], filename, { type: mime });
}

// Global Event Interceptor: Handle Mobile back actions & replace input clicks
document.addEventListener('DOMContentLoaded', () => {
    // 1. Back button management for Capacitor Shell
    if (window.Capacitor && window.Capacitor.Plugins) {
        const { App } = window.Capacitor.Plugins;
        if (App) {
            App.addListener('backButton', (data) => {
                if (data.canGoBack) {
                    window.history.back();
                } else {
                    App.exitApp();
                }
            });
            console.log('Capacitor Back Button Listener registered successfully.');
        }
    }
    
    // 2. Automate permission prompts on initial app launch
    if (window.EnteangadiMobile.isRunningInMobile()) {
        if (!sessionStorage.getItem('mobile-permissions-requested')) {
            setTimeout(() => {
                window.EnteangadiMobile.requestAllPermissions();
                sessionStorage.setItem('mobile-permissions-requested', 'true');
            }, 2500); // 2.5s delay to prevent UI splash stuttering
        }
        
        // Hijack inline clicks for Profile Picture Avatar Wrapper
        const avatarWrapper = document.querySelector('.profile-avatar-wrapper');
        if (avatarWrapper) {
            avatarWrapper.removeAttribute('onclick');
            avatarWrapper.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                window.EnteangadiMobile.showPhotoSourceSelection((dataUrl) => {
                    const file = dataURLtoFile(dataUrl, 'profile_picture.jpg');
                    const input = document.getElementById('profile_picture_input');
                    const form = document.getElementById('profile_pic_form');
                    if (input && form) {
                        const dt = new DataTransfer();
                        dt.items.add(file);
                        input.files = dt.files;
                        form.submit();
                    }
                });
            };
        }

        // Hijack inline clicks for Ad Media uploading card
        const addPhotoCard = document.querySelector('.add-photo-btn-card');
        if (addPhotoCard) {
            addPhotoCard.removeAttribute('onclick');
            addPhotoCard.onclick = (e) => {
                e.preventDefault();
                e.stopPropagation();
                window.EnteangadiMobile.showPhotoSourceSelection((dataUrl) => {
                    const file = dataURLtoFile(dataUrl, `photo_${Date.now()}.jpg`);
                    const input = document.getElementById('images');
                    if (input) {
                        const dt = new DataTransfer();
                        if (input.files) {
                            for (let i = 0; i < input.files.length; i++) {
                                dt.items.add(input.files[i]);
                            }
                        }
                        dt.items.add(file);
                        input.files = dt.files;
                        if (typeof previewImages === 'function') {
                            previewImages();
                        }
                    }
                });
            };
        }
    }
});

// Fallback dynamic click listener for late-rendered or dynamically loaded templates
document.addEventListener('click', (e) => {
    if (!window.EnteangadiMobile.isRunningInMobile()) return;
    
    // A. Intercept Profile picture upload
    const avatarWrapper = e.target.closest('.profile-avatar-wrapper');
    if (avatarWrapper && avatarWrapper.getAttribute('onclick')) {
        e.preventDefault();
        e.stopPropagation();
        avatarWrapper.removeAttribute('onclick'); // prevent repeating
        avatarWrapper.onclick = null; // clear
        
        window.EnteangadiMobile.showPhotoSourceSelection((dataUrl) => {
            const file = dataURLtoFile(dataUrl, 'profile_picture.jpg');
            const input = document.getElementById('profile_picture_input');
            const form = document.getElementById('profile_pic_form');
            if (input && form) {
                const dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
                form.submit();
            }
        });
        return;
    }
    
    // B. Intercept Ad Post/Edit photo card click
    const addPhotoCard = e.target.closest('.add-photo-btn-card');
    if (addPhotoCard && addPhotoCard.getAttribute('onclick')) {
        e.preventDefault();
        e.stopPropagation();
        addPhotoCard.removeAttribute('onclick'); // prevent repeating
        addPhotoCard.onclick = null; // clear
        
        window.EnteangadiMobile.showPhotoSourceSelection((dataUrl) => {
            const file = dataURLtoFile(dataUrl, `photo_${Date.now()}.jpg`);
            const input = document.getElementById('images');
            if (input) {
                const dt = new DataTransfer();
                if (input.files) {
                    for (let i = 0; i < input.files.length; i++) {
                        dt.items.add(input.files[i]);
                    }
                }
                dt.items.add(file);
                input.files = dt.files;
                if (typeof previewImages === 'function') {
                    previewImages();
                }
            }
        });
    }
});
