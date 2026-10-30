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
            priceLabel.innerText = 'Price (â‚¹) *';
        } else if (type === 'rent') {
            priceLabel.innerText = 'Rent Price (â‚¹) *';
        } else {
            priceLabel.innerText = 'Budget (â‚¹) *';
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

    loadCatalogBrands(selectedL1, typeof currentBrand !== 'undefined' ? currentBrand : null);
}

function loadCatalogBrands(categoryId, preselect = null) {
    const brandGroup = document.getElementById('brand_group');
    const brandSelect = document.getElementById('brand_select');
    const modelGroup = document.getElementById('model_group');
    const modelSelect = document.getElementById('model_select');
    const variantGroup = document.getElementById('variant_group');
    const variantSelect = document.getElementById('variant_select');

    if (!brandGroup || !brandSelect) return;

    if (!categoryId) {
        brandGroup.style.display = 'none';
        brandSelect.innerHTML = '<option value="">Select Brand</option>';
        if (modelGroup) modelGroup.style.display = 'none';
        if (variantGroup) variantGroup.style.display = 'none';
        return;
    }

    fetch('../api/catalog.php?action=brands&category_id=' + encodeURIComponent(categoryId))
        .then(res => res.json())
        .then(data => {
            if (data.success && data.brands && data.brands.length > 0) {
                brandSelect.innerHTML = '<option value="">Select Brand / Make</option>';
                data.brands.forEach(b => {
                    const opt = document.createElement('option');
                    opt.value = b.name;
                    opt.textContent = b.name;
                    opt.dataset.id = b.id;
                    if (preselect && (b.name === preselect || b.id == preselect)) {
                        opt.selected = true;
                    }
                    brandSelect.appendChild(opt);
                });
                brandGroup.style.display = 'block';

                if (brandSelect.value) {
                    onBrandSelected(brandSelect, typeof currentModel !== 'undefined' ? currentModel : null);
                }
            } else {
                brandGroup.style.display = 'none';
                brandSelect.innerHTML = '<option value="">Select Brand</option>';
                if (modelGroup) modelGroup.style.display = 'none';
                if (variantGroup) variantGroup.style.display = 'none';
            }
        })
        .catch(err => {
            console.error('Error loading brands in edit_ad:', err);
        });
}

function onBrandSelected(selectEl, preselectModel = null) {
    const selectedOpt = selectEl.options[selectEl.selectedIndex];
    const brandId = selectedOpt ? selectedOpt.dataset.id : null;
    const modelGroup = document.getElementById('model_group');
    const modelSelect = document.getElementById('model_select');
    const variantGroup = document.getElementById('variant_group');
    const variantSelect = document.getElementById('variant_select');

    if (variantGroup) variantGroup.style.display = 'none';
    if (variantSelect) variantSelect.innerHTML = '<option value="">Select Variant</option>';

    if (!brandId) {
        if (modelGroup) modelGroup.style.display = 'none';
        if (modelSelect) modelSelect.innerHTML = '<option value="">Select Model</option>';
        return;
    }

    fetch('../api/catalog.php?action=models&brand_id=' + encodeURIComponent(brandId))
        .then(res => res.json())
        .then(data => {
            if (data.success && data.models && data.models.length > 0) {
                modelSelect.innerHTML = '<option value="">Select Model / Variety</option>';
                data.models.forEach(m => {
                    const opt = document.createElement('option');
                    opt.value = m.name;
                    opt.textContent = m.name;
                    opt.dataset.id = m.id;
                    if (preselectModel && (m.name === preselectModel || m.id == preselectModel)) {
                        opt.selected = true;
                    }
                    modelSelect.appendChild(opt);
                });
                if (modelGroup) modelGroup.style.display = 'block';

                if (modelSelect.value) {
                    onModelSelected(modelSelect, typeof currentVariant !== 'undefined' ? currentVariant : null);
                }
            } else {
                if (modelGroup) modelGroup.style.display = 'none';
                if (modelSelect) modelSelect.innerHTML = '<option value="">Select Model</option>';
            }
        })
        .catch(err => console.error('Error loading models in edit_ad:', err));
}

