/**
 * Client Edit Multi-Step Wizard
 */
(function () {
    'use strict';

    let currentStep = 1;
    const totalSteps = 5;
    let isSubmitting = false;

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
        notes: 4
    };

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
        'Dadra and Nagar Haveli and Daman and Diu': ['26'],
        'Maharashtra': ['27'],
        'Andhra Pradesh': ['28', '37'],
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
        const form = document.getElementById('clientEditForm');
        if (!form) return;

        // Toggle company contact person on client_type change
        const typeRadios = form.querySelectorAll('input[name="client_type"]');
        typeRadios.forEach(radio => {
            radio.addEventListener('change', () => {
                const isCompany = form.querySelector('#type_company').checked;
                const contactWrapper = document.getElementById('contactPersonWrapper');
                const nameLabel = document.getElementById('clientNameLabel');

                if (isCompany) {
                    contactWrapper?.classList.remove('d-none');
                    if (nameLabel) nameLabel.innerHTML = 'Company Name <span class="text-danger">*</span>';
                } else {
                    contactWrapper?.classList.add('d-none');
                    if (nameLabel) nameLabel.innerHTML = 'Full Name <span class="text-danger">*</span>';
                }
            });
        });

        // Stepper item click navigation
        const stepItems = document.querySelectorAll('.wizard-step-item');
        stepItems.forEach(item => {
            item.addEventListener('click', () => {
                const target = parseInt(item.dataset.step, 10);
                if (target < currentStep) {
                    currentStep = target;
                    updateWizardUI();
                } else if (target > currentStep) {
                    for (let s = currentStep; s < target; s++) {
                        if (!validateStep(s)) {
                            return;
                        }
                    }
                    currentStep = target;
                    updateWizardUI();
                }
            });
        });

        // Prev & Next Buttons
        document.getElementById('prevStepBtn')?.addEventListener('click', () => {
            if (currentStep > 1) {
                currentStep--;
                updateWizardUI();
            }
        });

        document.getElementById('nextStepBtn')?.addEventListener('click', () => {
            if (validateStep(currentStep)) {
                if (currentStep < totalSteps) {
                    currentStep++;
                    updateWizardUI();
                }
            }
        });

        // Form Submit Handler
        form.addEventListener('submit', handleSubmit);

        updateWizardUI();
    }

    function updateWizardUI() {
        for (let i = 1; i <= totalSteps; i++) {
            const pane = document.getElementById(`stepPane${i}`);
            if (pane) {
                if (i === currentStep) {
                    pane.classList.remove('d-none');
                } else {
                    pane.classList.add('d-none');
                }
            }
        }

        const stepItems = document.querySelectorAll('.wizard-step-item');
        stepItems.forEach(item => {
            const s = parseInt(item.dataset.step, 10);
            item.classList.remove('active', 'completed');
            if (s === currentStep) {
                item.classList.add('active');
            } else if (s < currentStep) {
                item.classList.add('completed');
            }
        });

        const progressPercent = ((currentStep) / totalSteps) * 100;
        const pbar = document.getElementById('wizardProgressBar');
        if (pbar) {
            pbar.style.width = `${progressPercent}%`;
            pbar.setAttribute('aria-valuenow', String(progressPercent));
        }

        const prevBtn = document.getElementById('prevStepBtn');
        const nextBtn = document.getElementById('nextStepBtn');
        const submitBtn = document.getElementById('submitEditBtn');

        if (prevBtn) {
            if (currentStep === 1) prevBtn.classList.add('d-none');
            else prevBtn.classList.remove('d-none');
        }

        if (currentStep === totalSteps) {
            if (nextBtn) nextBtn.classList.add('d-none');
            if (submitBtn) submitBtn.classList.remove('d-none');
            buildReviewTable();
        } else {
            if (nextBtn) nextBtn.classList.remove('d-none');
            if (submitBtn) submitBtn.classList.add('d-none');
        }

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function validateStep(step) {
        const form = document.getElementById('clientEditForm');
        UI.clearErrors(form);
        const errors = {};

        if (step === 1) {
            const isCompany = form.querySelector('#type_company').checked;
            const name = form.querySelector('#name').value.trim();
            const contactPerson = form.querySelector('#contact_person').value.trim();
            const email = form.querySelector('#email').value.trim();
            const mobile = form.querySelector('#mobile').value.trim();
            const altMobile = form.querySelector('#alt_mobile').value.trim();

            if (!name) {
                errors.name = isCompany ? 'Company name is required.' : 'Full name is required.';
            } else if (name.length < 2) {
                errors.name = 'Name must be at least 2 characters.';
            }

            if (isCompany && !contactPerson) {
                errors.contact_person = 'Contact person is required for company accounts.';
            }

            if (!email) {
                errors.email = 'Email address is required.';
            } else if (!REGEX_EMAIL.test(email)) {
                errors.email = 'Please enter a valid email address.';
            }

            if (!mobile) {
                errors.mobile = 'Mobile number is required.';
            } else if (!REGEX_MOBILE.test(mobile)) {
                errors.mobile = 'Mobile must be a valid 10-digit number starting with 6-9.';
            }

            if (altMobile && !REGEX_MOBILE.test(altMobile)) {
                errors.alt_mobile = 'Alternate mobile must be a valid 10-digit number.';
            }
        } else if (step === 2) {
            const gst = form.querySelector('#gst_no').value.trim().toUpperCase();
            const pan = form.querySelector('#pan_no').value.trim().toUpperCase();
            const website = form.querySelector('#website').value.trim();
            const selectedState = form.querySelector('#state')?.value?.trim() || '';

            if (website && !REGEX_URL.test(website)) {
                errors.website = 'Please enter a valid website URL.';
            }

            if (gst) {
                if (!REGEX_GST.test(gst)) {
                    errors.gst_no = 'Invalid GSTIN format (15 characters, e.g. 27AAAAA0000A1Z5).';
                } else if (selectedState) {
                    const prefix = gst.substring(0, 2);
                    const validPrefixes = GST_STATE_CODES[selectedState];
                    if (validPrefixes && !validPrefixes.includes(prefix)) {
                        errors.gst_no = `GSTIN state code (${prefix}) does not match selected state (${selectedState}).`;
                    }
                }
            }

            if (pan && !REGEX_PAN.test(pan) && !pan.includes('*')) {
                errors.pan_no = 'Invalid PAN format (10 characters, e.g. ABCDE1234F).';
            }
        } else if (step === 3) {
            const addr1 = form.querySelector('#address_line1').value.trim();
            const pincode = form.querySelector('#pincode').value.trim();
            const city = form.querySelector('#city').value.trim();
            const state = form.querySelector('#state').value.trim();
            const gst = form.querySelector('#gst_no').value.trim().toUpperCase();

            if (!addr1) {
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
            } else if (gst && REGEX_GST.test(gst)) {
                const prefix = gst.substring(0, 2);
                const validPrefixes = GST_STATE_CODES[state];
                if (validPrefixes && !validPrefixes.includes(prefix)) {
                    errors.state = `Selected state (${state}) does not match GSTIN prefix (${prefix}).`;
                }
            }
        }

        if (Object.keys(errors).length > 0) {
            UI.showFieldErrors(form, errors);
            const firstErr = Object.keys(errors)[0];
            const el = form.querySelector(`[name="${firstErr}"]`) || form.querySelector(`#${firstErr}`);
            if (el) el.focus();
            return false;
        }

        return true;
    }

    function buildReviewTable() {
        const form = document.getElementById('clientEditForm');
        const tbody = document.querySelector('#reviewTable tbody');
        if (!tbody) return;

        const isCompany = form.querySelector('#type_company').checked;
        const data = {
            'Client Type': isCompany ? 'Company' : 'Individual',
            'Name': form.querySelector('#name').value.trim(),
            'Contact Person': isCompany ? form.querySelector('#contact_person').value.trim() : 'N/A',
            'Email': form.querySelector('#email').value.trim(),
            'Mobile': form.querySelector('#mobile').value.trim(),
            'Alt Mobile': form.querySelector('#alt_mobile').value.trim() || 'None',
            'Industry': form.querySelector('#industry').value || 'Not Specified',
            'Company Size': form.querySelector('#company_size').value || 'Not Specified',
            'Website': form.querySelector('#website').value.trim() || 'None',
            'GSTIN Number': form.querySelector('#gst_no').value.trim().toUpperCase() || 'None',
            'PAN Number': form.querySelector('#pan_no').value.trim().toUpperCase() || 'None',
            'Address': [
                form.querySelector('#address_line1').value.trim(),
                form.querySelector('#address_line2').value.trim(),
                form.querySelector('#city').value.trim(),
                form.querySelector('#state').value,
                form.querySelector('#pincode').value.trim(),
                form.querySelector('#country').value
            ].filter(Boolean).join(', '),
            'Lead Source': form.querySelector('#lead_source').value || 'None',
            'Client Status': form.querySelector('#status').value.toUpperCase(),
            'Tags': form.querySelector('#tags').value.trim() || 'None',
            'Notes': form.querySelector('#notes').value.trim() || 'None'
        };

        let html = '';
        for (const [key, val] of Object.entries(data)) {
            html += `<tr>
                <th style="width: 25%;" class="text-muted fw-semibold">${UI.escape(key)}</th>
                <td class="fw-medium">${UI.escape(val)}</td>
            </tr>`;
        }
        tbody.innerHTML = html;
    }

    async function handleSubmit(e) {
        e.preventDefault();
        if (isSubmitting) return;

        for (let s = 1; s <= 4; s++) {
            if (!validateStep(s)) {
                currentStep = s;
                updateWizardUI();
                return;
            }
        }

        const form = document.getElementById('clientEditForm');
        const clientId = form.dataset.clientId;
        const submitBtn = document.getElementById('submitEditBtn');

        isSubmitting = true;
        UI.buttonLoading(submitBtn, true, 'Saving Changes...');

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        try {
            const res = await api.put(`/api/clients/${clientId}`, payload);

            if (res && res.status === 'success') {
                UI.toast('Client updated successfully!', 'success', 'Success');
                setTimeout(() => {
                    window.location.href = `/clients/${clientId}`;
                }, 1000);
            } else {
                UI.toast(res?.message || 'Failed to update client.', 'danger', 'Update Error');
            }
        } catch (err) {
            if (err.errors) {
                let earliestStep = 5;
                for (const field of Object.keys(err.errors)) {
                    const stepNum = fieldStepMap[field] || 1;
                    if (stepNum < earliestStep) earliestStep = stepNum;
                }

                currentStep = earliestStep;
                updateWizardUI();
                UI.showFieldErrors(form, err.errors);

                const firstErrKey = Object.keys(err.errors)[0];
                const input = form.querySelector(`[name="${firstErrKey}"]`) || form.querySelector(`#${firstErrKey}`);
                if (input) input.focus();

                UI.toast(err.message || 'Validation failed. Please review errors.', 'warning', 'Validation Error');
            } else {
                UI.toast(err.message || 'Network error occurred while updating client.', 'danger', 'Error');
            }
        } finally {
            isSubmitting = false;
            UI.buttonLoading(submitBtn, false);
        }
    }

    document.addEventListener('DOMContentLoaded', init);
})();
