/**
 * Phase 3: Client Services, Compliance Details & Work Tracker Controller
 */
document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const wrapper = document.getElementById('clientProfileWrapper');
    const clientId = parseInt(
        wrapper?.dataset?.clientId ||
        window.location.pathname.match(/\/clients\/(\d+)/)?.[1] ||
        '0',
        10
    );

    if (!clientId) return;

    let availableServices = [];
    let availableStaff = [];
    let clientServices = [];

    // Helper: get CSRF token
    function getCsrfToken() {
        const metaTag = document.querySelector('meta[name="csrf-token"]');
        if (metaTag) return metaTag.getAttribute('content');
        const input = document.querySelector('input[name="_csrf_token"]');
        return input ? input.value : '';
    }

    // ==========================================
    // 1. Subscribed Services Management
    // ==========================================

    async function loadClientServices() {
        try {
            const res = await fetch(`/api/clients/${clientId}/services`);
            const data = await res.json();
            if (data.status === 'success') {
                clientServices = data.data || [];
                renderClientServices(clientServices);
            }
        } catch (err) {
            console.error('Failed to load client services:', err);
        }
    }

    function renderClientServices(services) {
        const tbody = document.getElementById('clientServicesTableBody');
        const badge1 = document.getElementById('clientServicesCountBadge');
        const badge2 = document.getElementById('servicesCountBadge');

        if (badge1) badge1.textContent = services.length;
        if (badge2) badge2.textContent = services.length;

        if (!tbody) return;

        if (services.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No active services assigned yet. Click "+ Add Service" to enroll.</td></tr>';
            return;
        }

        tbody.innerHTML = services.map(s => {
            let statusBadge = '<span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>';
            if (s.status === 'paused') {
                statusBadge = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle">Paused</span>';
            } else if (s.status === 'completed') {
                statusBadge = '<span class="badge bg-secondary text-white">Completed</span>';
            } else if (s.status === 'cancelled') {
                statusBadge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Cancelled</span>';
            }

            const fee = parseFloat(s.fee || 0).toLocaleString('en-IN', { minimumFractionDigits: 2 });
            const accountant = s.accountant_name || '<span class="text-muted fst-italic">Unassigned</span>';

            return `
                <tr>
                    <td>
                        <div class="fw-bold text-dark">${escapeHtml(s.service_name)}</div>
                        <span class="badge bg-light text-secondary border font-monospace small">${escapeHtml(s.service_code)}</span>
                    </td>
                    <td>
                        <span class="badge bg-secondary-subtle text-secondary border text-capitalize">${escapeHtml(s.frequency || 'Monthly')}</span>
                    </td>
                    <td class="text-end font-monospace fw-bold text-dark">
                        ₹${fee}
                    </td>
                    <td>
                        <div class="small fw-semibold text-dark">${accountant}</div>
                    </td>
                    <td class="small font-monospace text-secondary">
                        ${escapeHtml(s.start_date)}
                    </td>
                    <td class="text-center">
                        ${statusBadge}
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-secondary edit-cs-btn" data-id="${s.id}" title="Edit Service">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-cs-btn" data-id="${s.id}" data-name="${escapeHtml(s.service_name)}" title="Remove Service">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        // Attach edit handlers
        document.querySelectorAll('.edit-cs-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.id, 10);
                const item = clientServices.find(x => parseInt(x.id, 10) === id);
                if (!item) return;

                document.getElementById('editClientServiceId').value = item.id;
                document.getElementById('editClientServiceName').value = item.service_name;
                document.getElementById('editClientServiceFee').value = item.fee;
                document.getElementById('editClientServiceFrequency').value = item.frequency || 'monthly';
                document.getElementById('editClientServiceStatus').value = item.status || 'active';
                document.getElementById('editClientServiceNotes').value = item.notes || '';

                populateStaffSelect('editClientServiceAccountant', item.assigned_accountant_id);

                const modal = new bootstrap.Modal(document.getElementById('editClientServiceModal'));
                modal.show();
            });
        });

        // Attach delete handlers
        document.querySelectorAll('.delete-cs-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.id;
                const name = btn.dataset.name;
                if (!confirm(`Are you sure you want to remove "${name}" from this client?`)) return;

                try {
                    const res = await fetch(`/api/clients/${clientId}/services/${id}/delete`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken(),
                        }
                    });
                    const d = await res.json();
                    if (d.status === 'success') {
                        loadClientServices();
                        loadWorkTracker();
                    } else {
                        alert(d.message || 'Error removing service.');
                    }
                } catch (e) {
                    alert('Network error removing service.');
                }
            });
        });
    }

    // Load available services & staff for dropdowns
    async function loadLookups() {
        try {
            const [srvRes, staffRes] = await Promise.all([
                fetch('/api/services?active_only=1').then(r => r.json()),
                fetch('/api/staff').then(r => r.json())
            ]);

            if (srvRes.status === 'success') availableServices = srvRes.data || [];
            if (staffRes.status === 'success') availableStaff = staffRes.data || [];

            populateServiceSelect();
            populateStaffSelect('serviceAccountantSelect');
            populateStaffSelect('trackerStaffSelect');
        } catch (err) {
            console.error('Error loading service/staff lookups:', err);
        }
    }

    function populateServiceSelect() {
        const select = document.getElementById('serviceSelect');
        if (!select) return;
        select.innerHTML = '<option value="">-- Choose Service --</option>' + availableServices.map(s => {
            const freq = s.frequency ? ` (${s.frequency})` : '';
            return `<option value="${s.id}" data-fee="${s.default_fee}" data-frequency="${s.frequency || 'monthly'}" data-type="${s.type}">${escapeHtml(s.name)} - ₹${parseFloat(s.default_fee).toFixed(2)}${freq}</option>`;
        }).join('');
    }

    function populateStaffSelect(selectId, selectedId = null) {
        const select = document.getElementById(selectId);
        if (!select) return;
        const currentVal = selectedId || select.value;
        select.innerHTML = '<option value="">-- Unassigned --</option>' + availableStaff.map(u => {
            return `<option value="${u.id}" ${parseInt(currentVal, 10) === parseInt(u.id, 10) ? 'selected' : ''}>${escapeHtml(u.name)} (${escapeHtml(u.email)})</option>`;
        }).join('');
    }

    // Auto-fill fee and frequency when service selection changes
    const serviceSelect = document.getElementById('serviceSelect');
    if (serviceSelect) {
        serviceSelect.addEventListener('change', () => {
            const opt = serviceSelect.options[serviceSelect.selectedIndex];
            if (!opt || !opt.value) return;

            const fee = opt.dataset.fee;
            const freq = opt.dataset.frequency;

            if (fee) document.getElementById('serviceFeeInput').value = fee;
            if (freq) document.getElementById('serviceFrequencySelect').value = freq;
        });
    }

    // Open Add Service Modal
    const openAddServiceBtn = document.getElementById('openAddServiceModalBtn');
    if (openAddServiceBtn) {
        openAddServiceBtn.addEventListener('click', () => {
            document.getElementById('addClientServiceForm').reset();
            document.getElementById('serviceStartDateInput').value = new Date().toISOString().split('T')[0];
            populateServiceSelect();
            populateStaffSelect('serviceAccountantSelect');
            const modal = new bootstrap.Modal(document.getElementById('addClientServiceModal'));
            modal.show();
        });
    }

    // Save Client Service Form
    const addClientServiceForm = document.getElementById('addClientServiceForm');
    if (addClientServiceForm) {
        addClientServiceForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('saveClientServiceBtn');
            btn.disabled = true;

            const formData = new FormData(addClientServiceForm);
            const payload = Object.fromEntries(formData.entries());

            try {
                const res = await fetch(`/api/clients/${clientId}/services`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    },
                    body: JSON.stringify(payload)
                });
                const d = await res.json();
                if (d.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('addClientServiceModal')).hide();
                    loadClientServices();
                } else {
                    alert(d.message || 'Failed to assign service.');
                }
            } catch (err) {
                alert('Network error assigning service.');
            } finally {
                btn.disabled = false;
            }
        });
    }

    // Edit Client Service Form Submit
    const editClientServiceForm = document.getElementById('editClientServiceForm');
    if (editClientServiceForm) {
        editClientServiceForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const serviceId = document.getElementById('editClientServiceId').value;
            const btn = document.getElementById('updateClientServiceBtn');
            btn.disabled = true;

            const formData = new FormData(editClientServiceForm);
            const payload = Object.fromEntries(formData.entries());

            try {
                const res = await fetch(`/api/clients/${clientId}/services/${serviceId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    },
                    body: JSON.stringify(payload)
                });
                const d = await res.json();
                if (d.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('editClientServiceModal')).hide();
                    loadClientServices();
                } else {
                    alert(d.message || 'Failed to update service.');
                }
            } catch (err) {
                alert('Network error updating service.');
            } finally {
                btn.disabled = false;
            }
        });
    }

    // ==========================================
    // 2. Compliance Details & Decryption
    // ==========================================

    async function loadComplianceDetails() {
        try {
            const res = await fetch(`/api/clients/${clientId}/compliance`);
            const d = await res.json();
            if (d.status === 'success') {
                renderComplianceDetails(d.data);
            }
        } catch (err) {
            console.error('Failed to load compliance details:', err);
        }
    }

    function renderComplianceDetails(c) {
        if (!c) return;

        const setTxt = (id, val) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val || '—';
        };

        setTxt('compGstin', c.gstin);
        setTxt('compGstFilingType', c.gst_filing_type ? c.gst_filing_type.toUpperCase() : 'MONTHLY');
        setTxt('compPan', c.pan || '—');
        setTxt('compTan', c.tan);
        setTxt('compCin', c.cin_llpin);
        setTxt('compPfEsi', c.pf_esi_codes);
        setTxt('compFy', c.financial_year);

        const portalNotesEl = document.getElementById('compPortalNotes');
        if (portalNotesEl) {
            portalNotesEl.textContent = c.portal_notes || 'No portal notes provided.';
        }

        const revealBtn = document.getElementById('revealCredentialsBtn');
        if (revealBtn) {
            if (c.has_portal_credentials) {
                revealBtn.classList.remove('d-none');
            } else {
                revealBtn.classList.add('d-none');
            }
        }
    }

    // Reveal Credentials Click
    const revealBtn = document.getElementById('revealCredentialsBtn');
    if (revealBtn) {
        revealBtn.addEventListener('click', async () => {
            const box = document.getElementById('credentialsRevealBox');
            const pre = document.getElementById('decryptedCredentialsContent');
            const btnText = document.getElementById('revealBtnText');

            if (!box.classList.contains('d-none')) {
                box.classList.add('d-none');
                btnText.textContent = 'Reveal Credentials';
                return;
            }

            btnText.textContent = 'Decrypting...';
            revealBtn.disabled = true;

            try {
                const res = await fetch(`/api/clients/${clientId}/compliance/reveal`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    }
                });
                const d = await res.json();
                if (d.status === 'success') {
                    pre.textContent = d.data.portal_credentials || 'No credentials found.';
                    box.classList.remove('d-none');
                    btnText.textContent = 'Hide Credentials';
                } else {
                    alert(d.message || 'Access denied: only Admin and Accountant can reveal portal credentials.');
                    btnText.textContent = 'Reveal Credentials';
                }
            } catch (err) {
                alert('Network error while revealing portal credentials.');
                btnText.textContent = 'Reveal Credentials';
            } finally {
                revealBtn.disabled = false;
            }
        });
    }

    // Open Edit Compliance Modal
    const openEditComplianceBtn = document.getElementById('openEditComplianceBtn');
    if (openEditComplianceBtn) {
        openEditComplianceBtn.addEventListener('click', async () => {
            try {
                const res = await fetch(`/api/clients/${clientId}/compliance`);
                const d = await res.json();
                if (d.status === 'success') {
                    const c = d.data;
                    document.getElementById('editCompGstin').value = c.gstin || '';
                    document.getElementById('editCompGstFilingType').value = c.gst_filing_type || 'monthly';
                    document.getElementById('editCompPan').value = c.pan_raw || c.pan || '';
                    document.getElementById('editCompTan').value = c.tan || '';
                    document.getElementById('editCompCin').value = c.cin_llpin || '';
                    document.getElementById('editCompPfEsi').value = c.pf_esi_codes || '';
                    document.getElementById('editCompFy').value = c.financial_year || '';
                    document.getElementById('editCompPortalNotes').value = c.portal_notes || '';
                    document.getElementById('editCompCredentials').value = '';

                    const modal = new bootstrap.Modal(document.getElementById('editComplianceModal'));
                    modal.show();
                }
            } catch (err) {
                alert('Error loading compliance details.');
            }
        });
    }

    // Save Compliance Form Submit
    const editComplianceForm = document.getElementById('editComplianceForm');
    if (editComplianceForm) {
        editComplianceForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('saveComplianceBtn');
            btn.disabled = true;

            const formData = new FormData(editComplianceForm);
            const payload = Object.fromEntries(formData.entries());

            try {
                const res = await fetch(`/api/clients/${clientId}/compliance`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    },
                    body: JSON.stringify(payload)
                });
                const d = await res.json();
                if (d.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('editComplianceModal')).hide();
                    loadComplianceDetails();
                } else {
                    alert(d.message || 'Error saving compliance details.');
                }
            } catch (err) {
                alert('Network error saving compliance details.');
            } finally {
                btn.disabled = false;
            }
        });
    }

    // ==========================================
    // 3. Work Tracker per Service Period
    // ==========================================

    let workTrackerItems = [];

    async function loadWorkTracker() {
        try {
            const res = await fetch(`/api/clients/${clientId}/work-tracker`);
            const d = await res.json();
            if (d.status === 'success') {
                workTrackerItems = d.data || [];
                renderWorkTracker(workTrackerItems);
            }
        } catch (err) {
            console.error('Failed to load work tracker:', err);
        }
    }

    function renderWorkTracker(items) {
        const tbody = document.getElementById('workTrackerTableBody');
        const badge = document.getElementById('workTrackerCountBadge');
        if (badge) badge.textContent = items.length;
        if (!tbody) return;

        if (items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No period filing work items logged yet.</td></tr>';
            return;
        }

        tbody.innerHTML = items.map(item => {
            let statusBadge = '<span class="badge bg-secondary-subtle text-secondary border">Pending</span>';
            if (item.status === 'data_received') {
                statusBadge = '<span class="badge bg-info-subtle text-info border border-info-subtle">Data Received</span>';
            } else if (item.status === 'filed') {
                statusBadge = '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">Filed</span>';
            } else if (item.status === 'acknowledged') {
                statusBadge = '<span class="badge bg-success-subtle text-success border border-success-subtle">Acknowledged</span>';
            }

            const ack = item.acknowledgment_no ? `<span class="font-monospace text-dark small fw-semibold">${escapeHtml(item.acknowledgment_no)}</span>` : '<span class="text-muted small">—</span>';
            const date = item.filing_date ? `<span class="font-monospace text-secondary small">${escapeHtml(item.filing_date)}</span>` : '<span class="text-muted small">—</span>';
            const staff = item.assigned_to_name ? `<span class="small fw-semibold text-dark">${escapeHtml(item.assigned_to_name)}</span>` : '<span class="text-muted small">—</span>';

            return `
                <tr>
                    <td class="fw-bold font-monospace text-dark">
                        ${escapeHtml(item.period)}
                    </td>
                    <td>
                        <div class="fw-semibold small text-dark">${escapeHtml(item.service_name || 'Service')}</div>
                    </td>
                    <td class="text-center">
                        ${statusBadge}
                    </td>
                    <td>
                        ${ack}
                    </td>
                    <td>
                        ${date}
                    </td>
                    <td>
                        ${staff}
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-secondary edit-tracker-btn" data-id="${item.id}" title="Update Work Item">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-danger delete-tracker-btn" data-id="${item.id}" title="Delete Item">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        // Attach tracker handlers
        document.querySelectorAll('.edit-tracker-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const id = parseInt(btn.dataset.id, 10);
                const item = workTrackerItems.find(x => parseInt(x.id, 10) === id);
                if (!item) return;

                document.getElementById('workTrackerItemId').value = item.id;
                document.getElementById('workTrackerModalTitle').textContent = 'Update Work Period Entry';
                document.getElementById('workTrackerServiceWrapper').style.display = 'none';
                document.getElementById('trackerPeriodInput').value = item.period;
                document.getElementById('trackerStatusSelect').value = item.status;
                document.getElementById('trackerAckNoInput').value = item.acknowledgment_no || '';
                document.getElementById('trackerFilingDateInput').value = item.filing_date || '';
                document.getElementById('trackerNotesInput').value = item.notes || '';

                populateStaffSelect('trackerStaffSelect', item.assigned_to);

                const modal = new bootstrap.Modal(document.getElementById('workTrackerModal'));
                modal.show();
            });
        });

        document.querySelectorAll('.delete-tracker-btn').forEach(btn => {
            btn.addEventListener('click', async () => {
                const id = btn.dataset.id;
                if (!confirm('Are you sure you want to delete this work tracker entry?')) return;

                try {
                    const res = await fetch(`/api/clients/${clientId}/work-tracker/${id}/delete`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': getCsrfToken(),
                        }
                    });
                    const d = await res.json();
                    if (d.status === 'success') {
                        loadWorkTracker();
                    } else {
                        alert(d.message || 'Error deleting item.');
                    }
                } catch (e) {
                    alert('Network error deleting item.');
                }
            });
        });
    }

    // Open Add Work Tracker Modal
    const openAddWorkTrackerBtn = document.getElementById('openAddWorkTrackerBtn');
    if (openAddWorkTrackerBtn) {
        openAddWorkTrackerBtn.addEventListener('click', () => {
            if (clientServices.length === 0) {
                alert('Please subscribe the client to at least one service before logging work period entries.');
                return;
            }

            document.getElementById('workTrackerForm').reset();
            document.getElementById('workTrackerItemId').value = '';
            document.getElementById('workTrackerModalTitle').textContent = 'Log Work Period Entry';
            document.getElementById('workTrackerServiceWrapper').style.display = 'block';

            const trackerSrvSelect = document.getElementById('trackerServiceSelect');
            trackerSrvSelect.innerHTML = clientServices.map(s => {
                return `<option value="${s.id}">${escapeHtml(s.service_name)} (${escapeHtml(s.frequency || 'Monthly')})</option>`;
            }).join('');

            // Suggest current month period (e.g. Oct-2026)
            const date = new Date();
            const monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
            document.getElementById('trackerPeriodInput').value = `${monthNames[date.getMonth()]}-${date.getFullYear()}`;

            populateStaffSelect('trackerStaffSelect');

            const modal = new bootstrap.Modal(document.getElementById('workTrackerModal'));
            modal.show();
        });
    }

    // Save/Update Work Tracker Form
    const workTrackerForm = document.getElementById('workTrackerForm');
    if (workTrackerForm) {
        workTrackerForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const itemId = document.getElementById('workTrackerItemId').value;
            const btn = document.getElementById('saveTrackerBtn');
            btn.disabled = true;

            const formData = new FormData(workTrackerForm);
            const payload = Object.fromEntries(formData.entries());

            const url = itemId 
                ? `/api/clients/${clientId}/work-tracker/${itemId}`
                : `/api/clients/${clientId}/work-tracker`;

            try {
                const res = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': getCsrfToken(),
                    },
                    body: JSON.stringify(payload)
                });
                const d = await res.json();
                if (d.status === 'success') {
                    bootstrap.Modal.getInstance(document.getElementById('workTrackerModal')).hide();
                    loadWorkTracker();
                } else {
                    alert(d.message || 'Error saving work tracker item.');
                }
            } catch (err) {
                alert('Network error saving work tracker item.');
            } finally {
                btn.disabled = false;
            }
        });
    }

    function escapeHtml(text) {
        if (!text) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Initialize all Phase 3 sections
    loadLookups();
    loadClientServices();
    loadComplianceDetails();
    loadWorkTracker();
});