function onModelSelected(selectEl, preselectVariant = null) {
    const selectedOpt = selectEl.options[selectEl.selectedIndex];
    const modelId = selectedOpt ? selectedOpt.dataset.id : null;
    const variantGroup = document.getElementById('variant_group');
    const variantSelect = document.getElementById('variant_select');

    if (!modelId) {
        if (variantGroup) variantGroup.style.display = 'none';
        if (variantSelect) variantSelect.innerHTML = '<option value="">Select Variant</option>';
        return;
    }

    fetch('../api/catalog.php?action=variants&model_id=' + encodeURIComponent(modelId))
        .then(res => res.json())
        .then(data => {
            if (data.success && data.variants && data.variants.length > 0) {
                variantSelect.innerHTML = '<option value="">Select Variant / Trim / Spec</option>';
                data.variants.forEach(v => {
                    const opt = document.createElement('option');
                    opt.value = v.name;
                    opt.textContent = v.name;
                    if (preselectVariant && (v.name === preselectVariant || v.id == preselectVariant)) {
                        opt.selected = true;
                    }
                    variantSelect.appendChild(opt);
                });
                if (variantGroup) variantGroup.style.display = 'block';
            } else {
                if (variantGroup) variantGroup.style.display = 'none';
                if (variantSelect) variantSelect.innerHTML = '<option value="">Select Variant</option>';
            }
        })
        .catch(err => console.error('Error loading variants in edit_ad:', err));
}

function onVariantSelected(selectEl) {
    // Selection made
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


let editPhotoItems = [];
let deletedExistingIds = [];
let editDragSrcIndex = null;

function initEditPhotos() {
    editPhotoItems = [];
    deletedExistingIds = [];
    if (typeof initialExistingPhotos !== 'undefined' && Array.isArray(initialExistingPhotos)) {
        initialExistingPhotos.forEach((p, idx) => {
            editPhotoItems.push({
                key: 'ex_' + p.id,
                type: 'existing',
                existingId: Number(p.id),
                src: p.src,
                isCover: Boolean(p.isCover || idx === 0)
            });
        });
    }
    if (editPhotoItems.length > 0 && !editPhotoItems.some(item => item.isCover)) {
        editPhotoItems[0].isCover = true;
    }
    renderEditPhotoPreviews();
}

function readEditFileAsDataURL(file) {
    return new Promise((resolve) => {
        const cached = file.dataURL || (window.EnteangadiImageCache && window.EnteangadiImageCache[file.name]);
        if (cached) {
            resolve(cached);
            return;
        }
        const reader = new FileReader();
        reader.onload = (e) => resolve(e.target.result);
        reader.onerror = () => resolve('');
        reader.readAsDataURL(file);
    });
}

function syncEditPhotoInputs() {
    const filesInput = document.getElementById('images');
    const delContainer = document.getElementById('deleted_images_inputs');
    const orderInput = document.getElementById('photo_order');

    if (editPhotoItems.length > 0 && !editPhotoItems.some(item => item.isCover)) {
        editPhotoItems[0].isCover = true;
    }

    // 1. Sync deleted existing image IDs
    if (delContainer) {
        delContainer.innerHTML = '';
        deletedExistingIds.forEach(id => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'delete_images[]';
            inp.value = String(id);
            delContainer.appendChild(inp);
        });
    }

    // 2. Sync newly uploaded files in DataTransfer and assign newIndex
    const dt = new DataTransfer();
    let newCounter = 0;
    editPhotoItems.forEach(item => {
        if (item.type === 'new' && item.file) {
            item.newIndex = newCounter++;
            dt.items.add(item.file);
        }
    });
    if (filesInput) {
        filesInput.files = dt.files;
    }

    // 3. Build ordered photo list with selected Cover Image first
    if (orderInput) {
        const coverItem = editPhotoItems.find(item => item.isCover) || null;
        const orderedList = [];
        if (coverItem) {
            orderedList.push(
                coverItem.type === 'existing'
                    ? { type: 'existing', id: coverItem.existingId, isCover: true }
                    : { type: 'new', newIndex: coverItem.newIndex, isCover: true }
            );
        }
        editPhotoItems.forEach(item => {
            if (coverItem && item.key === coverItem.key) return;
            orderedList.push(
                item.type === 'existing'
                    ? { type: 'existing', id: item.existingId, isCover: false }
                    : { type: 'new', newIndex: item.newIndex, isCover: false }
            );
        });
        orderInput.value = JSON.stringify(orderedList);
    }
}

