/**
 * Client Profile Page Controller
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

    if (!clientId) {
        window.location.href = '/clients';
        return;
    }

    let clientData = null;

    // Load Client Profile
    async function loadProfile() {
        // Reset loaders
        setLoaders();

        try {
            const res = await api.get(`/api/clients/${clientId}`);
            if (!res || !res.data) {
                showProfileError('Client not found or access denied.');
                return;
            }

            clientData = res.data.client || res.data;
            const documents = res.data.documents || [];
            const activities = res.data.activities || [];

            renderHeader(clientData);
            renderOverview(clientData);
            renderDocuments(documents);
            renderTimeline(activities);
            loadClientFollowUps();
        } catch (err) {
            console.error('Error loading client profile:', err);
            showProfileError(err.message || 'Failed to load client profile.');
        }
    }

    function setLoaders() {
        const breadcrumb = document.getElementById('breadcrumbClientCode');
        if (breadcrumb) breadcrumb.textContent = 'Loading...';

        const docsTbody = document.getElementById('documentsTableBody');
        if (docsTbody) {
            docsTbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> Loading documents...</td></tr>';
        }

        const timeline = document.getElementById('activityTimelineList');
        if (timeline) {
            timeline.innerHTML = '<div class="text-muted text-center py-3"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> Loading activity history...</div>';
        }

        const followupsTbody = document.getElementById('followUpsTableBody');
        if (followupsTbody) {
            followupsTbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm text-primary" role="status"></div> Loading follow-ups...</td></tr>';
        }
    }

    function showProfileError(message) {
        const breadcrumb = document.getElementById('breadcrumbClientCode');
        if (breadcrumb) breadcrumb.textContent = 'Error';

        const docsTbody = document.getElementById('documentsTableBody');
        if (docsTbody) {
            docsTbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-danger">${UI.escape(message)} <button type="button" class="btn btn-sm btn-link retry-profile-btn ms-2">Retry</button></td></tr>`;
        }

        const timeline = document.getElementById('activityTimelineList');
        if (timeline) {
            timeline.innerHTML = `<div class="text-danger text-center py-3">${UI.escape(message)} <button type="button" class="btn btn-sm btn-link retry-profile-btn d-block mx-auto mt-1">Retry</button></div>`;
        }

        const followupsTbody = document.getElementById('followUpsTableBody');
        if (followupsTbody) {
            followupsTbody.innerHTML = `<tr><td colspan="5" class="text-center py-4 text-danger">Failed to load follow-ups. <button type="button" class="btn btn-sm btn-link retry-profile-btn ms-2">Retry</button></td></tr>`;
        }

        UI.toast(message, 'danger');
    }

    function renderHeader(c) {
        const breadcrumb = document.getElementById('breadcrumbClientCode');
        if (breadcrumb) breadcrumb.textContent = c.client_code || `#${c.id}`;

        const headerName = document.getElementById('headerClientName');
        if (headerName) headerName.textContent = c.name || 'Unnamed Client';

        const headerCode = document.getElementById('headerClientCode');
        if (headerCode) headerCode.textContent = c.client_code || '-';

        const statusBadge = document.getElementById('headerClientStatus');
        if (statusBadge) {
            const st = (c.status || 'new').toLowerCase();
            statusBadge.textContent = st.toUpperCase();
            statusBadge.className = 'badge text-uppercase ' + (st === 'active' ? 'bg-success' : (st === 'new' ? 'bg-info text-dark' : 'bg-warning text-dark'));
        }

        const clientType = document.getElementById('viewClientType');
        if (clientType) {
            clientType.textContent = (c.client_type === 'company' ? 'Company Account' : 'Individual Client');
        }

        // Configure DPDP modal target code
        const anonTarget = document.getElementById('anonymizeClientCodeTarget');
        if (anonTarget) anonTarget.textContent = c.client_code || '';
    }

    function renderOverview(c) {
        setText('viewName', c.name);
        setText('viewEmail', c.email);
        setText('viewMobile', c.mobile);
        setText('viewAltMobile', c.alt_mobile || 'None');

        const webEl = document.getElementById('viewWebsite');
        if (webEl) {
            webEl.innerHTML = c.website
                ? `<a href="${UI.escape(c.website)}" target="_blank" rel="noopener noreferrer">${UI.escape(c.website)}</a>`
                : 'None';
        }

        setText('viewGst', c.gst_no || 'Not Registered');
        setText('viewPan', c.pan_no || 'Not Provided');
        setText('viewIndustry', c.industry || 'Not Specified');
        setText('viewCompanySize', c.company_size || 'Not Specified');

        const addr = [c.address_line1, c.address_line2, c.city, c.state, c.pincode, c.country].filter(Boolean).join(', ');
        setText('viewAddress', addr || '-');

        const contactWrap = document.getElementById('viewContactPersonWrapper');
        if (contactWrap) {
            if (c.client_type === 'company') {
                contactWrap.classList.remove('d-none');
                setText('viewContactPerson', c.contact_person || 'Not Provided');
            } else {
                contactWrap.classList.add('d-none');
            }
        }

        setText('viewAssignedTo', c.assigned_to_name || c.assigned_user_name || 'Unassigned');
        setText('viewLeadSource', c.lead_source || 'Direct');
        setText('viewCreatedAt', (c.created_at || '').substring(0, 16) || '-');

        const consentBadge = document.getElementById('viewConsent');
        if (consentBadge) {
            if (c.consent_given) {
                consentBadge.className = 'badge bg-success-subtle text-success border border-success-subtle';
                consentBadge.textContent = 'Consent Verified';
            } else {
                consentBadge.className = 'badge bg-secondary-subtle text-secondary border border-secondary-subtle';
                consentBadge.textContent = 'Pending Consent';
            }
        }

        const tagsBox = document.getElementById('viewTagsContainer');
        if (tagsBox) {
            if (c.tags) {
                const tagArr = c.tags.split(',').map(t => t.trim()).filter(Boolean);
                tagsBox.innerHTML = tagArr.map(t => `<span class="badge bg-light text-secondary border">${UI.escape(t)}</span>`).join(' ');
            } else {
                tagsBox.innerHTML = '<span class="text-muted small">None</span>';
            }
        }

        setText('viewNotes', c.notes || 'No notes added.');
    }

    function setText(id, text) {
        const el = document.getElementById(id);
        if (el) el.textContent = text || '-';
    }

    function renderDocuments(docs) {
        const countBadge = document.getElementById('docsCountBadge');
        if (countBadge) countBadge.textContent = docs.length;

        const tbody = document.getElementById('documentsTableBody');
        if (!tbody) return;

        if (!docs || docs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No documents uploaded.</td></tr>';
            return;
        }

        let html = '';
        docs.forEach(d => {
            const kb = (d.size_bytes / 1024).toFixed(1);
            const sizeStr = kb > 1024 ? (kb / 1024).toFixed(2) + ' MB' : kb + ' KB';
            const dateStr = (d.created_at || '').substring(0, 10);

            html += `<tr>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        <svg class="text-primary flex-shrink-0" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        <span class="fw-medium text-truncate" style="max-width: 220px;">${UI.escape(d.original_name)}</span>
                    </div>
                </td>
                <td><small class="text-muted font-monospace">${UI.escape(d.mime_type)}</small></td>
                <td><small class="text-muted">${sizeStr}</small></td>
                <td><small class="text-muted">${dateStr}</small></td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm">
                        <a href="/api/clients/${clientId}/documents/${d.id}" target="_blank" class="btn btn-outline-secondary" title="Download">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        </a>
                        <button type="button" class="btn btn-outline-danger delete-doc-btn" data-id="${d.id}" data-name="${UI.escape(d.original_name)}" title="Delete">
                            &times;
                        </button>
                    </div>
                </td>
            </tr>`;
        });
        tbody.innerHTML = html;
    }

    function renderTimeline(activities) {
        const container = document.getElementById('activityTimelineList');
        if (!container) return;

        if (!activities || activities.length === 0) {
            container.innerHTML = '<div class="text-muted text-center py-3">No activity yet</div>';
            return;
        }

        let html = '<div class="list-group list-group-flush">';
        activities.forEach(a => {
            const timeStr = (a.created_at || '').substring(0, 16);
            let actionBadge = '<span class="badge bg-secondary">Event</span>';
            let description = '';

            switch (a.action) {
                case 'create':
                    actionBadge = '<span class="badge bg-primary">Registered</span>';
                    description = 'New client record created.';
                    break;
                case 'update':
                    actionBadge = '<span class="badge bg-info text-dark">Updated</span>';
                    description = 'Client details modified.';
                    break;
                case 'document_upload':
                    actionBadge = '<span class="badge bg-success">Uploaded Doc</span>';
                    description = 'Document attachment added.';
                    break;
                case 'document_delete':
                    actionBadge = '<span class="badge bg-warning text-dark">Removed Doc</span>';
                    description = 'Document attachment removed.';
                    break;
                case 'delete':
                    actionBadge = '<span class="badge bg-danger">Deleted</span>';
                    description = 'Client marked as soft-deleted.';
                    break;
                case 'dpdp_anonymize':
                    actionBadge = '<span class="badge bg-dark">DPDP Erasure</span>';
                    description = 'Client personal data permanently anonymized.';
                    break;
                case 'followup_created':
                    actionBadge = '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">Follow-up Scheduled</span>';
                    description = 'New follow-up interaction scheduled.';
                    break;
                case 'followup_done':
                    actionBadge = '<span class="badge bg-success">Follow-up Completed</span>';
                    description = 'Follow-up marked as completed.';
                    break;
                case 'followup_updated':
                    actionBadge = '<span class="badge bg-secondary">Follow-up Updated</span>';
                    description = 'Follow-up interaction details modified.';
                    break;
                case 'followup_deleted':
                    actionBadge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Follow-up Removed</span>';
                    description = 'Follow-up interaction deleted.';
                    break;
                default:
                    actionBadge = `<span class="badge bg-secondary">${UI.escape(a.action)}</span>`;
                    description = a.action;
            }

            let diffHtml = '';
            if (a.new_values) {
                try {
                    const newVals = typeof a.new_values === 'string' ? JSON.parse(a.new_values) : a.new_values;
                    const oldVals = a.old_values ? (typeof a.old_values === 'string' ? JSON.parse(a.old_values) : a.old_values) : {};
                    const changedKeys = Object.keys(newVals);
                    if (changedKeys.length > 0) {
                        diffHtml = '<div class="mt-1 pt-1 border-top small text-muted">';
                        changedKeys.forEach(k => {
                            if (k === 'followup_id') return;
                            const oldV = oldVals[k] !== undefined && oldVals[k] !== null ? String(oldVals[k]) : 'none';
                            const newV = newVals[k] !== undefined && newVals[k] !== null ? String(newVals[k]) : 'none';
                            if (a.action === 'update') {
                                diffHtml += `<div><code>${UI.escape(k)}</code>: <del class="text-danger">${UI.escape(oldV)}</del> &rarr; <span class="text-success">${UI.escape(newV)}</span></div>`;
                            } else {
                                diffHtml += `<div><span class="text-secondary fw-semibold">${UI.escape(k)}:</span> <span class="text-dark">${UI.escape(newV)}</span></div>`;
                            }
                        });
                        diffHtml += '</div>';
                    }
                } catch (err) {}
            }

            html += `<div class="list-group-item px-0 py-2 border-bottom">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    ${actionBadge}
                    <span class="text-muted" style="font-size: 0.75rem;">${timeStr}</span>
                </div>
                <div class="text-truncate small text-dark fw-medium">${description}</div>
                <div class="text-truncate text-muted small">By: <strong>${UI.escape(a.user_name || 'System')}</strong></div>
                ${diffHtml}
            </div>`;
        });
        html += '</div>';
        container.innerHTML = html;
    }

    // Load and Render Client Follow-ups
    async function loadClientFollowUps() {
        try {
            const res = await api.get(`/api/clients/${clientId}/followups`);
            const followUps = res?.data || [];
            renderFollowUps(followUps);
        } catch (err) {
            console.error('Failed to load client follow-ups', err);
            const tbody = document.getElementById('followUpsTableBody');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger small">Failed to load follow-ups. <button type="button" class="btn btn-sm btn-link retry-followups-btn">Retry</button></td></tr>';
            }
        }
    }

    function renderFollowUps(followUps) {
        const badge = document.getElementById('followUpsCountBadge');
        if (badge) badge.textContent = followUps.length;

        const tbody = document.getElementById('followUpsTableBody');
        if (!tbody) return;

        if (!followUps || followUps.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No follow-ups scheduled for this client.</td></tr>';
            return;
        }

        let html = '';
        const now = new Date();
        followUps.forEach(f => {
            const dueDate = new Date(f.due_at.replace(' ', 'T'));
            const isOverdue = f.status === 'pending' && dueDate < now;
            const formattedDate = f.due_at.substring(0, 16);

            let typeBadge = '<span class="badge bg-secondary">Other</span>';
            if (f.type === 'call') typeBadge = '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">Call</span>';
            else if (f.type === 'meeting') typeBadge = '<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle">Meeting</span>';
            else if (f.type === 'email') typeBadge = '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Email</span>';

            let statusBadge = '<span class="badge bg-secondary">Unknown</span>';
            if (f.status === 'pending') {
                statusBadge = isOverdue
                    ? '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Overdue</span>'
                    : '<span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle">Pending</span>';
            } else if (f.status === 'done') {
                statusBadge = '<span class="badge bg-success-subtle text-success border border-success-subtle">Done</span>';
            } else if (f.status === 'missed') {
                statusBadge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Missed</span>';
            }

            let notesHtml = UI.escape(f.notes || '-');
            if (notesHtml.includes('[Outcome]:')) {
                notesHtml = notesHtml.replace('[Outcome]:', '<strong class="text-success d-block mt-1">[Outcome]:</strong>');
            }

            html += `<tr>
                <td>${typeBadge}</td>
                <td>
                    <div class="fw-semibold small">${formattedDate}</div>
                    ${isOverdue ? '<span class="badge bg-danger text-white" style="font-size: 0.65rem;">Past Due</span>' : ''}
                </td>
                <td>${statusBadge}</td>
                <td><small class="text-muted d-block text-break" style="max-width: 280px;">${notesHtml}</small></td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm">
                        ${f.status === 'pending' ? `
                        <button type="button" class="btn btn-outline-success mark-done-followup-btn" data-id="${f.id}" title="Complete Follow-up">
                            &#10003; Done
                        </button>` : ''}
                        <button type="button" class="btn btn-outline-danger delete-followup-btn" data-id="${f.id}" title="Delete Follow-up">
                            &times;
                        </button>
                    </div>
                </td>
            </tr>`;
        });
        tbody.innerHTML = html;
    }

    // Event Delegation: Delete Document
    document.getElementById('documentsTableBody')?.addEventListener('click', async (e) => {
        const btn = e.target.closest('.delete-doc-btn');
        if (!btn) return;

        const docId = btn.dataset.id;
        const docName = btn.dataset.name;
        if (!confirm(`Delete document "${docName}"?`)) return;

        try {
            await api.delete(`/api/clients/${clientId}/documents/${docId}`);
            UI.toast('Document deleted successfully.', 'success');
            loadProfile();
        } catch (err) {
            UI.toast(err.message || 'Failed to delete document.', 'danger');
        }
    });

    // Upload Document Handling
    const openUploadBtn = document.getElementById('openUploadDocBtn');
    if (openUploadBtn) {
        openUploadBtn.addEventListener('click', () => {
            const form = document.getElementById('uploadDocForm');
            if (form) form.reset();
            const modalEl = document.getElementById('uploadDocumentModal');
            if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    }

    document.getElementById('uploadDocForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = document.getElementById('submitUploadDocBtn');
        const fileInput = document.getElementById('singleDocInput');

        if (!fileInput.files || fileInput.files.length === 0) {
            UI.toast('Please choose a file to upload.', 'warning');
            return;
        }

        UI.buttonLoading(submitBtn, true, 'Uploading...');
        const formData = new FormData();
        formData.append('document', fileInput.files[0]);

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
            const res = await fetch(`/api/clients/${clientId}/documents`, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-Token': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            });

            const data = await res.json();
            if (res.ok && data.status === 'success') {
                UI.toast('Document uploaded successfully!', 'success');
                const modalEl = document.getElementById('uploadDocumentModal');
                if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                loadProfile();
            } else {
                UI.toast(data.message || 'Document upload failed.', 'danger');
            }
        } catch (err) {
            UI.toast('Network error during upload.', 'danger');
        } finally {
            UI.buttonLoading(submitBtn, false);
        }
    });

    // Delete Client Modal Handling
    const openDeleteBtn = document.getElementById('openDeleteModalBtn');
    if (openDeleteBtn) {
        openDeleteBtn.addEventListener('click', () => {
            const modalEl = document.getElementById('profileDeleteModal');
            if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    }

    document.getElementById('confirmProfileDeleteBtn')?.addEventListener('click', async () => {
        const btn = document.getElementById('confirmProfileDeleteBtn');
        UI.buttonLoading(btn, true, 'Deleting...');

        try {
            await api.delete(`/api/clients/${clientId}`);
            UI.toast('Client deleted successfully.', 'success');
            const modalEl = document.getElementById('profileDeleteModal');
            if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            setTimeout(() => window.location.href = '/clients', 1000);
        } catch (err) {
            UI.toast(err.message || 'Failed to delete client.', 'danger');
            UI.buttonLoading(btn, false);
        }
    });

    // DPDP Anonymization Modal Handling
    const openAnonymizeBtn = document.getElementById('openAnonymizeModalBtn');
    const anonymizeInput = document.getElementById('anonymizeConfirmCode');
    const confirmAnonymizeBtn = document.getElementById('confirmProfileAnonymizeBtn');

    if (openAnonymizeBtn) {
        openAnonymizeBtn.addEventListener('click', () => {
            if (anonymizeInput) anonymizeInput.value = '';
            if (confirmAnonymizeBtn) confirmAnonymizeBtn.disabled = true;
            const modalEl = document.getElementById('profileAnonymizeModal');
            if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    }

    if (anonymizeInput && confirmAnonymizeBtn) {
        anonymizeInput.addEventListener('input', () => {
            const expectedCode = (clientData?.client_code || '').trim().toUpperCase();
            const typedCode = anonymizeInput.value.trim().toUpperCase();
            confirmAnonymizeBtn.disabled = !(expectedCode !== '' && typedCode === expectedCode);
        });
    }

    if (confirmAnonymizeBtn) {
        confirmAnonymizeBtn.addEventListener('click', async () => {
            const expectedCode = (clientData?.client_code || '').trim().toUpperCase();
            const typedCode = anonymizeInput?.value.trim().toUpperCase() || '';
            if (expectedCode === '' || typedCode !== expectedCode) {
                UI.toast('Client code does not match. Please verify and try again.', 'warning');
                return;
            }

            UI.buttonLoading(confirmAnonymizeBtn, true, 'Anonymizing...');
            try {
                await api.post(`/api/clients/${clientId}/anonymize`);
                UI.toast('Client personal data permanently anonymized pursuant to DPDP Act.', 'success');
                const modalEl = document.getElementById('profileAnonymizeModal');
                if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).hide();
                setTimeout(() => window.location.href = '/clients', 1200);
            } catch (err) {
                UI.toast(err.message || 'Failed to anonymize client.', 'danger');
                UI.buttonLoading(confirmAnonymizeBtn, false);
            }
        });
    }

    // Schedule Follow-up Handling
    const openAddFollowUpBtn = document.getElementById('openAddFollowUpBtn');
    if (openAddFollowUpBtn) {
        openAddFollowUpBtn.addEventListener('click', () => {
            const form = document.getElementById('scheduleFollowUpForm');
            if (form) {
                form.reset();
                UI.clearErrors(form);
            }
            const tmr = new Date();
            tmr.setDate(tmr.getDate() + 1);
            tmr.setHours(10, 0, 0, 0);
            const isoStr = new Date(tmr.getTime() - tmr.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            const dueInput = document.getElementById('followup_due_at');
            if (dueInput) dueInput.value = isoStr;

            const modalEl = document.getElementById('scheduleFollowUpModal');
            if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
        });
    }

    document.getElementById('scheduleFollowUpForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = document.getElementById('scheduleFollowUpForm');
        const submitBtn = document.getElementById('submitScheduleFollowUpBtn');
        UI.clearErrors(form);
        UI.buttonLoading(submitBtn, true, 'Scheduling...');

        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        try {
            await api.post(`/api/clients/${clientId}/followups`, payload);
            UI.toast('Follow-up scheduled successfully!', 'success');
            const modalEl = document.getElementById('scheduleFollowUpModal');
            if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            loadProfile();
        } catch (err) {
            if (err.errors) {
                UI.showFieldErrors(form, err.errors);
            } else {
                UI.toast(err.message || 'Failed to schedule follow-up.', 'danger');
            }
        } finally {
            UI.buttonLoading(submitBtn, false);
        }
    });

    // Event Delegation: Mark Done & Delete Follow-up
    document.getElementById('followUpsTableBody')?.addEventListener('click', async (e) => {
        const markDoneBtn = e.target.closest('.mark-done-followup-btn');
        if (markDoneBtn) {
            const id = markDoneBtn.dataset.id;
            const idInput = document.getElementById('markDoneFollowUpId');
            if (idInput) idInput.value = id;
            const form = document.getElementById('markDoneForm');
            if (form) form.reset();
            const modalEl = document.getElementById('markDoneModal');
            if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
            return;
        }

        const delBtn = e.target.closest('.delete-followup-btn');
        if (delBtn) {
            const id = delBtn.dataset.id;
            if (!confirm('Are you sure you want to delete this follow-up?')) return;

            try {
                await api.delete(`/api/followups/${id}`);
                UI.toast('Follow-up deleted successfully.', 'success');
                loadProfile();
            } catch (err) {
                UI.toast(err.message || 'Failed to delete follow-up.', 'danger');
            }
        }
    });

    document.getElementById('markDoneForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('markDoneFollowUpId')?.value;
        const outcome = document.getElementById('mark_done_outcome')?.value || '';
        const submitBtn = document.getElementById('submitMarkDoneBtn');

        UI.buttonLoading(submitBtn, true, 'Saving...');
        try {
            await api.put(`/api/followups/${id}`, {
                status: 'done',
                outcome: outcome
            });
            UI.toast('Follow-up marked as completed!', 'success');
            const modalEl = document.getElementById('markDoneModal');
            if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            loadProfile();
        } catch (err) {
            UI.toast(err.message || 'Failed to complete follow-up.', 'danger');
        } finally {
            UI.buttonLoading(submitBtn, false);
        }
    });

    // Global Retry Handlers
    document.addEventListener('click', (e) => {
        if (e.target.closest('.retry-profile-btn')) {
            loadProfile();
        } else if (e.target.closest('.retry-followups-btn')) {
            loadClientFollowUps();
        }
    });

    // Initial Load
    loadProfile();
});
