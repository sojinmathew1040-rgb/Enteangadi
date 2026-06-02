/**
 * Enteangadi - Ad Editing Logic
 * Handles category updates, perishable checks, existing image management, and real-time safety moderation.
 */

function goToStep(n) {
    if (n > currentStep) {
        if (typeof validateCurrentStep === 'function' && !validateCurrentStep()) return;
    }

    document.querySelectorAll('.form-step').forEach(step => step.classList.remove('active'));
    const targetStep = document.getElementById('step-' + n);
    if (targetStep) targetStep.classList.add('active');

    document.querySelectorAll('.stepper-wrapper-premium .step').forEach((step, idx) => {
        if (idx < n) step.classList.add('active');
        else step.classList.remove('active');
    });
    currentStep = n;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

let currentStep = 1;

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

function updateTypeUI(type) {
    const priceLabel = document.getElementById('priceLabel');
    if (priceLabel) {
        if (type === 'sell') {
            priceLabel.innerText = 'Price (₹) *';
        } else if (type === 'rent') {
            priceLabel.innerText = 'Rent Price (₹) *';
        } else {
            priceLabel.innerText = 'Budget (₹) *';
        }
    }
}

function updateL2Categories() {
    const l1Select = document.getElementById('l1_category');
    const l2Group = document.getElementById('l2_category_group');
    const l2Select = document.getElementById('category_id');

    if (!l1Select || !l2Select) return;
    const selectedL1 = l1Select.value;

    if (typeof l2Categories === 'undefined') return;

    l2Select.innerHTML = '<option value="">Select Sub-Category</option>';

    if (selectedL1 && l2Categories[selectedL1]) {
        if (l2Group) l2Group.style.display = 'block';
        l2Categories[selectedL1].forEach(cat => {
            const option = document.createElement('option');
            option.value = cat.id;
            option.textContent = cat.name;
            if (typeof currentL2 !== 'undefined' && cat.id == currentL2) option.selected = true;
            l2Select.appendChild(option);
        });
        checkPerishable(l2Select.value || (typeof currentL2 !== 'undefined' ? currentL2 : null));
    } else {
        if (l2Group) l2Group.style.display = 'none';
    }
}

function checkPerishable(catId) {
    const section = document.getElementById('perishable_section');
    const expiryInput = document.getElementById('expiry_date');

    if (typeof catDetails === 'undefined') return;

    if (catId && catDetails[catId] && catDetails[catId].is_perishable == 1) {
        if (section) section.style.display = 'flex';
        if (expiryInput) expiryInput.required = true;
    } else {
        if (section) section.style.display = 'none';
        if (expiryInput) expiryInput.required = false;
    }
}

function toggleContact(type) {
    const chk = document.getElementById('contact_' + type + '_chk');
    const group = document.getElementById(type + '_group');
    const input = document.getElementById(type + '_number');
    if (group && chk) group.style.display = chk.checked ? 'block' : 'none';
    if (input && chk) input.required = chk.checked;
}

function toggleExistingDelete(id, el) {
    const chk = document.getElementById('del-' + id);
    if (!chk) return;
    chk.checked = !chk.checked;
    el.classList.toggle('marked-delete', chk.checked);
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
                await loadScript('https://cdn.jsdelivr.net/npm/@tensorflow/tfjs');
            }
            if (typeof nsfwjs === 'undefined') {
                await loadScript('https://cdn.jsdelivr.net/npm/nsfwjs');
            }
            nsfwModel = await nsfwjs.load();
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
        img.src = URL.createObjectURL(file);
        img.onload = async () => {
            try {
                const predictions = await model.classify(img);
                URL.revokeObjectURL(img.src);

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
            resolve(false);
        };
    });
}

async function previewImages() {
    const container = document.getElementById('image_preview_container');
    const filesInput = document.getElementById('images');
    if (!container || !filesInput) return;

    const files = Array.from(filesInput.files);

    // Clear previous new uploads
    const newPreviews = container.querySelectorAll('.preview-item.new-upload');
    newPreviews.forEach(p => p.remove());

    if (files.length === 0) return;

    // Show Safety Scanning overlay
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
        if (loaderTitle) loaderTitle.innerText = "Saving Changes";
        if (loaderDesc) loaderDesc.innerText = "Please wait while we update your ad...";
    }

    // Update the input with filtered safe files
    filesInput.files = dt.files;

    for (let i = 0; i < validFiles.length; i++) {
        const reader = new FileReader();
        reader.onload = (e) => {
            const div = document.createElement('div');
            div.className = 'preview-item new-upload';
            div.innerHTML = `<img src="${e.target.result}"><div class="new-badge">NEW</div>`;
            container.appendChild(div);
        };
        reader.readAsDataURL(validFiles[i]);
    }
}

async function detectPostLocation(e) {
    if (e) e.preventDefault();
    const btn = e.currentTarget;
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

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
            const resp = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=10`);
            const data = await resp.json();
            if (locInput) locInput.value = data.address.city || data.address.town || data.display_name;
        } catch (e) { }
    } catch (err) {
        console.error('Error fetching location:', err);
    } finally {
        btn.innerHTML = originalHTML;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    updateL2Categories();
    const checkedType = document.querySelector('input[name="type"]:checked');
    if (checkedType) {
        updateTypeUI(checkedType.value);
    }

    // Add real-time watchers for Title and Description
    const titleEl = document.getElementById('title');
    const descEl = document.getElementById('description');

    if (titleEl) {
        titleEl.addEventListener('input', () => handleLiveTextModeration(titleEl));
    }
    if (descEl) {
        descEl.addEventListener('input', () => handleLiveTextModeration(descEl));
    }

    // Preload NSFW model
    loadNSFWModel().catch(err => console.error("Model pre-load error:", err));

    const editAdForm = document.getElementById('editAdForm');
    if (editAdForm) {
        editAdForm.addEventListener('submit', function (e) {
            const isTitleValid = handleLiveTextModeration(titleEl);
            const isDescValid = handleLiveTextModeration(descEl);

            if (!isTitleValid || !isDescValid) {
                e.preventDefault();
                alert('Your ad title or description contains prohibited terms. Please review highlighted fields.');
                return;
            }

            const loader = document.getElementById('loadingOverlay');
            if (loader) {
                const loaderTitle = document.getElementById('loaderTitle') || loader.querySelector('h3');
                const loaderDesc = document.getElementById('loaderDesc') || loader.querySelector('p');
                if (loaderTitle) loaderTitle.innerText = "Saving Changes";
                if (loaderDesc) loaderDesc.innerText = "Please wait while we update your ad...";
                loader.style.display = 'flex';
            }
        });
    }
});
