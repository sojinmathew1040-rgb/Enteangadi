/**
 * Enteangadi - Ad Editing Logic
 * Handles category updates, perishable checks, and existing image management.
 */

function goToStep(n) {
    document.querySelectorAll('.form-step').forEach(step => step.classList.remove('active'));
    const targetStep = document.getElementById('step-' + n);
    if (targetStep) targetStep.classList.add('active');

    document.querySelectorAll('.stepper-wrapper-premium .step').forEach((step, idx) => {
        if (idx < n) step.classList.add('active');
        else step.classList.remove('active');
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function updateTypeUI(type) {
    const priceLabel = document.getElementById('priceLabel');
    if (priceLabel) {
        priceLabel.innerText = (type === 'sell') ? 'Price (₹) *' : 'Budget (₹) *';
    }
}

function updateL2Categories() {
    const l1Select = document.getElementById('l1_category');
    const l2Group = document.getElementById('l2_category_group');
    const l2Select = document.getElementById('category_id');

    if (!l1Select || !l2Select) return;
    const selectedL1 = l1Select.value;

    // l2Categories and currentL2 must be defined globally
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

function previewImages() {
    const container = document.getElementById('image_preview_container');
    const filesInput = document.getElementById('images');
    if (!container || !filesInput) return;

    const files = filesInput.files;
    // We don't clear existing images, just append new previews or handle them specifically
    // In edit mode, we typically show new previews separately or clear current NEW previews
    // For simplicity, let's follow the original logic which might have been replacing previews

    // Note: The original edit_ad.php previewImages was a bit simpler
    const newPreviews = container.querySelectorAll('.preview-item.new-upload');
    newPreviews.forEach(p => p.remove());

    for (let i = 0; i < files.length; i++) {
        const reader = new FileReader();
        reader.onload = (e) => {
            const div = document.createElement('div');
            div.className = 'preview-item new-upload';
            div.innerHTML = `<img src="${e.target.result}"><div class="new-badge">NEW</div>`;
            container.appendChild(div);
        };
        reader.readAsDataURL(files[i]);
    }
}

async function detectPostLocation(e) {
    if (e) e.preventDefault();
    if (!navigator.geolocation) return;
    const btn = e.currentTarget;
    const originalHTML = btn.innerHTML;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

    navigator.geolocation.getCurrentPosition(async (pos) => {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;
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
        btn.innerHTML = originalHTML;
    }, () => {
        btn.innerHTML = originalHTML;
    });
}

document.addEventListener('DOMContentLoaded', () => {
    updateL2Categories();
    const checkedType = document.querySelector('input[name="type"]:checked');
    if (checkedType) {
        updateTypeUI(checkedType.value);
    }
});
