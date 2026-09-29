/**
 * Client Registration Multi-Step Wizard
 */
(function () {
    'use strict';

    let currentStep = 1;
    const totalSteps = 5;
    const selectedFiles = []; // Array of File objects (max 3, max 5MB each)
    let isSubmitting = false;

    // Field-to-step mapping for server-side 422 errors
    const fieldStepMap = {
        client_type: 1,
        name: 1,
        contact_person: 1,
        email: 1,
        mobile: 1,
        alt_mobile: 1,
        industry: 2,
        company_size: 2,
        website: 2,
        gst_no: 2,
        pan_no: 2,
        address_line1: 3,
        address_line2: 3,
        pincode: 3,
        city: 3,
        state: 3,
        country: 3,
        lead_source: 4,
        assigned_to: 4,
        status: 4,
        tags: 4,
        notes: 4,
        documents: 4,
        consent_given: 4
    };

    // Regex validators
    const REGEX_EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const REGEX_MOBILE = /^[6-9]\d{9}$/;
    const REGEX_PINCODE = /^\d{6}$/;
    const REGEX_PAN = /^[A-Z]{5}[0-9]{4}[A-Z]$/;
    const REGEX_GST = /^\d{2}[A-Z]{5}\d{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/;
    const REGEX_URL = /^(https?:\/\/)?([\da-z.-]+)\.([a-z.]{2,6})([/\w .-]*)*\/?$/i;

    const GST_STATE_CODES = {
        'Jammu and Kashmir': ['01'],
        'Himachal Pradesh': ['02'],
        'Punjab': ['03'],
        'Chandigarh': ['04'],
        'Uttarakhand': ['05'],
        'Haryana': ['06'],
        'Delhi': ['07'],
        'Delhi (NCT)': ['07'],
        'Rajasthan': ['08'],
        'Uttar Pradesh': ['09'],
        'Bihar': ['10'],
        'Sikkim': ['11'],
        'Arunachal Pradesh': ['12'],
        'Nagaland': ['13'],
        'Manipur': ['14'],
        'Mizoram': ['15'],
        'Tripura': ['16'],
        'Meghalaya': ['17'],
        'Assam': ['18'],
        'West Bengal': ['19'],
        'Jharkhand': ['20'],
        'Odisha': ['21'],
        'Chhattisgarh': ['22'],
        'Madhya Pradesh': ['23'],
        'Gujarat': ['24'],
        'Dadra and Nagar Haveli and Daman and Diu': ['26', '25'],
        'Maharashtra': ['27'],
        'Andhra Pradesh': ['37', '28'],
        'Karnataka': ['29'],
        'Goa': ['30'],
        'Lakshadweep': ['31'],
        'Kerala': ['32'],
        'Tamil Nadu': ['33'],
        'Puducherry': ['34'],
        'Andaman and Nicobar Islands': ['35'],
        'Telangana': ['36'],
        'Ladakh': ['38']
    };

    function init() {
        setupEventListeners();
        setupAutoUppercase();
        setupClientTypeToggle();
        setupFileUpload();
        setupAddressBillingSync();
        updateWizardUI();
    }

    function setupEventListeners() {
        const nextBtn = document.getElementById('nextStepBtn');
        const prevBtn = document.getElementById('prevStepBtn');
        const form = document.getElementById('clientCreateForm');

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                if (validateStep(currentStep)) {
                    if (currentStep < totalSteps) {
                        currentStep++;
                        if (currentStep === totalSteps) {
                            populateReviewSection();
                        }
                        updateWizardUI();
                    }
                }
            });
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                if (currentStep > 1) {
                    currentStep--;
                    updateWizardUI();
                }
            });
        }

        if (form) {
            form.addEventListener('submit', handleFormSubmit);
        }

        // Jump to step via edit buttons in review tab
        document.querySelectorAll('[data-goto-step]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const target = parseInt(btn.dataset.gotoStep, 10);
                if (target >= 1 && target <= totalSteps) {
                    currentStep = target;
                    updateWizardUI();
                }
            });
        });
    }

    function setupAutoUppercase() {
        const panInput = document.getElementById('pan_no');
        const gstInput = document.getElementById('gst_no');

        if (panInput) {
            panInput.addEventListener('input', () => {
                panInput.value = panInput.value.toUpperCase().trim();
            });
        }

        if (gstInput) {
            gstInput.addEventListener('input', () => {
                gstInput.value = gstInput.value.toUpperCase().trim();
            });
        }
    }

    function setupClientTypeToggle() {
        const radios = document.querySelectorAll('input[name="client_type"]');
        const contactPersonWrapper = document.getElementById('contactPersonWrapper');
        const nameLabel = document.getElementById('clientNameLabel');

        radios.forEach(radio => {
            radio.addEventListener('change', () => {
                const isCompany = document.querySelector('input[name="client_type"]:checked')?.value === 'company';
                if (contactPersonWrapper) {
                    contactPersonWrapper.classList.toggle('d-none', !isCompany);
                }
                if (nameLabel) {
                    nameLabel.textContent = isCompany ? 'Company Name *' : 'Full Name *';
                }
            });
        });
    }

    function setupAddressBillingSync() {
        const syncCheckbox = document.getElementById('billingSameAsAddress');
        if (!syncCheckbox) return;

        syncCheckbox.addEventListener('change', () => {
            // Optional billing address section hook
            if (syncCheckbox.checked) {
                syncCheckbox.parentElement.classList.add('text-success');
            } else {
                syncCheckbox.parentElement.classList.remove('text-success');
            }
        });
    }

    function setupFileUpload() {
        const fileInput = document.getElementById('documentUploadInput');
        const fileListContainer = document.getElementById('selectedFilesList');

        if (!fileInput || !fileListContainer) return;

        fileInput.addEventListener('change', (e) => {
            const files = Array.from(e.target.files || []);

            for (const file of files) {
                if (selectedFiles.length >= 3) {
                    UI.toast('Maximum 3 documents allowed.', 'warning');
                    break;
                }

                // Max 5 MB
                if (file.size > 5 * 1024 * 1024) {
                    UI.toast(`File "${file.name}" exceeds maximum allowed size of 5 MB.`, 'danger');
                    continue;
                }

                // Allowed types
                const validMimes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
                const ext = file.name.split('.').pop().toLowerCase();
                const validExts = ['pdf', 'jpg', 'jpeg', 'png'];

                if (!validMimes.includes(file.type) && !validExts.includes(ext)) {
                    UI.toast(`File "${file.name}" format not supported. Only PDF, JPG, and PNG are allowed.`, 'danger');
                    continue;
                }

                // Avoid duplicate names
                if (!selectedFiles.some(f => f.name === file.name && f.size === file.size)) {
                    selectedFiles.push(file);
                }
            }

            fileInput.value = '';
            renderFileList();
        });

        window.removeSelectedFile = function (index) {
            if (index >= 0 && index < selectedFiles.length) {
                selectedFiles.splice(index, 1);
                renderFileList();
            }
        };
    }

    function renderFileList() {
        const container = document.getElementById('selectedFilesList');
        if (!container) return;

        if (selectedFiles.length === 0) {
            container.innerHTML = '<span class="text-muted small">No documents selected (optional).</span>';
            return;
        }

        let html = '<ul class="list-group list-group-flush border rounded">';
        selectedFiles.forEach((file, index) => {
            const sizeKb = (file.size / 1024).toFixed(1);
            const sizeStr = sizeKb > 1024 ? (sizeKb / 1024).toFixed(2) + ' MB' : sizeKb + ' KB';
            html += `
                <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                    <div class="d-flex align-items-center text-truncate me-2">
                        <svg class="me-2 text-primary flex-shrink-0" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        <span class="text-truncate small fw-medium">${UI.escape(file.name)}</span>
                        <span class="badge bg-light text-secondary ms-2">${sizeStr}</span>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" onclick="removeSelectedFile(${index})" aria-label="Remove file">
                        &times;
                    </button>
                </li>
            `;
        });
        html += '</ul>';
        container.innerHTML = html;
    }

    function updateWizardUI() {
        // Step panes
        for (let i = 1; i <= totalSteps; i++) {
            const pane = document.getElementById(`stepPane${i}`);
            if (pane) {
                pane.classList.toggle('d-none', i !== currentStep);
            }
        }

        // Stepper pills
        document.querySelectorAll('.wizard-step-item').forEach((pill, idx) => {
            const stepNum = idx + 1;
            pill.classList.remove('active', 'completed');
            if (stepNum === currentStep) {
                pill.classList.add('active');
            } else if (stepNum < currentStep) {
                pill.classList.add('completed');
            }
        });

        // Progress bar
        const progressBar = document.getElementById('wizardProgressBar');
        if (progressBar) {
            const pct = (currentStep / totalSteps) * 100;
            progressBar.style.width = `${pct}%`;
            progressBar.setAttribute('aria-valuenow', pct);
        }

        // Prev / Next / Submit buttons
        const prevBtn = document.getElementById('prevStepBtn');
        const nextBtn = document.getElementById('nextStepBtn');
        const submitBtn = document.getElementById('submitClientBtn');

        if (prevBtn) {
            prevBtn.disabled = currentStep === 1;
        }

        if (nextBtn && submitBtn) {
            if (currentStep === totalSteps) {
                nextBtn.classList.add('d-none');
                submitBtn.classList.remove('d-none');
            } else {
                nextBtn.classList.remove('d-none');
                submitBtn.classList.add('d-none');
            }
        }

        // Scroll top smoothly
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function validateStep(step) {
        const form = document.getElementById('clientCreateForm');
        UI.clearErrors(form);
        const errors = {};

        if (step === 1) {
            const isCompany = document.querySelector('input[name="client_type"]:checked')?.value === 'company';
            const name = document.getElementById('name')?.value.trim();
            const contactPerson = document.getElementById('contact_person')?.value.trim();
            const email = document.getElementById('email')?.value.trim();
            const mobile = document.getElementById('mobile')?.value.trim();
            const altMobile = document.getElementById('alt_mobile')?.value.trim();

            if (!name) {
                errors.name = isCompany ? 'Company name is required.' : 'Full name is required.';
            }

            if (isCompany && !contactPerson) {
                errors.contact_person = 'Contact person is required for company clients.';
            }

            if (!email) {
                errors.email = 'Email address is required.';
            } else if (!REGEX_EMAIL.test(email)) {
                errors.email = 'Please enter a valid email address.';
            }

            if (!mobile) {
                errors.mobile = 'Mobile number is required.';
            } else if (!REGEX_MOBILE.test(mobile)) {
                errors.mobile = 'Must be a valid 10-digit Indian mobile number starting with 6-9.';
            }

            if (altMobile && !REGEX_MOBILE.test(altMobile)) {
                errors.alt_mobile = 'Alternate mobile must be a valid 10-digit number starting with 6-9.';
            }
        } else if (step === 2) {
            const website = document.getElementById('website')?.value.trim();
            const gstNo = document.getElementById('gst_no')?.value.trim().toUpperCase();
            const panNo = document.getElementById('pan_no')?.value.trim().toUpperCase();

            if (website && !REGEX_URL.test(website)) {
                errors.website = 'Please enter a valid website URL (e.g. https://example.com).';
            }

            if (panNo && !REGEX_PAN.test(panNo)) {
                errors.pan_no = 'Invalid PAN format. Must be 5 letters, 4 digits, 1 letter (e.g. ABCDE1234F).';
            }

            if (gstNo) {
                if (!REGEX_GST.test(gstNo)) {
                    errors.gst_no = 'Invalid GSTIN format (15 characters, e.g. 27AAAAA0000A1Z5).';
                } else {
                    const state = document.getElementById('state')?.value.trim();
                    if (state && GST_STATE_CODES[state]) {
                        const prefix = gstNo.substring(0, 2);
                        if (!GST_STATE_CODES[state].includes(prefix)) {
                            errors.gst_no = `GSTIN state code (${prefix}) does not match selected state (${state}).`;
                        }
                    }
                }
            }
        } else if (step === 3) {
            const line1 = document.getElementById('address_line1')?.value.trim();
            const pincode = document.getElementById('pincode')?.value.trim();
            const city = document.getElementById('city')?.value.trim();
            const state = document.getElementById('state')?.value.trim();

            if (!line1) {
                errors.address_line1 = 'Address line 1 is required.';
            }

            if (!pincode) {
                errors.pincode = 'Pincode is required.';
            } else if (!REGEX_PINCODE.test(pincode)) {
                errors.pincode = 'Pincode must be exactly 6 digits.';
            }

            if (!city) {
                errors.city = 'City is required.';
            }

            if (!state) {
                errors.state = 'State is required.';
            } else {
                const gstNo = document.getElementById('gst_no')?.value.trim().toUpperCase();
                if (gstNo && REGEX_GST.test(gstNo) && GST_STATE_CODES[state]) {
                    const prefix = gstNo.substring(0, 2);
                    if (!GST_STATE_CODES[state].includes(prefix)) {
                        errors.state = `Selected state (${state}) does not match GSTIN state code (${prefix}).`;
                    }
                }
            }
        } else if (step === 4) {
            const consent = document.getElementById('consent_given');
            if (consent && !consent.checked) {
                errors.consent_given = 'Client consent is mandatory to register and store records.';
            }
        }

        if (Object.keys(errors).length > 0) {
            UI.showFieldErrors(form, errors);
            // Focus first error field
            const firstErrField = Object.keys(errors)[0];
            const firstInput = form.querySelector(`[name="${firstErrField}"]`) || form.querySelector(`#${firstErrField}`);
            if (firstInput) {
                firstInput.focus();
            }
            return false;
        }

        return true;
    }

    function populateReviewSection() {
        const val = id => document.getElementById(id)?.value.trim() || '—';
        const clientType = document.querySelector('input[name="client_type"]:checked')?.value || 'individual';

        // 1. Basic Info
        document.getElementById('rev_client_type').textContent = clientType === 'company' ? 'Company' : 'Individual';
        document.getElementById('rev_name').textContent = val('name');
        const contactPersonRow = document.getElementById('rev_contact_person_row');
        if (contactPersonRow) {
            if (clientType === 'company') {
                contactPersonRow.classList.remove('d-none');
                document.getElementById('rev_contact_person').textContent = val('contact_person');
            } else {
                contactPersonRow.classList.add('d-none');
            }
        }
        document.getElementById('rev_email').textContent = val('email');
        document.getElementById('rev_mobile').textContent = val('mobile');
        document.getElementById('rev_alt_mobile').textContent = val('alt_mobile');

        // 2. Business Info
        document.getElementById('rev_industry').textContent = val('industry');
        document.getElementById('rev_company_size').textContent = val('company_size');
        document.getElementById('rev_website').textContent = val('website');
        document.getElementById('rev_gst_no').textContent = val('gst_no');
        document.getElementById('rev_pan_no').textContent = val('pan_no');

        // 3. Address Info
        const line1 = val('address_line1');
        const line2 = val('address_line2');
        const fullAddress = line2 !== '—' ? `${line1}, ${line2}` : line1;
        document.getElementById('rev_address').textContent = fullAddress;
        document.getElementById('rev_city_state').textContent = `${val('city')}, ${val('state')} - ${val('pincode')}`;
        document.getElementById('rev_country').textContent = val('country');

        // 4. CRM Details
        document.getElementById('rev_lead_source').textContent = val('lead_source');

        const assignedSelect = document.getElementById('assigned_to');
        let assignedName = 'Self (Auto-assigned)';
        if (assignedSelect && assignedSelect.selectedIndex >= 0 && assignedSelect.value) {
            assignedName = assignedSelect.options[assignedSelect.selectedIndex].text;
        }
        document.getElementById('rev_assigned_to').textContent = assignedName;

        document.getElementById('rev_status').textContent = val('status');
        document.getElementById('rev_tags').textContent = val('tags');
        document.getElementById('rev_notes').textContent = val('notes');

        // Documents count
        const docsSummary = document.getElementById('rev_documents');
        if (docsSummary) {
            if (selectedFiles.length === 0) {
                docsSummary.textContent = 'None attached';
            } else {
                docsSummary.textContent = `${selectedFiles.length} file(s): ` + selectedFiles.map(f => f.name).join(', ');
            }
        }
    }

    async function handleFormSubmit(e) {
        e.preventDefault();
        if (isSubmitting) return;

        // Final sanity validation across steps 1-4
        for (let s = 1; s <= 4; s++) {
            if (!validateStep(s)) {
                currentStep = s;
                updateWizardUI();
                return;
            }
        }

        const submitBtn = document.getElementById('submitClientBtn');
        isSubmitting = true;
        UI.buttonLoading(submitBtn, true, 'Submitting Registration...');

        const form = document.getElementById('clientCreateForm');
        const formData = new FormData(form);

        // Append custom tracked files
        formData.delete('documents[]');
        formData.delete('documents');
        selectedFiles.forEach(file => {
            formData.append('documents[]', file);
        });

        // Ensure CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content
            || document.getElementById('csrfToken')?.value
            || '';

        try {
            const response = await fetch('/api/clients', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            let data;
            const contentType = response.headers.get('content-type') || '';
            if (contentType.includes('application/json')) {
                data = await response.json();
            } else {
                data = { status: response.ok ? 'success' : 'error', message: await response.text() };
            }

            if (response.ok && data.status === 'success') {
                UI.toast('Client registered successfully!', 'success', 'Success');
                setTimeout(() => {
                    window.location.href = '/clients';
                }, 1000);
            } else if (response.status === 422 && data.errors) {
                // Route server validation errors to the appropriate step!
                let earliestStep = 5;
                for (const field of Object.keys(data.errors)) {
                    const stepNum = fieldStepMap[field] || 1;
                    if (stepNum < earliestStep) {
                        earliestStep = stepNum;
                    }
                }

                currentStep = earliestStep;
                updateWizardUI();
                UI.showFieldErrors(form, data.errors);

                const firstErrKey = Object.keys(data.errors)[0];
                const input = form.querySelector(`[name="${firstErrKey}"]`) || form.querySelector(`#${firstErrKey}`);
                if (input) input.focus();

                UI.toast(data.message || 'Validation failed. Please review errors.', 'warning', 'Validation Error');
            } else {
                UI.toast(data.message || 'Failed to register client.', 'danger', 'Submission Error');
            }
        } catch (err) {
            UI.toast(err.message || 'Network error occurred during registration.', 'danger', 'Error');
        } finally {
            isSubmitting = false;
            UI.buttonLoading(submitBtn, false);
        }
    }

    document.addEventListener('DOMContentLoaded', init);
})();
