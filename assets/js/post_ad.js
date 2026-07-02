/**
 * Enteangadi - Ad Posting Logic
 * Handles multi-step form navigation, category updates, location detection, and image previews.
 */

let currentStep = 1;

function goToStep(step) {
    if (step > currentStep) {
        if (typeof validateCurrentStep === 'function' && !validateCurrentStep()) return;
    }

    document.querySelectorAll('.form-step').forEach(el => el.classList.remove('active'));
    const targetStep = document.getElementById('step-' + step);
    if (targetStep) targetStep.classList.add('active');

    document.querySelectorAll('.stepper-wrapper-premium .step').forEach((el, idx) => {
        if (idx < step) el.classList.add('active');
        else el.classList.remove('active');
    });

    currentStep = step;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Banned Words list for client-side validation
const BANNED_WORDS = [
    'sex', 'porn', 'nude', 'naked', 'erotic', 'escort', 'massage parlour', 'sensual',
    'vulgar', 'orgasm', 'xxx', 'hentai', 'playboy', 'slut', 'whore', 'hookup',
    'condom', 'vagina', 'penis', 'breasts', 'boobs', 'strip club', 'call girl',
    'kambi', 'vedi', 'chundu', 'mulakalo', 'sugam', 'kundila', 'mypu', 'poola', 'kunna',
    'drugs', 'cocaine', 'heroin', 'marijuana', 'weed', 'cannabis', 'meth', 'ecstasy',
    'lsd', 'ganja', 'kannabis', 'mdma', 'hashish', 'steroids',
    'weapons', 'ammunition', 'firearms', 'gun for sale', 'pistol for sale', 'explosives',
    'grenade', 'bomb', 'assault rifle', 'murder', 'suicide', 'slaughter'
];

function checkTextModeration(text) {
    if (!text) return null;
    const cleanText = text.toLowerCase();
    for (const word of BANNED_WORDS) {
        const regex = new RegExp('\\b' + word.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&') + '\\b', 'u');
        if (regex.test(cleanText)) {
            return word;
        }
        if (word.length > 3 && cleanText.includes(word)) {
            return word;
        }
    }
    return null;
}

function handleLiveTextModeration(inputEl) {
    if (!inputEl) return true;

    // Find or create warning message element
    let warningEl = inputEl.parentNode.querySelector('.moderation-warning');
    const flagged = checkTextModeration(inputEl.value);

    if (flagged) {
        inputEl.style.borderColor = '#ef4444';
        if (!warningEl) {
            warningEl = document.createElement('div');
            warningEl.className = 'moderation-warning';
            warningEl.style.cssText = 'color: #ef4444; font-size: 12px; margin-top: 6px; font-weight: 600; display: flex; align-items: center; gap: 4px;';
            inputEl.parentNode.appendChild(warningEl);
        }
        warningEl.innerHTML = `<i class="fa fa-exclamation-triangle"></i> Restricted term detected: "${flagged}". Please modify.`;
        return false;
    } else {
        inputEl.style.borderColor = '#f1f5f9';
        if (warningEl) {
            warningEl.remove();
        }
        return true;
    }
}

function validateCurrentStep() {
    const activeSection = document.getElementById('step-' + currentStep);
    if (!activeSection) return true;

    let valid = true;

    // Check empty fields
    const inputs = activeSection.querySelectorAll('[required]');
    inputs.forEach(input => {
        if (!input.value) {
            input.style.borderColor = '#ef4444';
            valid = false;
        } else {
            input.style.borderColor = '#f1f5f9';
        }
    });

    // Check text moderation for title/description
    const textInputs = activeSection.querySelectorAll('#title, #description');
    textInputs.forEach(input => {
        if (!handleLiveTextModeration(input)) {
            valid = false;
        }
    });

    return valid;
}

document.addEventListener('DOMContentLoaded', () => {
    // Add real-time watchers for Title and Description
    const titleEl = document.getElementById('title');
    const descEl = document.getElementById('description');

    if (titleEl) {
        titleEl.addEventListener('input', () => handleLiveTextModeration(titleEl));
    }
    if (descEl) {
        descEl.addEventListener('input', () => handleLiveTextModeration(descEl));
    }

    const postAdForm = document.getElementById('postAdForm');
    if (postAdForm) {
        postAdForm.addEventListener('submit', function (e) {
            const isTitleValid = handleLiveTextModeration(titleEl);
            const isDescValid = handleLiveTextModeration(descEl);

            if (!isTitleValid || !isDescValid) {
                e.preventDefault();
                alert('Your ad title or description contains prohibited terms. Please review highlighted fields.');
                return;
            }

            const loader = document.getElementById('loadingOverlay');
            if (loader) loader.style.display = 'flex';
        });
    }
});

function updateL2Categories() {
    const l1 = document.getElementById('l1_category').value;
    const l2Group = document.getElementById('l2_category_group');
    const l2Select = document.getElementById('category_id');

    // l2Categories and catDetails must be defined globally in the PHP file
    if (typeof l2Categories === 'undefined') return;

    l2Select.innerHTML = '<option value="">Select Sub-Category</option>';
    if (l1 && l2Categories[l1]) {
        l2Group.style.display = 'block';
        l2Categories[l1].forEach(c => {
            const op = document.createElement('option');
            op.value = c.id;
            op.textContent = c.name;
            l2Select.appendChild(op);
        });
    } else {
        l2Group.style.display = 'none';
        if (l1) checkPerishable(l1);
    }
}

function checkPerishable(id) {
    const s = document.getElementById('perishable_section');
    const e = document.getElementById('expiry_date');

    if (typeof catDetails === 'undefined') return;

    if (id && catDetails[id]?.is_perishable == 1) {
        if (s) s.style.display = 'flex';
        if (e) e.required = true;
    } else {
        if (s) s.style.display = 'none';
        if (e) e.required = false;
    }
}

function updateTypeUI(t) {
    const priceLabel = document.getElementById('priceLabel');
    if (priceLabel) {
        if (t === 'sell') {
            priceLabel.innerText = "Price (₹) *";
        } else if (t === 'rent') {
            priceLabel.innerText = "Rent Price (₹) *";
        } else {
            priceLabel.innerText = "Budget (₹) *";
        }
    }
}

function toggleContact(t) {
    const chk = document.getElementById('contact_' + t + '_chk');
    const g = document.getElementById(t + '_group');
    const i = document.getElementById(t + '_number');
    if (g && chk) g.style.display = chk.checked ? 'block' : 'none';
    if (i && chk) i.required = chk.checked;
}

async function detectPostLocation(ev) {
    const b = ev.currentTarget;
    const originalHTML = b.innerHTML;
    b.disabled = true;
    b.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

    try {
        const coords = await EnteangadiLocation.getCurrentCoordinates();
        const lat = coords.lat;
        const lng = coords.lng;
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');
        const locInput = document.getElementById('location_name');

        if (latInput) latInput.value = lat;
        if (lngInput) lngInput.value = lng;

        try {
            const r = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=10`);
            const d = await r.json();
            if (locInput) locInput.value = d.address.city || d.address.town || 'Current Location';
        } catch (e) {
            if (locInput) locInput.value = 'Current Location';
        }
    } catch (err) {
        console.error('Error fetching location:', err);
    } finally {
        b.disabled = false;
        b.innerHTML = originalHTML;
    }
}

// Dynamic loading of TensorFlow.js and NSFWJS for 18+ content filtering
let nsfwModel = null;
let modelLoadingPromise = null;

async function loadNSFWModel() {
    if (nsfwModel) return nsfwModel;
    if (modelLoadingPromise) return modelLoadingPromise;

    modelLoadingPromise = (async () => {
        try {
            if (typeof tf === 'undefined') {
                await loadScript('https://cdn.jsdelivr.net/npm/@tensorflow/tfjs@2.8.5/dist/tf.min.js');
            }
            if (typeof nsfwjs === 'undefined') {
                await loadScript('https://cdn.jsdelivr.net/npm/nsfwjs@2.4.2/dist/nsfwjs.min.js');
            }
            
            // Resolve absolute base path for local model hosting
            const baseUrl = (window.EnteangadiConfig && typeof window.EnteangadiConfig.baseUrl !== 'undefined') ? window.EnteangadiConfig.baseUrl : '..';
            const modelUrl = baseUrl + '/assets/models/nsfw/';
            
            // Load from locally hosted model files to prevent remote dependency down-times
            nsfwModel = await nsfwjs.load(modelUrl);
            return nsfwModel;
        } catch (e) {
            console.error("Failed to load NSFW detection model:", e);
            return null;
        }
    })();

    return modelLoadingPromise;
}

function loadScript(src) {
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

function checkImageNSFWWithModel(model, file) {
    return new Promise((resolve) => {
        const img = new Image();
        const dataURL = file.dataURL || (window.EnteangadiImageCache && window.EnteangadiImageCache[file.name]);
        const isDataURL = !!dataURL;
        img.src = dataURL || URL.createObjectURL(file);
        img.onload = async () => {
            try {
                const predictions = await model.classify(img);
                if (!isDataURL) {
                    URL.revokeObjectURL(img.src);
                }

                let nsfwScore = 0;
                for (const p of predictions) {
                    if (p.className === 'Porn' || p.className === 'Sexy' || p.className === 'Hentai') {
                        nsfwScore += p.probability;
                    }
                }
                // Flag as NSFW if the combined probability is 60% or higher
                resolve(nsfwScore >= 0.60);
            } catch (err) {
                console.error("Image classification error:", err);
                resolve(false);
            }
        };
        img.onerror = () => {
            if (!isDataURL) {
                try { URL.revokeObjectURL(img.src); } catch(e) {}
            }
            resolve(false);
        };
    });
}

async function previewImages() {
    const c = document.getElementById('image_preview_container');
    const imagesInput = document.getElementById('images');
    if (!c || !imagesInput) return;

    const files = Array.from(imagesInput.files);
    c.innerHTML = '';

    if (files.length === 0) return;

    // Show beautiful premium scanner overlay
    const loader = document.getElementById('loadingOverlay');
    const loaderTitle = document.getElementById('loaderTitle') || loader?.querySelector('h3');
    const loaderDesc = document.getElementById('loaderDesc') || loader?.querySelector('p');

    if (loader) {
        if (loaderTitle) loaderTitle.innerText = "Safety Scanning";
        if (loaderDesc) loaderDesc.innerText = "Scanning selected images for community guidelines safety...";
        loader.style.display = 'flex';
    }

    let model = null;
    try {
        model = await loadNSFWModel();
    } catch (e) {
        console.error("Failed to fetch model:", e);
    }

    const validFiles = [];
    const dt = new DataTransfer();

    for (let i = 0; i < files.length; i++) {
        const file = files[i];

        // Exclude video files on client-side
        if (file.type.startsWith('video/')) {
            alert(`Videos are not allowed: ${file.name}`);
            continue;
        }

        let isNSFW = false;
        if (model && file.type.startsWith('image/')) {
            try {
                isNSFW = await checkImageNSFWWithModel(model, file);
            } catch (err) {
                console.error("Error analyzing safety of:", file.name, err);
            }
        }

        if (isNSFW) {
            alert(`Inappropriate Content Blocked: Image "${file.name}" contains content violating our 18+ policy and has been removed from selection.`);
            continue;
        }

        validFiles.push(file);
        dt.items.add(file);
    }

    // Hide scanning overlay
    if (loader) {
        loader.style.display = 'none';
        if (loaderTitle) loaderTitle.innerText = "Publishing Ad";
        if (loaderDesc) loaderDesc.innerText = "Please wait while we set everything up...";
    }

    // Update the input with filtered safe files
    imagesInput.files = dt.files;

    // Render preview cards
    for (let i = 0; i < validFiles.length; i++) {
        const file = validFiles[i];
        const dataURL = file.dataURL || (window.EnteangadiImageCache && window.EnteangadiImageCache[file.name]);
        if (dataURL) {
            const d = document.createElement('div');
            d.className = 'preview-item' + (i === 0 ? ' is-cover' : '');
            d.onclick = () => {
                document.querySelectorAll('.preview-item').forEach(el => el.classList.remove('is-cover'));
                d.classList.add('is-cover');
                const coverIdx = document.getElementById('cover_index');
                if (coverIdx) coverIdx.value = i;
            };
            d.innerHTML = `
                <img src="${dataURL}">
                <div class="cover-badge">COVER</div>
                <div class="selection-overlay">Set as Main</div>
            `;
            c.appendChild(d);
        } else {
            const r = new FileReader();
            r.onload = (e) => {
                const d = document.createElement('div');
                d.className = 'preview-item' + (i === 0 ? ' is-cover' : '');
                d.onclick = () => {
                    document.querySelectorAll('.preview-item').forEach(el => el.classList.remove('is-cover'));
                    d.classList.add('is-cover');
                    const coverIdx = document.getElementById('cover_index');
                    if (coverIdx) coverIdx.value = i;
                };
                d.innerHTML = `
                    <img src="${e.target.result}">
                    <div class="cover-badge">COVER</div>
                    <div class="selection-overlay">Set as Main</div>
                `;
                c.appendChild(d);
            }
            r.readAsDataURL(file);
        }
    }
}

// Preload the NSFW model when post_ad page loads
document.addEventListener('DOMContentLoaded', () => {
    loadNSFWModel().catch(err => console.error("Model pre-load error:", err));
});