function setEditPhotoCover(index) {
    if (index < 0 || index >= editPhotoItems.length) return;
    editPhotoItems.forEach((item, idx) => {
        item.isCover = (idx === index);
    });
    renderEditPhotoPreviews();
    if (typeof showToast === 'function') {
        showToast('Cover image updated.');
    }
}

function moveEditPhoto(fromIndex, toIndex) {
    if (
        fromIndex === toIndex ||
        fromIndex < 0 ||
        toIndex < 0 ||
        fromIndex >= editPhotoItems.length ||
        toIndex >= editPhotoItems.length
    ) {
        return;
    }
    const [moved] = editPhotoItems.splice(fromIndex, 1);
    editPhotoItems.splice(toIndex, 0, moved);
    renderEditPhotoPreviews();
}

async function deleteEditPhoto(index) {
    if (index < 0 || index >= editPhotoItems.length) return;
    const item = editPhotoItems[index];

    const confirmed = window.confirmPhotoDelete
        ? await window.confirmPhotoDelete({
            title: 'Delete Photo?',
            message: 'Are you sure you want to delete this photo?',
            imgSrc: item.src,
            yesText: 'Yes',
            noText: 'No'
        })
        : confirm('Are you sure you want to delete this photo?');

    if (!confirmed) return;

    if (item.type === 'existing' && item.existingId) {
        if (!deletedExistingIds.includes(item.existingId)) {
            deletedExistingIds.push(item.existingId);
        }
    }

    const wasCover = item.isCover;
    editPhotoItems.splice(index, 1);
    if (wasCover && editPhotoItems.length > 0) {
        editPhotoItems[0].isCover = true;
    }

    renderEditPhotoPreviews();
    if (typeof showToast === 'function') {
        showToast('Photo removed. Save changes to apply.');
    }
}

