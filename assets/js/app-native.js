/**
 * Native integration for Capacitor Mobile Builds
 * Exposes window.EnteangadiMobile and manages Camera, Location, Mic, and Media permissions.
 */

// Inject Action Sheet CSS Styles dynamically for modularity
(function () {
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
    
    /* Premium Phone/WhatsApp country selector styles */
    .phone-input-container {
        display: inline-flex;
        align-items: center;
        border: 1px solid var(--border-color, #e2e8f0);
        border-radius: 12px;
        background: var(--white, #ffffff);
        overflow: hidden;
        width: 100%;
        box-sizing: border-box;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
        margin-bottom: 8px;
    }
    .phone-input-container:focus-within {
        border-color: var(--primary-green, #2E7D32);
        box-shadow: 0 0 0 3px rgba(46, 125, 50, 0.15);
    }
    .country-code-select {
        border: none !important;
        background: transparent !important;
        padding: 12px 8px 12px 12px !important;
        font-size: 15px !important;
        font-weight: 600 !important;
        color: var(--text-dark, #1e293b) !important;
        outline: none !important;
        border-right: 1px solid var(--border-color, #e2e8f0) !important;
        cursor: pointer !important;
        width: 110px !important;
        min-width: 110px !important;
        box-sizing: border-box !important;
    }
    .phone-local-input {
        border: none !important;
        background: transparent !important;
        padding: 12px !important;
        font-size: 15px !important;
        outline: none !important;
        flex: 1 !important;
        width: 100% !important;
        box-sizing: border-box !important;
        color: var(--text-dark, #1e293b) !important;
    }
    [data-theme="dark"] .phone-input-container {
        background: #0f172a;
        border-color: #334155;
    }
    [data-theme="dark"] .country-code-select {
        border-right-color: #334155 !important;
        color: #f8fafc !important;
    }
    [data-theme="dark"] .phone-local-input {
        color: #f8fafc !important;
    }
    `;
    document.head.appendChild(style);
})();

// Expose unified Mobile Camera and Permissions manager
window.EnteangadiMobile = {
    /**
     * Checks if the app is currently running inside Capacitor WebView container.
     */
    isRunningInMobile: function () {
        return !!(window.Capacitor && window.Capacitor.Plugins);
    },

    /**
     * Request Notification permissions for both mobile push/local notifications and web standard notifications
     */
    requestNotificationPermission: async function () {
        if (this.isRunningInMobile() && window.Capacitor.Plugins.LocalNotifications) {
            try {
                const result = await window.Capacitor.Plugins.LocalNotifications.requestPermissions();
                console.log("Capacitor local notifications permission result:", result);
                return result.display === 'granted';
            } catch (e) {
                console.warn("Capacitor native LocalNotifications permission request error:", e);
                return false;
            }
        } else if (typeof Notification !== 'undefined') {
            try {
                const permission = await Notification.requestPermission();
                console.log("Web standard notification permission status:", permission);
                return permission === 'granted';
            } catch (e) {
                console.warn("Web standard notification permission request error:", e);
                return false;
            }
        }
        return false;
    },

    /**
     * Trigger a native or standard web notification
     */
    showLocalNotification: function (senderName, messageText) {
        // App logo resolution (fallback to logo_1778137117.jpg if app_logo setting not specified)
        let logoUrl = '/Enteangadi/uploads/logo/logo_1778137117.jpg';
        if (typeof EnteangadiConfig !== 'undefined' && EnteangadiConfig.baseUrl !== undefined) {
            const logoPath = EnteangadiConfig.appLogo || 'uploads/logo/logo_1778137117.jpg';
            logoUrl = (EnteangadiConfig.baseUrl ? EnteangadiConfig.baseUrl + '/' : '/') + logoPath;
        }

        // Clean up text if it is voice note or shared photo
        let bodyText = messageText || '';
        if (bodyText.startsWith('[AUDIO]:')) {
            bodyText = '🎙️ Voice note';
        } else if (bodyText.startsWith('[IMAGE]:')) {
            bodyText = '📷 Shared photo';
        }

        if (this.isRunningInMobile() && window.Capacitor.Plugins.LocalNotifications) {
            try {
                window.Capacitor.Plugins.LocalNotifications.schedule({
                    notifications: [{
                        title: "Enteangadi - " + senderName,
                        body: bodyText,
                        id: Math.floor(Math.random() * 1000000),
                        schedule: { at: new Date(Date.now() + 100) },
                        sound: "default",
                        smallIcon: "res://ic_stat_logo",
                        largeIcon: "res://ic_launcher"
                    }]
                });
                console.log("Capacitor local notification scheduled successfully.");
            } catch (err) {
                console.error("Failed to schedule Capacitor native local notification:", err);
            }
        } else if (typeof Notification !== 'undefined') {
            if (Notification.permission === 'granted') {
                new Notification("Enteangadi - " + senderName, {
                    body: bodyText,
                    icon: logoUrl
                });
            } else if (Notification.permission === 'default') {
                Notification.requestPermission().then(perm => {
                    if (perm === 'granted') {
                        new Notification("Enteangadi - " + senderName, {
                            body: bodyText,
                            icon: logoUrl
                        });
                    }
                });
            }
        }
    },

    /**
     * Automatically requests permissions for Location, Camera, Microphone (Mike), Media files, and Notifications.
     */
    requestAllPermissions: async function () {
        // Request Web notification permissions first if not in mobile wrapper
        if (!this.isRunningInMobile()) {
            await this.requestNotificationPermission();
            return;
        }

        console.log("Initializing premium permission checks for mobile application...");

        // 0. Notification Native Permission request
        await this.requestNotificationPermission();

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

        // 3. Microphone native mapping via custom plugin if in mobile wrapper
        if (this.isRunningInMobile() && window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.MicrophonePermission) {
            try {
                await window.Capacitor.Plugins.MicrophonePermission.checkPermission();
            } catch (e) {
                console.warn("Capacitor custom microphone permission request error:", e);
            }
        }

        // 4. Microphone native mapping via browser interface
        try {
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
                stream.getTracks().forEach(track => track.stop()); // Immediately shut down to free hardware
            }
        } catch (e) {
            console.warn("Web API native microphone permission mapping error:", e);
        }

        // 5. Camera native mapping via browser interface
        try {
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                const stream = await navigator.mediaDevices.getUserMedia({ video: true });
                stream.getTracks().forEach(track => track.stop()); // Immediately shut down to free hardware
            }
        } catch (e) {
            console.warn("Web API native camera permission mapping error:", e);
        }
    },

    /**
     * Start voice note recording natively.
     */
    startRecording: async function () {
        if (this.isRunningInMobile() && window.Capacitor.Plugins.MicrophonePermission) {
            return await window.Capacitor.Plugins.MicrophonePermission.startRecording();
        }
        throw new Error("Native recording not available");
    },

    /**
     * Stop voice note recording natively.
     */
    stopRecording: async function () {
        if (this.isRunningInMobile() && window.Capacitor.Plugins.MicrophonePermission) {
            return await window.Capacitor.Plugins.MicrophonePermission.stopRecording();
        }
        throw new Error("Native recording not available");
    },

    /**
     * Helper to render Action Sheet slide-up menu on mobile viewports
     */
    showPhotoSourceSelection: function (onSuccess, showDelete = false, onDelete = null, isMultiple = false, targetInput = null) {
        const backdrop = document.createElement('div');
        backdrop.className = 'mobile-action-sheet-backdrop';

        let deleteBtnHtml = '';
        if (showDelete) {
            deleteBtnHtml = `
                <button class="mobile-action-sheet-btn cancel" id="btn-delete-photo" style="background: #fee2e2; color: #ef4444; margin-bottom: 12px;">
                    <i class="fa fa-trash-alt"></i> Delete Profile Picture
                </button>
            `;
        }

        backdrop.innerHTML = `
            <div class="mobile-action-sheet">
                <h3>Select Image Source</h3>
                <button class="mobile-action-sheet-btn primary" id="btn-take-photo">
                    <i class="fa fa-camera"></i> Take Photo (Camera)
                </button>
                <button class="mobile-action-sheet-btn secondary" id="btn-choose-gallery">
                    <i class="fa fa-images"></i> Choose from Gallery
                </button>
                ${deleteBtnHtml}
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
            await this.capturePhotoNatively('CAMERA', onSuccess, isMultiple, targetInput);
        };

        backdrop.querySelector('#btn-choose-gallery').onclick = () => {
            closeSheet();
            this.triggerInputFallback(targetInput);
        };

        if (showDelete && onDelete) {
            const delBtn = backdrop.querySelector('#btn-delete-photo');
            if (delBtn) {
                delBtn.onclick = () => {
                    closeSheet();
                    onDelete();
                };
            }
        }

        backdrop.onclick = (e) => {
            if (e.target === backdrop) closeSheet();
        };
    },

    /**
     * Helper to read native photo details (via path conversion) and convert to dataURL
     * This is crucial when the app is hosted on a remote server/origin, as standard
     * file/content paths fail CORS validation in Capacitor.
     */
    readPhotoAsDataURL: async function (photo) {
        const path = photo.path || photo.webPath;
        if (!path) {
            throw new Error("No valid path found for photo");
        }

        // Try reading natively via Capacitor Filesystem plugin to bypass webview HTTP fetch issues on remote server
        if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.Filesystem && photo.path) {
            try {
                const fsResult = await window.Capacitor.Plugins.Filesystem.readFile({
                    path: photo.path
                });
                if (fsResult && fsResult.data) {
                    const mimeType = photo.format ? `image/${photo.format}` : 'image/jpeg';
                    return `data:${mimeType};base64,${fsResult.data}`;
                }
            } catch (fsErr) {
                console.warn("Capacitor Filesystem.readFile failed, falling back to fetch...", fsErr);
            }
        }
        
        let convertedUrl = window.Capacitor.convertFileSrc(path);
        
        // Remove sub-directory prefixes (like /enteangadi/) before _capacitor_file_
        // This is crucial because Capacitor's native interceptor only matches paths starting with /_capacitor_file_
        if (convertedUrl.includes('/_capacitor_file_')) {
            try {
                const urlObj = new URL(convertedUrl);
                const idx = urlObj.pathname.indexOf('/_capacitor_file_');
                if (idx !== -1 && idx > 0) {
                    urlObj.pathname = urlObj.pathname.substring(idx);
                    convertedUrl = urlObj.toString();
                }
            } catch (e) {
                console.error("Url parsing error in converter:", e);
            }
        }

        const response = await fetch(convertedUrl);
        if (!response.ok) {
            throw new Error(`Failed to fetch local path URL: ${response.statusText}`);
        }
        const blob = await response.blob();
        if (blob.size === 0) {
            throw new Error("Opaque or 0-byte blob returned from path fetch");
        }
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onloadend = () => resolve(reader.result);
            reader.onerror = reject;
            reader.readAsDataURL(blob);
        });
    },

    capturePhotoNatively: async function (sourceType, onSuccess, isMultiple = false, targetInput = null) {
        try {
            if (window.Capacitor && window.Capacitor.Plugins && window.Capacitor.Plugins.Camera) {
                // Secure specific camera/photos permissions based on source type for API 33+ compatibility
                try {
                    if (sourceType === 'PHOTOS') {
                        await window.Capacitor.Plugins.Camera.requestPermissions({ permissions: ['photos'] });
                    } else {
                        await window.Capacitor.Plugins.Camera.requestPermissions({ permissions: ['camera', 'photos'] });
                    }
                } catch (permErr) {
                    console.warn("Camera.requestPermissions with options failed, trying generic requestPermissions...", permErr);
                    try {
                        await window.Capacitor.Plugins.Camera.requestPermissions();
                    } catch (e) {
                        console.error("Generic requestPermissions failed:", e);
                    }
                }

                if (sourceType === 'PHOTOS' && isMultiple) {
                    try {
                        const result = await window.Capacitor.Plugins.Camera.pickImages({
                            quality: 80
                        });
                        if (result && result.photos && result.photos.length > 0) {
                            const dataUrls = [];
                            for (const photo of result.photos) {
                                try {
                                    const dataUrl = await window.EnteangadiMobile.readPhotoAsDataURL(photo);
                                    dataUrls.push(dataUrl);
                                } catch (readErr) {
                                    console.error("Failed to read photo as data URL:", readErr);
                                }
                            }
                            if (dataUrls.length > 0) {
                                onSuccess(dataUrls);
                                return;
                            }
                        }
                        throw new Error("No photos could be successfully read or selected.");
                    } catch (pickErr) {
                        console.error("Capacitor pickImages failed or returned no photos, falling back to input picker...", pickErr);
                        this.triggerInputFallback(targetInput);
                    }
                    return;
                }

                try {
                    // For single select (profile pic) or camera captures, use native Camera.getPhoto with dataUrl to bypass CORS/mixed-content issues
                    const photo = await window.Capacitor.Plugins.Camera.getPhoto({
                        quality: 80,
                        allowEditing: false,
                        resultType: 'dataUrl', // Base64 data URL bypasses the need for local webPath/file fetches
                        source: sourceType // 'CAMERA' or 'PHOTOS'
                    });

                    if (photo && photo.dataUrl) {
                        onSuccess(photo.dataUrl);
                    } else {
                        throw new Error("No photo data URL returned.");
                    }
                } catch (photoErr) {
                    console.error("Capacitor getPhoto failed...", photoErr);
                    const errMsg = (photoErr && photoErr.message) || String(photoErr);
                    if (/cancel/i.test(errMsg)) {
                        console.log("User cancelled camera operation, not falling back.");
                    } else {
                        console.log("Capacitor getPhoto error, falling back to input picker...");
                        this.triggerInputFallback(targetInput);
                    }
                }
            } else {
                console.warn("Capacitor native Camera plugin is not linked. Falling back to default browser input picker.");
                this.triggerInputFallback(targetInput);
            }
        } catch (err) {
            console.error("Error capturing native photo:", err);
            this.triggerInputFallback(targetInput);
        }
    },

    triggerInputFallback: function (targetInput = null) {
        let input = targetInput;
        if (typeof input === 'string') {
            input = document.getElementById(input);
        }
        if (!input) {
            input = document.getElementById('profile_picture_input') || document.getElementById('images');
        }
        if (input) {
            // Temporarily make it block & visible with opacity 0 to bypass WebView programmatic click security
            const originalDisplay = input.style.display;
            const originalVisibility = input.style.visibility;
            const originalOpacity = input.style.opacity;
            const originalPosition = input.style.position;
            const originalWidth = input.style.width;
            const originalHeight = input.style.height;

            input.style.display = 'block';
            input.style.visibility = 'visible';
            input.style.opacity = '0';
            input.style.position = 'absolute';
            input.style.width = '1px';
            input.style.height = '1px';

            const prevClick = input.onclick;
            input.onclick = null;
            input.click();
            setTimeout(() => {
                input.onclick = prevClick;
                input.style.display = originalDisplay;
                input.style.visibility = originalVisibility;
                input.style.opacity = originalOpacity;
                input.style.position = originalPosition;
                input.style.width = originalWidth;
                input.style.height = originalHeight;
            }, 500);
        }
    }
};

// Convert base64 dataURL to standard HTML5 File object
function dataURLtoFile(dataurl, filename) {
    if (!dataurl) return null;
    let arr, mime, bstr;
    if (dataurl.includes(',')) {
        arr = dataurl.split(',');
        const match = arr[0].match(/:(.*?);/);
        mime = match ? match[1] : 'image/jpeg';
        bstr = atob(arr[1]);
    } else {
        mime = 'image/jpeg';
        bstr = atob(dataurl);
    }
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
    }

    // Hijack inline clicks for Ad Media uploading card on both desktop and mobile
    const addPhotoCards = document.querySelectorAll('.add-photo-btn-card');
    addPhotoCards.forEach(addPhotoCard => {
        addPhotoCard.removeAttribute('onclick');
        addPhotoCard.onclick = (e) => {
            // Prevent hijacking clicks originating directly from input elements
            if (e.target && e.target.tagName === 'INPUT') {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            window.EnteangadiMobile.showPhotoSourceSelection((dataUrls) => {
                const urls = Array.isArray(dataUrls) ? dataUrls : [dataUrls];
                const input = document.getElementById('images');
                if (input) {
                    const dt = new DataTransfer();
                    if (input.files) {
                        for (let i = 0; i < input.files.length; i++) {
                            dt.items.add(input.files[i]);
                        }
                    }
                    urls.forEach((dataUrl, idx) => {
                        const filename = `photo_${Date.now()}_${idx}.jpg`;
                        const file = dataURLtoFile(dataUrl, filename);
                        file.dataURL = dataUrl;
                        window.EnteangadiImageCache = window.EnteangadiImageCache || {};
                        window.EnteangadiImageCache[filename] = dataUrl;
                        dt.items.add(file);
                    });
                    input.files = dt.files;
                    if (typeof previewImages === 'function') {
                        previewImages();
                    }
                }
            }, false, null, true, 'images');
        };
    });

    // Hijack inline clicks for Profile Picture Avatar Wrapper on both desktop and mobile
    const avatarWrappers = document.querySelectorAll('.profile-avatar-wrapper');
    avatarWrappers.forEach(avatarWrapper => {
        avatarWrapper.removeAttribute('onclick');
        avatarWrapper.onclick = (e) => {
            // Prevent hijacking clicks originating directly from input elements
            if (e.target && e.target.tagName === 'INPUT') {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            const hasPhoto = !!document.getElementById('profile-avatar-img');
            window.EnteangadiMobile.showPhotoSourceSelection((dataUrls) => {
                const urls = Array.isArray(dataUrls) ? dataUrls : [dataUrls];
                if (urls.length === 0) return;
                if (typeof window.startProfileCropper === 'function') {
                    window.startProfileCropper(urls[0]);
                    return;
                }
                const file = dataURLtoFile(urls[0], 'profile_picture.jpg');
                const input = document.getElementById('profile_picture_input');
                const form = document.getElementById('profile_pic_form');
                if (input && form) {
                    const dt = new DataTransfer();
                    dt.items.add(file);
                    input.files = dt.files;
                    form.submit();
                }
            }, hasPhoto, () => {
                if (typeof deleteProfilePicture === 'function') {
                    deleteProfilePicture();
                }
            }, false, 'profile_picture_input');
        };
    });
});

// Fallback dynamic click listener for late-rendered or dynamically loaded templates
document.addEventListener('click', (e) => {
    // A. Intercept Profile picture upload
    const avatarWrapper = e.target.closest('.profile-avatar-wrapper');
    if (avatarWrapper && avatarWrapper.getAttribute('onclick')) {
        if (e.target && e.target.tagName === 'INPUT') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        avatarWrapper.removeAttribute('onclick'); // prevent repeating
        avatarWrapper.onclick = null; // clear

        const hasPhoto = !!document.getElementById('profile-avatar-img');
        window.EnteangadiMobile.showPhotoSourceSelection((dataUrls) => {
            const urls = Array.isArray(dataUrls) ? dataUrls : [dataUrls];
            if (urls.length === 0) return;
            if (typeof window.startProfileCropper === 'function') {
                window.startProfileCropper(urls[0]);
                return;
            }
            const file = dataURLtoFile(urls[0], 'profile_picture.jpg');
            const input = document.getElementById('profile_picture_input');
            const form = document.getElementById('profile_pic_form');
            if (input && form) {
                const dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
                form.submit();
            }
        }, hasPhoto, () => {
            if (typeof deleteProfilePicture === 'function') {
                deleteProfilePicture();
            }
        }, false, 'profile_picture_input');
        return;
    }

    // B. Intercept Ad Post/Edit photo card click
    const addPhotoCard = e.target.closest('.add-photo-btn-card');
    if (addPhotoCard && addPhotoCard.getAttribute('onclick')) {
        if (e.target && e.target.tagName === 'INPUT') {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        addPhotoCard.removeAttribute('onclick'); // prevent repeating
        addPhotoCard.onclick = null; // clear

        window.EnteangadiMobile.showPhotoSourceSelection((dataUrls) => {
            const urls = Array.isArray(dataUrls) ? dataUrls : [dataUrls];
            const input = document.getElementById('images');
            if (input) {
                const dt = new DataTransfer();
                if (input.files) {
                    for (let i = 0; i < input.files.length; i++) {
                        dt.items.add(input.files[i]);
                    }
                }
                urls.forEach((dataUrl, idx) => {
                    const filename = `photo_${Date.now()}_${idx}.jpg`;
                    const file = dataURLtoFile(dataUrl, filename);
                    file.dataURL = dataUrl;
                    window.EnteangadiImageCache = window.EnteangadiImageCache || {};
                    window.EnteangadiImageCache[filename] = dataUrl;
                    dt.items.add(file);
                });
                input.files = dt.files;
                if (typeof previewImages === 'function') {
                    previewImages();
                }
            }
        }, false, null, true, 'images');
    }
});

// Premium Country Code Selector Implementation
(function () {
    const countryCodes = [
        { code: "91", country: "IN", name: "India", flag: "🇮🇳" },
        { code: "971", country: "AE", name: "United Arab Emirates", flag: "🇦🇪" },
        { code: "966", country: "SA", name: "Saudi Arabia", flag: "🇸🇦" },
        { code: "968", country: "OM", name: "Oman", flag: "🇴🇲" },
        { code: "974", country: "QA", name: "Qatar", flag: "🇶🇦" },
        { code: "965", country: "KW", name: "Kuwait", flag: "🇰🇼" },
        { code: "973", country: "BH", name: "Bahrain", flag: "🇧🇭" },
        { code: "1", country: "US", name: "United States", flag: "🇺🇸" },
        { code: "44", country: "GB", name: "United Kingdom", flag: "🇬🇧" },
        { code: "61", country: "AU", name: "Australia", flag: "🇦🇺" },
        { code: "65", country: "SG", name: "Singapore", flag: "🇸🇬" },
        { code: "60", country: "MY", name: "Malaysia", flag: "🇲🇾" },
        { code: "1", country: "CA", name: "Canada", flag: "🇨🇦" },
        { code: "33", country: "FR", name: "France", flag: "🇫🇷" },
        { code: "49", country: "DE", name: "Germany", flag: "🇩🇪" },
        { code: "81", country: "JP", name: "Japan", flag: "🇯🇵" },
        { code: "86", country: "CN", name: "China", flag: "🇨🇳" },
        { code: "39", country: "IT", name: "Italy", flag: "🇮🇹" },
        { code: "7", country: "RU", name: "Russia", flag: "🇷🇺" },
        { code: "34", country: "ES", name: "Spain", flag: "🇪🇸" },
        { code: "55", country: "BR", name: "Brazil", flag: "🇧🇷" },
        { code: "27", country: "ZA", name: "South Africa", flag: "🇿🇦" },
        { code: "92", country: "PK", name: "Pakistan", flag: "🇵🇰" },
        { code: "880", country: "BD", name: "Bangladesh", flag: "🇧🇩" },
        { code: "94", country: "LK", name: "Sri Lanka", flag: "🇱🇰" },
        { code: "977", country: "NP", name: "Nepal", flag: "🇳🇵" },
        { code: "62", country: "ID", name: "Indonesia", flag: "🇮🇩" },
        { code: "63", country: "PH", name: "Philippines", flag: "🇵🇭" },
        { code: "64", country: "NZ", name: "New Zealand", flag: "🇳🇿" },
        { code: "353", country: "IE", name: "Ireland", flag: "🇮🇪" },
        { code: "41", country: "CH", name: "Switzerland", flag: "🇨🇭" },
        { code: "31", country: "NL", name: "Netherlands", flag: "🇳🇱" },
        { code: "46", country: "SE", name: "Sweden", flag: "🇸🇪" },
        { code: "47", country: "NO", name: "Norway", flag: "🇳🇴" },
        { code: "45", country: "DK", name: "Denmark", flag: "🇩🇰" },
        { code: "32", country: "BE", name: "Belgium", flag: "🇧🇪" },
        { code: "43", country: "AT", name: "Austria", flag: "🇦🇹" },
        { code: "351", country: "PT", name: "Portugal", flag: "🇵🇹" },
        { code: "30", country: "GR", name: "Greece", flag: "🇬🇷" },
        { code: "90", country: "TR", name: "Turkey", flag: "🇹🇷" },
        { code: "20", country: "EG", name: "Egypt", flag: "🇪🇬" },
        { code: "234", country: "NG", name: "Nigeria", flag: "🇳🇬" },
        { code: "254", country: "KE", name: "Kenya", flag: "🇰🇪" },
        { code: "255", country: "TZ", name: "Tanzania", flag: "🇹🇿" },
        { code: "256", country: "UG", name: "Uganda", flag: "🇺🇬" },
        { code: "212", country: "MA", name: "Morocco", flag: "🇲🇦" },
        { code: "213", country: "DZ", name: "Algeria", flag: "🇩🇿" },
        { code: "216", country: "TN", name: "Tunisia", flag: "🇹🇳" },
        { code: "218", country: "LY", name: "Libya", flag: "🇱🇾" },
        { code: "962", country: "JO", name: "Jordan", flag: "🇯🇴" },
        { code: "961", country: "LB", name: "Lebanon", flag: "🇱🇧" },
        { code: "963", country: "SY", name: "Syria", flag: "🇸🇾" },
        { code: "964", country: "IQ", name: "Iraq", flag: "🇮🇶" },
        { code: "967", country: "YE", name: "Yemen", flag: "🇾🇪" },
        { code: "98", country: "IR", name: "Iran", flag: "🇮🇷" },
        { code: "972", country: "IL", name: "Israel", flag: "🇮🇱" },
        { code: "95", country: "MM", name: "Myanmar", flag: "🇲🇲" },
        { code: "66", country: "TH", name: "Thailand", flag: "🇹🇭" },
        { code: "84", country: "VN", name: "Vietnam", flag: "🇻🇳" },
        { code: "82", country: "KR", name: "South Korea", flag: "🇰🇷" },
        { code: "852", country: "HK", name: "Hong Kong", flag: "🇭🇰" },
        { code: "886", country: "TW", name: "Taiwan", flag: "🇹🇼" },
        { code: "380", country: "UA", name: "Ukraine", flag: "🇺🇦" },
        { code: "48", country: "PL", name: "Poland", flag: "🇵🇱" },
        { code: "40", country: "RO", name: "Romania", flag: "🇷🇴" },
        { code: "36", country: "HU", name: "Hungary", flag: "🇭🇺" },
        { code: "420", country: "CZ", name: "Czech Republic", flag: "🇨🇿" },
        { code: "358", country: "FI", name: "Finland", flag: "🇫🇮" }
    ];

    let detectedCountryCode = null;
    async function getAutoDetectedCountryCode() {
        if (detectedCountryCode) return detectedCountryCode;
        try {
            const response = await fetch('https://ipapi.co/json/');
            const data = await response.json();
            if (data && data.country_calling_code) {
                detectedCountryCode = data.country_calling_code.replace('+', '');
                return detectedCountryCode;
            }
        } catch (e) {
            console.warn("ipapi.co failed, trying ipwho.is:", e);
        }
        try {
            const response = await fetch('https://ipwho.is/');
            const data = await response.json();
            if (data && data.calling_code) {
                detectedCountryCode = data.calling_code;
                return detectedCountryCode;
            }
        } catch (e) {
            console.warn("ipwho.is failed:", e);
        }
        return "91"; // Default fallback
    }

    async function convertPhoneInput(originalInput) {
        if (!originalInput || originalInput.dataset.converted === "true") return;
        originalInput.dataset.converted = "true";

        const container = document.createElement('div');
        container.className = 'phone-input-container';

        const select = document.createElement('select');
        select.className = 'country-code-select';

        const sortedCountryCodes = [...countryCodes].sort((a, b) => b.code.length - a.code.length);

        countryCodes.forEach(cc => {
            const option = document.createElement('option');
            option.value = cc.code;
            option.innerText = `${cc.flag} +${cc.code}`;
            select.appendChild(option);
        });

        const localInput = document.createElement('input');
        localInput.type = 'tel';
        localInput.className = 'phone-local-input';
        localInput.placeholder = originalInput.placeholder || 'Local phone number';

        container.appendChild(select);
        container.appendChild(localInput);

        originalInput.style.display = 'none';
        originalInput.parentNode.insertBefore(container, originalInput.nextSibling);

        let initialValue = originalInput.value.trim().replace(/\D/g, '');
        let matchedCode = "91";
        let localVal = initialValue;

        if (initialValue.length > 0) {
            for (const cc of sortedCountryCodes) {
                if (initialValue.startsWith(cc.code)) {
                    const remaining = initialValue.substring(cc.code.length);
                    if (remaining.length >= 6) {
                        matchedCode = cc.code;
                        localVal = remaining;
                        break;
                    }
                }
            }
        } else {
            matchedCode = await getAutoDetectedCountryCode();
        }

        select.value = matchedCode;
        localInput.value = localVal;

        const updateOriginalValue = () => {
            const cleanLocal = localInput.value.replace(/\D/g, '');
            if (cleanLocal) {
                originalInput.value = select.value + cleanLocal;
            } else {
                originalInput.value = '';
            }
        };

        updateOriginalValue();

        select.addEventListener('change', updateOriginalValue);
        localInput.addEventListener('input', updateOriginalValue);

        // Sync required property from original input to local input
        localInput.required = originalInput.required;
        Object.defineProperty(originalInput, 'required', {
            get: function () {
                return localInput.required;
            },
            set: function (val) {
                localInput.required = val;
            },
            configurable: true
        });

        // Mirror style changes (like validation error borders)
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.attributeName === 'style') {
                    const borderColor = originalInput.style.borderColor;
                    if (borderColor) {
                        container.style.borderColor = borderColor;
                    }
                }
            });
        });
        observer.observe(originalInput, { attributes: true, attributeFilter: ['style'] });
    }

    function initPhoneInputs() {
        const targets = document.querySelectorAll('input[type="tel"]');
        targets.forEach(input => {
            if (input.id.includes('phone') || input.id.includes('whatsapp') || input.name.includes('phone') || input.name.includes('whatsapp')) {
                convertPhoneInput(input);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPhoneInputs);
    } else {
        initPhoneInputs();
    }
})();
