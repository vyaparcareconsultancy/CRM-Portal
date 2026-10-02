/**
 * Leads Management: Table & Kanban Pipeline, Filtering, CSV Import, Conversion, and Follow-ups
 */
document.addEventListener('DOMContentLoaded', () => {
    let currentView = 'kanban';
    let lookupData = { sources: [], services: [], courses: [], staff: [] };
    let draggedLeadId = null;
    let draggedFromStatus = null;
    let pendingDropTargetStatus = null;

    // Modals
    const leadModal = new bootstrap.Modal(document.getElementById('leadModal'));
    const convertModal = new bootstrap.Modal(document.getElementById('convertModal'));
    const lostReasonModal = new bootstrap.Modal(document.getElementById('lostReasonModal'));
    const importModal = new bootstrap.Modal(document.getElementById('importModal'));
    const sourcesModal = document.getElementById('sourcesModal') ? new bootstrap.Modal(document.getElementById('sourcesModal')) : null;
    const leadFollowUpModal = new bootstrap.Modal(document.getElementById('leadFollowUpModal'));

    // DOM Elements
    const viewKanbanBtn = document.getElementById('viewKanbanBtn');
    const viewTableBtn = document.getElementById('viewTableBtn');
    const kanbanContainer = document.getElementById('kanbanViewContainer');
    const tableContainer = document.getElementById('tableViewContainer');

    const filterSearch = document.getElementById('filterSearch');
    const filterStatus = document.getElementById('filterStatus');
    const filterSource = document.getElementById('filterSource');
    const filterAssigned = document.getElementById('filterAssigned');
    const filterDateFrom = document.getElementById('filterDateFrom');
    const resetFiltersBtn = document.getElementById('resetFiltersBtn');

    // 1. Initial Load & Lookups
    async function loadLookups() {
        try {
            const res = await api.get('/api/leads/lookups');
            if (res.data) {
                lookupData = res.data;
                populateFilterSources(lookupData.sources);
                populateModalSources(lookupData.sources);
                if (filterAssigned && lookupData.staff) {
                    populateStaffDropdown(lookupData.staff);
                }
                updateInterestDropdown();
            }
        } catch (err) {
            console.error('Failed to load lookups', err);
        }
    }

    function populateFilterSources(sources) {
        if (!filterSource) return;
        const current = filterSource.value;
        filterSource.innerHTML = '<option value="">All Lead Sources</option>';
        sources.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.name;
            filterSource.appendChild(opt);
        });
        filterSource.value = current;

        const defaultSourceSelect = document.getElementById('defaultSourceSelect');
        if (defaultSourceSelect) {
            defaultSourceSelect.innerHTML = '<option value="">Auto-detect from file</option>';
            sources.forEach(s => {
                const opt = document.createElement('option');
                opt.value = s.id;
                opt.textContent = s.name;
                defaultSourceSelect.appendChild(opt);
            });
        }
    }

    function populateModalSources(sources) {
        const select = document.getElementById('leadSourceSelect');
        if (!select) return;
        select.innerHTML = '<option value="">Select source...</option>';
        sources.forEach(s => {
            const opt = document.createElement('option');
            opt.value = s.id;
            opt.textContent = s.name;
            opt.dataset.name = (s.name || '').toLowerCase();
            select.appendChild(opt);
        });
    }

    function populateStaffDropdown(staff) {
        if (filterAssigned) {
            filterAssigned.innerHTML = '<option value="">All Counselors</option>';
            staff.forEach(u => {
                const opt = document.createElement('option');
                opt.value = u.id;
                opt.textContent = u.name;
                filterAssigned.appendChild(opt);
            });
        }

        const modalStaff = document.getElementById('leadAssignedTo');
        if (modalStaff) {
            modalStaff.innerHTML = '<option value="">Assign to counselor...</option>';
            staff.forEach(u => {
                const opt = document.createElement('option');
                opt.value = u.id;
                opt.textContent = u.name;
                modalStaff.appendChild(opt);
            });
        }
    }

    // Toggle interest dropdown based on radio
    function updateInterestDropdown(selectedVal = '') {
        const isService = document.getElementById('typeService').checked;
        const select = document.getElementById('leadInterestedInSelect');
        const customInput = document.getElementById('leadInterestedInCustom');
        if (!select) return;

        select.innerHTML = '<option value="">Select option...</option>';
        const list = isService ? (lookupData.services || []) : (lookupData.courses || []);
        
        let found = false;
        list.forEach(item => {
            const opt = document.createElement('option');
            opt.value = item.name;
            opt.textContent = item.name;
            if (item.name === selectedVal) {
                opt.selected = true;
                found = true;
            }
            select.appendChild(opt);
        });

        // Add custom option
        const otherOpt = document.createElement('option');
        otherOpt.value = '__custom__';
        otherOpt.textContent = 'Other / Custom...';
        if (selectedVal && !found) {
            otherOpt.selected = true;
            customInput.style.display = 'block';
            customInput.value = selectedVal;
        } else {
            customInput.style.display = 'none';
        }
        select.appendChild(otherOpt);
    }

    document.getElementById('typeService')?.addEventListener('change', () => updateInterestDropdown());
    document.getElementById('typeCourse')?.addEventListener('change', () => updateInterestDropdown());

    document.getElementById('leadInterestedInSelect')?.addEventListener('change', function () {
        const customInput = document.getElementById('leadInterestedInCustom');
        if (this.value === '__custom__') {
            customInput.style.display = 'block';
            customInput.focus();
        } else {
            customInput.style.display = 'none';
            customInput.value = this.value;
        }
    });

    // Lead source referral check
    document.getElementById('leadSourceSelect')?.addEventListener('change', function () {
        const refGroup = document.getElementById('referredByGroup');
        const selectedOpt = this.options[this.selectedIndex];
        const name = selectedOpt ? (selectedOpt.dataset.name || '').toLowerCase() : '';
        if (name === 'referral') {
            refGroup.style.display = 'block';
        } else {
            refGroup.style.display = 'none';
        }
    });

    // Same as mobile checkbox
    document.getElementById('leadWhatsappSame')?.addEventListener('change', function () {
        const whatsappInput = document.getElementById('leadWhatsapp');
        if (this.checked) {
            whatsappInput.value = document.getElementById('leadMobile').value;
        }
    });

    document.getElementById('leadMobile')?.addEventListener('input', function () {
        const sameCheckbox = document.getElementById('leadWhatsappSame');
        if (sameCheckbox && sameCheckbox.checked) {
            document.getElementById('leadWhatsapp').value = this.value;
        }
    });

    // Lost reason visibility
    document.getElementById('leadStatusSelect')?.addEventListener('change', function () {
        const lostGroup = document.getElementById('lostReasonGroup');
        if (this.value === 'lost') {
            lostGroup.style.display = 'block';
        } else {
            lostGroup.style.display = 'none';
        }
    });

    // 2. Fetch & Render Leads
    function getFilterParams() {
        const params = {
            search: filterSearch ? filterSearch.value.trim() : '',
            status: filterStatus ? filterStatus.value : '',
            source: filterSource ? filterSource.value : '',
            date_from: filterDateFrom ? filterDateFrom.value : '',
        };
        if (filterAssigned && filterAssigned.value) {
            params.assigned_to = filterAssigned.value;
        }
        return params;
    }

    async function loadLeads() {
        UI.showProgressBar();
        const filters = getFilterParams();

        try {
            if (currentView === 'kanban') {
                filters.view = 'kanban';
                const res = await api.get('/api/leads', filters);
                renderKanban(res.data || {});
            } else {
                filters.view = 'list';
                const res = await api.get('/api/leads', filters);
                renderTable(res.data || {});
            }
        } catch (err) {
            UI.toast(err.message || 'Failed to load leads.', 'danger');
        } finally {
            UI.hideProgressBar();
        }
    }

    // 3. Render Kanban Board
    function renderKanban(columns) {
        const statuses = ['new', 'contacted', 'interested', 'follow_up', 'converted', 'lost'];
        
        statuses.forEach(st => {
            const container = document.getElementById(`column_${st}`);
            const badge = document.getElementById(`count_${st}`);
            const cards = columns[st] || [];

            if (badge) badge.textContent = cards.length;
            if (!container) return;

            if (cards.length === 0) {
                container.innerHTML = `<div class="text-center text-muted small py-4 empty-column">No leads</div>`;
                return;
            }

            container.innerHTML = cards.map(lead => renderKanbanCard(lead)).join('');
        });

        attachDragAndDropHandlers();
    }

    function renderKanbanCard(lead) {
        const cleanMobile = (lead.mobile || '').replace(/[^0-9]/g, '');
        const cleanWhatsapp = (lead.whatsapp_number || lead.mobile || '').replace(/[^0-9]/g, '');
        const waLink = `https://wa.me/91${cleanWhatsapp}?text=${encodeURIComponent('Hello ' + (lead.name || '') + ', regarding your inquiry at Vyapar Care Consultancy:')}`;
        const telLink = `tel:${cleanMobile}`;
        const sourceLabel = lead.source_name ? `<span class="badge bg-light text-secondary border">${escapeHtml(lead.source_name)}</span>` : '';
        const interestLabel = lead.interested_in ? `<span class="badge bg-primary-subtle text-primary border">${escapeHtml(lead.interested_in)}</span>` : '';
        const counselor = lead.assigned_to_name ? `<span class="small text-muted d-block mt-1">👤 ${escapeHtml(lead.assigned_to_name)}</span>` : '';

        const convertBtn = (lead.status !== 'converted')
            ? `<button type="button" class="btn btn-xs btn-outline-success btn-convert" data-id="${lead.id}" title="Convert Lead">Convert</button>`
            : `<span class="badge bg-success-subtle text-success">Converted</span>`;

        return `
            <div class="kanban-card" draggable="true" data-id="${lead.id}" data-status="${lead.status}">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <span class="fw-bold small text-primary">${escapeHtml(lead.lead_code || '')}</span>
                    <div class="d-flex gap-1">
                        <a href="${waLink}" target="_blank" rel="noopener noreferrer" class="btn btn-xs btn-whatsapp px-2 py-0 rounded" title="Chat on WhatsApp">WA</a>
                        <a href="${telLink}" class="btn btn-xs btn-call px-2 py-0 rounded" title="Call">Call</a>
                    </div>
                </div>
                <div class="fw-bold text-dark mb-1">${escapeHtml(lead.name || '')}</div>
                <div class="small text-muted mb-2">📞 ${escapeHtml(lead.mobile || '')}</div>
                <div class="d-flex flex-wrap gap-1 mb-2">
                    ${interestLabel}
                    ${sourceLabel}
                </div>
                ${counselor}
                <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                    <div>${convertBtn}</div>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary btn-fu" data-id="${lead.id}" data-name="${escapeHtml(lead.name)}" title="Schedule Follow-up">Follow-up</button>
                        <button type="button" class="btn btn-outline-secondary btn-edit" data-id="${lead.id}" title="Edit Lead">Edit</button>
                    </div>
                </div>
            </div>
        `;
    }

    // 4. HTML5 Drag and Drop Handlers
    function attachDragAndDropHandlers() {
        const cards = document.querySelectorAll('.kanban-card');
        const columns = document.querySelectorAll('.kanban-cards');

        cards.forEach(card => {
            card.addEventListener('dragstart', (e) => {
                draggedLeadId = card.dataset.id;
                draggedFromStatus = card.dataset.status;
                card.classList.add('dragging');
                e.dataTransfer.effectAllowed = 'move';
                e.dataTransfer.setData('text/plain', draggedLeadId);
            });

            card.addEventListener('dragend', () => {
                card.classList.remove('dragging');
                columns.forEach(col => col.classList.remove('drag-over'));
            });
        });

        columns.forEach(col => {
            col.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                col.classList.add('drag-over');
            });

            col.addEventListener('dragleave', () => {
                col.classList.remove('drag-over');
            });

            col.addEventListener('drop', async (e) => {
                e.preventDefault();
                col.classList.remove('drag-over');
                const targetStatus = col.dataset.status;

                if (!draggedLeadId || targetStatus === draggedFromStatus) return;

                if (targetStatus === 'lost') {
                    // Prompt for lost reason
                    document.getElementById('lostModalLeadId').value = draggedLeadId;
                    document.getElementById('lostModalReason').value = '';
                    lostReasonModal.show();
                    return;
                }

                if (targetStatus === 'converted') {
                    // Open conversion modal
                    openConvertModal(draggedLeadId);
                    return;
                }

                try {
                    await api.post(`/api/leads/${draggedLeadId}/status`, { status: targetStatus });
                    UI.toast(`Status updated to ${targetStatus}`, 'success');
                    loadLeads();
                } catch (err) {
                    UI.toast(err.message || 'Failed to update status.', 'danger');
                }
            });
        });
    }

    // 5. Render Table View
    function renderTable(data) {
        const tbody = document.getElementById('leadsTableBody');
        const items = data.items || [];
        const info = document.getElementById('leadsPaginationInfo');
        if (info) {
            info.textContent = `Showing ${items.length} of ${data.total || 0} leads`;
        }

        if (items.length === 0) {
            tbody.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-muted">No leads found matching your criteria.</td></tr>`;
            return;
        }

        tbody.innerHTML = items.map(lead => {
            const cleanMobile = (lead.mobile || '').replace(/[^0-9]/g, '');
            const cleanWhatsapp = (lead.whatsapp_number || lead.mobile || '').replace(/[^0-9]/g, '');
            const waLink = `https://wa.me/91${cleanWhatsapp}?text=${encodeURIComponent('Hello ' + (lead.name || '') + ', regarding your inquiry at Vyapar Care Consultancy:')}`;
            const telLink = `tel:${cleanMobile}`;
            const statusBadge = getStatusBadge(lead.status);

            const convertBtn = (lead.status !== 'converted')
                ? `<button type="button" class="btn btn-sm btn-outline-success btn-convert" data-id="${lead.id}">Convert</button>`
                : `<span class="badge bg-success-subtle text-success">Converted</span>`;

            return `
                <tr>
                    <td class="fw-bold text-primary">${escapeHtml(lead.lead_code || '')}</td>
                    <td>
                        <div class="fw-bold">${escapeHtml(lead.name || '')}</div>
                        <div class="small text-muted d-flex align-items-center gap-2 mt-1">
                            <span>📞 ${escapeHtml(lead.mobile || '')}</span>
                            <a href="${waLink}" target="_blank" rel="noopener noreferrer" class="btn btn-xs btn-whatsapp px-2 py-0 rounded" title="Chat on WhatsApp">WA</a>
                            <a href="${telLink}" class="btn btn-xs btn-call px-2 py-0 rounded" title="Call">Call</a>
                        </div>
                    </td>
                    <td><span class="badge bg-light text-dark border">${escapeHtml(lead.source_name || 'Direct')}</span></td>
                    <td><span class="badge bg-primary-subtle text-primary border">${escapeHtml(lead.interested_in || 'General')}</span></td>
                    <td>${escapeHtml(lead.assigned_to_name || 'Unassigned')}</td>
                    <td>${statusBadge}</td>
                    <td class="small text-muted">${formatDate(lead.created_at)}</td>
                    <td class="text-end">
                        <div class="btn-group btn-group-sm">
                            ${convertBtn}
                            <button type="button" class="btn btn-outline-secondary btn-fu" data-id="${lead.id}" data-name="${escapeHtml(lead.name)}" title="Schedule Follow-up">Follow-up</button>
                            <button type="button" class="btn btn-outline-secondary btn-edit" data-id="${lead.id}" title="Edit Lead">Edit</button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    function getStatusBadge(status) {
        const map = {
            'new': '<span class="badge bg-primary">New</span>',
            'contacted': '<span class="badge bg-info text-white">Contacted</span>',
            'interested': '<span class="badge bg-warning text-dark">Interested</span>',
            'follow_up': '<span class="badge" style="background:#7c3aed;color:#fff;">Follow-up</span>',
            'converted': '<span class="badge bg-success">Converted</span>',
            'lost': '<span class="badge bg-danger">Lost</span>',
        };
        return map[status] || `<span class="badge bg-secondary">${status}</span>`;
    }

    function formatDate(dt) {
        if (!dt) return '-';
        const d = new Date(dt.replace(' ', 'T'));
        if (isNaN(d)) return dt;
        return d.toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
    }

    function escapeHtml(str) {
        return UI.escapeHtml(str);
    }

    // 6. View Switcher
    viewKanbanBtn.addEventListener('click', () => {
        currentView = 'kanban';
        viewKanbanBtn.classList.add('active');
        viewTableBtn.classList.remove('active');
        kanbanContainer.style.display = 'block';
        tableContainer.style.display = 'none';
        loadLeads();
    });

    viewTableBtn.addEventListener('click', () => {
        currentView = 'table';
        viewTableBtn.classList.add('active');
        viewKanbanBtn.classList.remove('active');
        tableContainer.style.display = 'block';
        kanbanContainer.style.display = 'none';
        loadLeads();
    });

    // 7. Filter Event Listeners
    let searchDebounce = null;
    filterSearch.addEventListener('input', () => {
        clearTimeout(searchDebounce);
        searchDebounce = setTimeout(loadLeads, 300);
    });

    filterStatus.addEventListener('change', loadLeads);
    filterSource.addEventListener('change', loadLeads);
    if (filterAssigned) filterAssigned.addEventListener('change', loadLeads);
    filterDateFrom.addEventListener('change', loadLeads);

    resetFiltersBtn.addEventListener('click', () => {
        filterSearch.value = '';
        filterStatus.value = '';
        filterSource.value = '';
        if (filterAssigned) filterAssigned.value = '';
        filterDateFrom.value = '';
        loadLeads();
    });

    // 8. Add Lead Modal Handling
    document.getElementById('openCreateLeadModalBtn')?.addEventListener('click', () => {
        document.getElementById('leadModalTitle').textContent = 'New Lead Registration';
        document.getElementById('leadId').value = '';
        document.getElementById('leadForm').reset();
        document.getElementById('leadWhatsappSame').checked = true;
        document.getElementById('referredByGroup').style.display = 'none';
        document.getElementById('lostReasonGroup').style.display = 'none';
        document.getElementById('typeService').checked = true;
        updateInterestDropdown();
        leadModal.show();
    });

    // Edit Lead
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-edit');
        if (!btn) return;
        const id = btn.dataset.id;
        try {
            const res = await api.get(`/api/leads/${id}`);
            const lead = res.data;
            if (!lead) return;

            document.getElementById('leadModalTitle').textContent = `Edit Lead (${lead.lead_code})`;
            document.getElementById('leadId').value = lead.id;
            document.getElementById('leadName').value = lead.name || '';
            document.getElementById('leadMobile').value = lead.mobile || '';
            document.getElementById('leadWhatsapp').value = lead.whatsapp_number || lead.mobile || '';
            document.getElementById('leadWhatsappSame').checked = (lead.whatsapp_number === lead.mobile);
            document.getElementById('leadEmail').value = lead.email || '';
            
            const sourceSelect = document.getElementById('leadSourceSelect');
            sourceSelect.value = lead.lead_source_id || '';
            
            const refGroup = document.getElementById('referredByGroup');
            if (lead.source_name && lead.source_name.toLowerCase() === 'referral') {
                refGroup.style.display = 'block';
                document.getElementById('leadReferredBy').value = lead.referred_by || '';
            } else {
                refGroup.style.display = 'none';
            }

            if (lead.interest_type === 'course') {
                document.getElementById('typeCourse').checked = true;
            } else {
                document.getElementById('typeService').checked = true;
            }
            updateInterestDropdown(lead.interested_in);

            const statusSelect = document.getElementById('leadStatusSelect');
            statusSelect.value = lead.status;
            const lostGroup = document.getElementById('lostReasonGroup');
            if (lead.status === 'lost') {
                lostGroup.style.display = 'block';
                document.getElementById('leadLostReason').value = lead.lost_reason || '';
            } else {
                lostGroup.style.display = 'none';
            }

            const assignedSelect = document.getElementById('leadAssignedTo');
            if (assignedSelect) assignedSelect.value = lead.assigned_to || '';

            document.getElementById('leadNotes').value = lead.notes || '';

            leadModal.show();
        } catch (err) {
            UI.toast(err.message || 'Failed to load lead details.', 'danger');
        }
    });

    // Save Lead Form Submit
    document.getElementById('leadForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const submitBtn = document.getElementById('saveLeadBtn');
        const id = document.getElementById('leadId').value;

        const interestSelect = document.getElementById('leadInterestedInSelect');
        const customInterest = document.getElementById('leadInterestedInCustom');
        const interestedInVal = (interestSelect.value === '__custom__') ? customInterest.value.trim() : interestSelect.value;

        const payload = {
            name: document.getElementById('leadName').value.trim(),
            mobile: document.getElementById('leadMobile').value.trim(),
            whatsapp_number: document.getElementById('leadWhatsapp').value.trim(),
            email: document.getElementById('leadEmail').value.trim(),
            lead_source_id: document.getElementById('leadSourceSelect').value,
            referred_by: document.getElementById('leadReferredBy').value.trim(),
            interest_type: document.getElementById('typeCourse').checked ? 'course' : 'service',
            interested_in: interestedInVal,
            status: document.getElementById('leadStatusSelect').value,
            lost_reason: document.getElementById('leadLostReason').value.trim(),
            notes: document.getElementById('leadNotes').value.trim(),
        };

        const assignedSelect = document.getElementById('leadAssignedTo');
        if (assignedSelect && assignedSelect.value) {
            payload.assigned_to = assignedSelect.value;
        }

        UI.buttonLoading(submitBtn, true);
        try {
            if (id) {
                await api.put(`/api/leads/${id}`, payload);
                UI.toast('Lead updated successfully.', 'success');
            } else {
                await api.post('/api/leads', payload);
                UI.toast('Lead registered successfully.', 'success');
            }
            leadModal.hide();
            loadLeads();
        } catch (err) {
            UI.toast(err.message || 'Failed to save lead.', 'danger');
        } finally {
            UI.buttonLoading(submitBtn, false);
        }
    });

    // 9. Lost Reason Modal Submit
    document.getElementById('lostReasonForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const id = document.getElementById('lostModalLeadId').value;
        const reason = document.getElementById('lostModalReason').value.trim();
        if (!reason) return;

        try {
            await api.post(`/api/leads/${id}/status`, { status: 'lost', lost_reason: reason });
            UI.toast('Lead marked as Lost.', 'success');
            lostReasonModal.hide();
            loadLeads();
        } catch (err) {
            UI.toast(err.message || 'Failed to mark lead as lost.', 'danger');
        }
    });

    // 10. Convert Lead Modal Handling
    async function openConvertModal(leadId) {
        try {
            const res = await api.get(`/api/leads/${leadId}`);
            const lead = res.data;
            if (!lead) return;

            document.getElementById('convertLeadId').value = lead.id;
            document.getElementById('convertLeadName').textContent = lead.name;
            document.getElementById('convertLeadCode').textContent = lead.lead_code;
            document.getElementById('convertLeadInterest').textContent = lead.interested_in;

            document.getElementById('chkConvertToClient').checked = (lead.interest_type !== 'course');
            document.getElementById('chkConvertToStudent').checked = (lead.interest_type === 'course');
            
            document.getElementById('convertClientOptions').style.display = document.getElementById('chkConvertToClient').checked ? 'block' : 'none';
            document.getElementById('convertStudentOptions').style.display = document.getElementById('chkConvertToStudent').checked ? 'block' : 'none';

            document.getElementById('convertCourseName').value = lead.interested_in || '';
            document.getElementById('convertPan').value = '';
            document.getElementById('convertGstin').value = '';

            convertModal.show();
        } catch (err) {
            UI.toast(err.message || 'Failed to load lead for conversion.', 'danger');
        }
    }

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-convert');
        if (!btn) return;
        openConvertModal(btn.dataset.id);
    });

    document.getElementById('chkConvertToClient')?.addEventListener('change', function () {
        document.getElementById('convertClientOptions').style.display = this.checked ? 'block' : 'none';
    });

    document.getElementById('chkConvertToStudent')?.addEventListener('change', function () {
        document.getElementById('convertStudentOptions').style.display = this.checked ? 'block' : 'none';
    });

    document.getElementById('convertForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const submitBtn = document.getElementById('confirmConvertBtn');
        const id = document.getElementById('convertLeadId').value;

        const toClient = document.getElementById('chkConvertToClient').checked;
        const toStudent = document.getElementById('chkConvertToStudent').checked;

        if (!toClient && !toStudent) {
            UI.toast('Please select at least one conversion target (Client or Student).', 'warning');
            return;
        }

        const payload = {
            convert_to_client: toClient,
            convert_to_student: toStudent,
            client_type: document.getElementById('convertClientType').value,
            pan_no: document.getElementById('convertPan').value.trim(),
            gst_no: document.getElementById('convertGstin').value.trim(),
            course_name: document.getElementById('convertCourseName').value.trim(),
            qualification: document.getElementById('convertQualification').value.trim(),
        };

        UI.buttonLoading(submitBtn, true);
        try {
            const res = await api.post(`/api/leads/${id}/convert`, payload);
            const data = res.data || {};
            let msg = 'Lead successfully converted!';
            if (data.client_code) msg += ` Client Code: ${data.client_code}.`;
            if (data.student_code) msg += ` Student Code: ${data.student_code}.`;
            UI.toast(msg, 'success');
            convertModal.hide();
            loadLeads();
        } catch (err) {
            UI.toast(err.message || 'Conversion failed.', 'danger');
        } finally {
            UI.buttonLoading(submitBtn, false);
        }
    });

    // 11. Schedule Follow-up for Lead
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.btn-fu');
        if (!btn) return;
        const id = btn.dataset.id;
        const name = btn.dataset.name || 'Lead';

        document.getElementById('fuTargetLeadId').value = id;
        document.getElementById('fuTargetName').value = name;
        
        // Default due date to tomorrow 11 AM
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        tomorrow.setHours(11, 0, 0, 0);
        const yyyy = tomorrow.getFullYear();
        const mm = String(tomorrow.getMonth() + 1).padStart(2, '0');
        const dd = String(tomorrow.getDate()).padStart(2, '0');
        document.getElementById('fuDueAt').value = `${yyyy}-${mm}-${dd}T11:00`;
        document.getElementById('fuNotes').value = '';

        leadFollowUpModal.show();
    });

    document.getElementById('leadFollowUpForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const submitBtn = document.getElementById('saveFollowUpBtn');
        const leadId = document.getElementById('fuTargetLeadId').value;
        const dueAt = document.getElementById('fuDueAt').value;
        const type = document.getElementById('fuType').value;
        const notes = document.getElementById('fuNotes').value.trim();

        UI.buttonLoading(submitBtn, true);
        try {
            await api.post(`/api/leads/${leadId}/followups`, {
                lead_id: leadId,
                due_at: dueAt,
                type: type,
                notes: notes,
            });
            UI.toast('Follow-up scheduled successfully.', 'success');
            leadFollowUpModal.hide();
        } catch (err) {
            UI.toast(err.message || 'Failed to schedule follow-up.', 'danger');
        } finally {
            UI.buttonLoading(submitBtn, false);
        }
    });

    // 12. CSV Import Handling
    document.getElementById('openImportModalBtn')?.addEventListener('click', () => {
        document.getElementById('importForm').reset();
        document.getElementById('importResultsAlert').style.display = 'none';
        importModal.show();
    });

    document.getElementById('importForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const submitBtn = document.getElementById('startImportBtn');
        const fileInput = document.getElementById('csvFileInput');
        const resultsAlert = document.getElementById('importResultsAlert');

        if (!fileInput.files || fileInput.files.length === 0) {
            UI.toast('Please choose a CSV file.', 'warning');
            return;
        }

        const formData = new FormData();
        formData.append('file', fileInput.files[0]);
        const defaultSource = document.getElementById('defaultSourceSelect').value;
        if (defaultSource) {
            formData.append('default_source_id', defaultSource);
        }

        UI.buttonLoading(submitBtn, true);
        resultsAlert.style.display = 'none';

        try {
            const res = await api.upload('/api/leads/import', formData);
            const summary = res.data || {};
            resultsAlert.className = 'alert alert-success small mt-3';
            resultsAlert.style.display = 'block';
            resultsAlert.innerHTML = `
                <strong>Import Finished!</strong><br>
                Total rows processed: ${summary.total_processed || 0}<br>
                ✅ Successfully imported: <strong>${summary.imported || 0}</strong><br>
                ⚠️ Duplicates skipped: <strong>${summary.duplicates || 0}</strong>
            `;
            if (summary.errors && summary.errors.length > 0) {
                resultsAlert.innerHTML += `<div class="mt-2 text-danger">Errors:<br>${summary.errors.slice(0, 5).map(e => escapeHtml(e)).join('<br>')}</div>`;
            }
            loadLeads();
        } catch (err) {
            resultsAlert.className = 'alert alert-danger small mt-3';
            resultsAlert.style.display = 'block';
            resultsAlert.textContent = err.message || 'CSV Import failed.';
        } finally {
            UI.buttonLoading(submitBtn, false);
        }
    });

    // 13. Admin Manage Sources Handling
    document.getElementById('openSourcesModalBtn')?.addEventListener('click', async () => {
        if (!sourcesModal) return;
        sourcesModal.show();
        loadSourcesTable();
    });

    async function loadSourcesTable() {
        const tbody = document.getElementById('sourcesTableBody');
        if (!tbody) return;

        try {
            const res = await api.get('/api/lead-sources');
            const sources = res.data || [];
            if (sources.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-2">No sources defined</td></tr>';
                return;
            }

            tbody.innerHTML = sources.map(s => {
                const isActive = Number(s.is_active) === 1;
                const statusBadge = isActive
                    ? '<span class="badge bg-success">Active</span>'
                    : '<span class="badge bg-secondary">Inactive</span>';
                const toggleBtn = isActive
                    ? `<button type="button" class="btn btn-xs btn-outline-danger btn-toggle-source" data-id="${s.id}" data-active="0">Deactivate</button>`
                    : `<button type="button" class="btn btn-xs btn-outline-success btn-toggle-source" data-id="${s.id}" data-active="1">Activate</button>`;

                return `
                    <tr>
                        <td class="fw-semibold">${escapeHtml(s.name)}</td>
                        <td>${statusBadge}</td>
                        <td class="text-end">${toggleBtn}</td>
                    </tr>
                `;
            }).join('');
        } catch (err) {
            tbody.innerHTML = '<tr><td colspan="3" class="text-danger py-2">Failed to load sources</td></tr>';
        }
    }

    document.getElementById('addSourceForm')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const input = document.getElementById('newSourceName');
        const name = input.value.trim();
        if (!name) return;

        try {
            await api.post('/api/lead-sources', { name: name, is_active: 1 });
            UI.toast('Lead source added.', 'success');
            input.value = '';
            loadSourcesTable();
            loadLookups();
        } catch (err) {
            UI.toast(err.message || 'Failed to add source.', 'danger');
        }
    });

    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.btn-toggle-source');
        if (!btn) return;
        const id = btn.dataset.id;
        const active = btn.dataset.active;
        try {
            await api.put(`/api/lead-sources/${id}`, { is_active: active });
            loadSourcesTable();
            loadLookups();
        } catch (err) {
            UI.toast(err.message || 'Failed to update source.', 'danger');
        }
    });

    // Initial sequence
    loadLookups();
    loadLeads();
});