function renderEditPhotoPreviews() {
    const container = document.getElementById('image_preview_container');
    if (!container) return;

    syncEditPhotoInputs();
    container.innerHTML = '';

    editPhotoItems.forEach((item, idx) => {
        const card = document.createElement('div');
        card.className = 'preview-item' + (item.isCover ? ' is-cover' : '');
        card.draggable = true;
        card.dataset.index = idx;

        const badgeHtml = item.isCover
            ? `<span class="photo-badge cover-badge-pill"><i class="fa fa-star"></i> COVER</span>`
            : `<span class="photo-badge order-badge-pill">#${idx + 1}</span>`;

        card.innerHTML = `
            <div class="photo-thumb-area" title="Click to set as Cover Image">
                <img src="${item.src}" alt="Product Photo ${idx + 1}">
                ${badgeHtml}
                <button type="button" class="photo-delete-btn" title="Delete Photo">
                    <i class="fa fa-trash-alt"></i>
                </button>
            </div>
            <div class="photo-controls-bar">
                <button type="button" class="photo-ctrl-btn move-left" ${idx === 0 ? 'disabled' : ''} title="Move Left">
                    <i class="fa fa-chevron-left"></i>
                </button>
                <button type="button" class="photo-cover-select-btn ${item.isCover ? 'active' : ''}" title="Set as Cover Image">
                    ${item.isCover ? '<i class="fa fa-check-circle"></i> Cover' : 'Set Cover'}
                </button>
                <button type="button" class="photo-ctrl-btn move-right" ${idx === editPhotoItems.length - 1 ? 'disabled' : ''} title="Move Right">
                    <i class="fa fa-chevron-right"></i>
                </button>
            </div>
        `;

        const thumbArea = card.querySelector('.photo-thumb-area');
        const deleteBtn = card.querySelector('.photo-delete-btn');
        const moveLeftBtn = card.querySelector('.move-left');
        const moveRightBtn = card.querySelector('.move-right');
        const coverBtn = card.querySelector('.photo-cover-select-btn');

        if (thumbArea) {
            thumbArea.addEventListener('click', (e) => {
                if (e.target.closest('.photo-delete-btn')) return;
                setEditPhotoCover(idx);
            });
        }
        if (deleteBtn) {
            deleteBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                deleteEditPhoto(idx);
            });
        }
        if (moveLeftBtn) {
            moveLeftBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                moveEditPhoto(idx, idx - 1);
            });
        }
        if (moveRightBtn) {
            moveRightBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                moveEditPhoto(idx, idx + 1);
            });
        }
        if (coverBtn) {
            coverBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                setEditPhotoCover(idx);
            });
        }

        // HTML5 Drag and Drop Reordering
        card.addEventListener('dragstart', (e) => {
            editDragSrcIndex = idx;
            card.classList.add('is-dragging');
            if (e.dataTransfer) {
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', String(idx));
            }
        });
        card.addEventListener('dragover', (e) => {
            e.preventDefault();
            if (e.dataTransfer) e.dataTransfer.dropEffect = 'move';
            if (editDragSrcIndex !== null && editDragSrcIndex !== idx) {
                card.classList.add('drag-over');
            }
        });
        card.addEventListener('dragleave', () => {
            card.classList.remove('drag-over');
        });
        card.addEventListener('drop', (e) => {
            e.preventDefault();
            card.classList.remove('drag-over');
            const fromIdx = editDragSrcIndex !== null ? editDragSrcIndex : parseInt(e.dataTransfer?.getData('text/plain') || '-1', 10);
            editDragSrcIndex = null;
            if (!isNaN(fromIdx) && fromIdx >= 0 && fromIdx !== idx) {
                moveEditPhoto(fromIdx, idx);
            }
        });
        card.addEventListener('dragend', () => {
            editDragSrcIndex = null;
            document.querySelectorAll('#image_preview_container .preview-item').forEach(el => {
                el.classList.remove('is-dragging', 'drag-over');
            });
        });

        container.appendChild(card);
    });
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
            const baseUrl = (window.EnteangadiConfig && typeof window.EnteangadiConfig.baseUrl !== 'undefined') ? window.EnteangadiConfig.baseUrl : '..';
            const modelUrl = baseUrl + '/assets/models/nsfw/';
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
                resolve(nsfwScore >= 0.60);
            } catch (err) {
                console.error("Image classification error:", err);
                resolve(false);
            }
        };
        img.onerror = () => {
            if (!isDataURL) {
                try { URL.revokeObjectURL(img.src); } catch (e) {}
            }
            resolve(false);
        };
    });
}

async function previewImages() {
    const container = document.getElementById('image_preview_container');
    const filesInput = document.getElementById('images');
    if (!container || !filesInput) return;

    const files = Array.from(filesInput.files || []);
    if (files.length === 0) {
        syncEditPhotoInputs();
        return;
    }

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

    for (let i = 0; i < files.length; i++) {
        const file = files[i];

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

        const dataURL = await readEditFileAsDataURL(file);
        editPhotoItems.push({
            key: 'new_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7),
            type: 'new',
            file: file,
            src: dataURL,
            isCover: editPhotoItems.length === 0
        });
    }

    // Hide scanning overlay
    if (loader) {
        loader.style.display = 'none';
        if (loaderTitle) loaderTitle.innerText = "Saving Changes";
        if (loaderDesc) loaderDesc.innerText = "Please wait while we update your ad...";
    }

    renderEditPhotoPreviews();
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
    initEditPhotos();
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

            syncEditPhotoInputs();

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
