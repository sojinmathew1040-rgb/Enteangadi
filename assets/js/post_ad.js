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

function validateCurrentStep() {
    const activeSection = document.getElementById('step-' + currentStep);
    if (!activeSection) return true;
    
    const inputs = activeSection.querySelectorAll('[required]');
    let valid = true;
    inputs.forEach(input => {
        if (!input.value) {
            input.style.borderColor = '#ef4444';
            valid = false;
        } else {
            input.style.borderColor = '#f1f5f9';
        }
    });
    return valid;
}

document.addEventListener('DOMContentLoaded', () => {
    const postAdForm = document.getElementById('postAdForm');
    if (postAdForm) {
        postAdForm.addEventListener('submit', function () {
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
        priceLabel.innerText = (t === 'sell' ? "Price (₹) *" : "Budget (₹) *"); 
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
    
    if (!navigator.geolocation) {
        b.disabled = false;
        b.innerHTML = originalHTML;
        return;
    }

    navigator.geolocation.getCurrentPosition(async (pos) => {
        const lat = pos.coords.latitude; 
        const lng = pos.coords.longitude;
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
        b.disabled = false; 
        b.innerHTML = originalHTML;
    }, () => { 
        b.disabled = false; 
        b.innerHTML = originalHTML; 
    });
}

function previewImages() {
    const c = document.getElementById('image_preview_container');
    const imagesInput = document.getElementById('images');
    if (!c || !imagesInput) return;
    
    const f = imagesInput.files;
    c.innerHTML = '';
    for (let i = 0; i < f.length; i++) {
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
        r.readAsDataURL(f[i]);
    }
}
