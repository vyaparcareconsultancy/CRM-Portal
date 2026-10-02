<div class="row">
    <div class="col-12">
        <!-- Page Header -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h4 class="fw-bold mb-1">Follow-ups & Outreach</h4>
                <p class="text-muted small mb-0">Track and manage scheduled calls, WhatsApp chats, visits, and meetings across leads and clients.</p>
            </div>
            <?php if (can('followup.manage')): ?>
            <div>
                <button type="button" class="btn btn-primary d-flex align-items-center gap-2" id="openMainScheduleModalBtn">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                    <span>Schedule Follow-up</span>
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- Filter Card & Tabs -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom pt-3 pb-0 px-3 px-md-4">
                <ul class="nav nav-tabs card-header-tabs" id="followUpTabs" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active fw-semibold d-flex align-items-center gap-2" data-tab="today" type="button">
                            <span>Today</span>
                            <span class="badge bg-primary rounded-pill" id="badgeCountToday">0</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link fw-semibold d-flex align-items-center gap-2" data-tab="overdue" type="button">
                            <span>Overdue</span>
                            <span class="badge bg-danger rounded-pill" id="badgeCountOverdue">0</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link fw-semibold d-flex align-items-center gap-2" data-tab="upcoming" type="button">
                            <span>Upcoming</span>
                            <span class="badge bg-secondary rounded-pill" id="badgeCountUpcoming">0</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link fw-semibold d-flex align-items-center gap-2" data-tab="done" type="button">
                            <span>Done</span>
                            <span class="badge bg-success rounded-pill" id="badgeCountDone">0</span>
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link fw-semibold d-flex align-items-center gap-2" data-tab="all" type="button">
                            <span>All</span>
                            <span class="badge bg-light text-dark border rounded-pill" id="badgeCountAll">0</span>
                        </button>
                    </li>
                </ul>
            </div>
            <div class="card-body p-3 p-md-4">
                <div class="row g-3">
                    <div class="col-md-6 col-lg-5">
                        <div class="input-group">
                            <span class="input-group-text bg-white border-end-0 text-muted">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                            </span>
                            <input type="text" class="form-control border-start-0 ps-0" id="filterSearch" placeholder="Search name, phone, code, remarks...">
                        </div>
                    </div>
                    <div class="col-md-4 col-lg-3">
                        <select class="form-select" id="filterType">
                            <option value="">All Interaction Modes</option>
                            <option value="call">Call</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="visit">Visit / Walk-in</option>
                            <option value="meeting">Meeting</option>
                            <option value="email">Email</option>
                        </select>
                    </div>
                    <div class="col-md-2 col-lg-2">
                        <button type="button" class="btn btn-light border w-100" id="resetFiltersBtn">Reset</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Follow-ups Table Card -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table align-middle table-hover mb-0" id="mainFollowUpsTable">
                        <thead class="table-light text-uppercase small text-muted">
                            <tr>
                                <th>Contact (Client / Lead)</th>
                                <th>Mode</th>
                                <th>Due Date & Time</th>
                                <th>Assigned To</th>
                                <th>Outcome & Remarks</th>
                                <th>Status</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="mainFollowUpsTableBody">
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                                    <div>Loading follow-ups...</div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <!-- Pagination Footer -->
            <div class="card-footer bg-white border-top py-3 d-flex flex-wrap align-items-center justify-content-between gap-2" id="tablePaginationFooter">
                <div class="small text-muted" id="paginationInfo">Showing 0 of 0 entries</div>
                <ul class="pagination pagination-sm mb-0" id="paginationControls"></ul>
            </div>
        </div>
    </div>
</div>

<!-- Schedule Follow-up Modal -->
<div class="modal fade" id="mainScheduleModal" tabindex="-1" aria-labelledby="mainScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="mainScheduleModalLabel">Schedule Follow-up</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="mainScheduleForm">
                <div class="modal-body p-4">
                    <!-- Entity Selector (Client vs Lead) -->
                    <div class="mb-3">
                        <label class="form-label small fw-semibold d-block">Select Entity Type *</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="sched_target_type" id="schedTargetLead" value="lead" checked>
                            <label class="btn btn-outline-secondary btn-sm" for="schedTargetLead">Lead</label>

                            <input type="radio" class="btn-check" name="sched_target_type" id="schedTargetClient" value="client">
                            <label class="btn btn-outline-secondary btn-sm" for="schedTargetClient">Client</label>
                        </div>
                    </div>

                    <!-- Lead Dropdown -->
                    <div class="mb-3" id="schedLeadGroup">
                        <label for="sched_lead_id" class="form-label small fw-semibold">Lead *</label>
                        <select class="form-select" id="sched_lead_id" name="lead_id">
                            <option value="">Select a lead...</option>
                        </select>
                        <div class="invalid-feedback" id="sched_lead_idError"></div>
                    </div>

                    <!-- Client Dropdown -->
                    <div class="mb-3" id="schedClientGroup" style="display: none;">
                        <label for="sched_client_id" class="form-label small fw-semibold">Client *</label>
                        <select class="form-select" id="sched_client_id" name="client_id">
                            <option value="">Select a client...</option>
                        </select>
                        <div class="invalid-feedback" id="sched_client_idError"></div>
                    </div>

                    <!-- Mode -->
                    <div class="mb-3">
                        <label for="sched_type" class="form-label small fw-semibold">Interaction Mode *</label>
                        <select class="form-select" id="sched_type" name="type" required>
                            <option value="call">Call</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="visit">Visit / Walk-in</option>
                            <option value="meeting">Meeting</option>
                            <option value="email">Email</option>
                        </select>
                    </div>

                    <!-- Due At -->
                    <div class="mb-3">
                        <label for="sched_due_at" class="form-label small fw-semibold">Date & Time *</label>
                        <input type="datetime-local" class="form-control" id="sched_due_at" name="due_at" required>
                        <div class="invalid-feedback" id="sched_due_atError"></div>
                    </div>

                    <!-- Notes -->
                    <div class="mb-3">
                        <label for="sched_notes" class="form-label small fw-semibold">Discussion Agenda / Notes</label>
                        <textarea class="form-control" id="sched_notes" name="notes" rows="3" placeholder="Enter agenda, purpose, or discussion points..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="mainSubmitScheduleBtn">Schedule</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Complete Follow-up Modal (Outcome & Next Follow-up auto-prompt) -->
<div class="modal fade" id="mainMarkDoneModal" tabindex="-1" aria-labelledby="mainMarkDoneModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="mainMarkDoneModalLabel">Log Follow-up Outcome</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="mainMarkDoneForm">
                <input type="hidden" id="mainMarkDoneId" value="">
                <div class="modal-body p-4">
                    <p class="text-muted small mb-3">Record the result of this conversation and optionally schedule the next step.</p>

                    <!-- Outcome Select -->
                    <div class="mb-3">
                        <label for="main_outcome_select" class="form-label small fw-semibold">Call / Interaction Outcome <span class="text-danger">*</span></label>
                        <select class="form-select" id="main_outcome_select" name="outcome" required>
                            <option value="">Select outcome...</option>
                            <option value="connected">Connected (Discussed)</option>
                            <option value="not_picked">Not Picked</option>
                            <option value="busy">Busy / Call Later</option>
                            <option value="switched_off">Switched Off / Unreachable</option>
                            <option value="call_back">Call Back Requested</option>
                        </select>
                    </div>

                    <!-- Remarks -->
                    <div class="mb-3">
                        <label for="main_remarks" class="form-label small fw-semibold">Discussion Remarks</label>
                        <textarea class="form-control" id="main_remarks" name="remarks" rows="2" placeholder="Key points discussed, client questions, or agreement..."></textarea>
                    </div>

                    <!-- Next Follow-up Auto-Prompt Checkbox -->
                    <div class="card p-3 bg-light border">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="chkAutoNextFollowUp">
                            <label class="form-check-label fw-bold small" for="chkAutoNextFollowUp">
                                Auto-schedule Next Follow-up?
                            </label>
                        </div>
                        <div id="nextFollowUpGroup" class="mt-2 ps-3" style="display: none;">
                            <div class="mb-2">
                                <label for="next_follow_up_at" class="form-label small fw-semibold">Next Date & Time</label>
                                <input type="datetime-local" class="form-control form-control-sm" id="next_follow_up_at" name="next_follow_up_at">
                            </div>
                            <div class="mb-2">
                                <label for="next_follow_up_type" class="form-label small fw-semibold">Next Mode</label>
                                <select class="form-select form-select-sm" id="next_follow_up_type" name="next_follow_up_type">
                                    <option value="call">Call</option>
                                    <option value="whatsapp">WhatsApp</option>
                                    <option value="visit">Visit</option>
                                    <option value="meeting">Meeting</option>
                                    <option value="email">Email</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="mainSubmitMarkDoneBtn">Complete Follow-up</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let currentTab = 'today';
    let currentPage = 1;
    const perPage = 15;
    let searchTimeout = null;

    const tbody = document.getElementById('mainFollowUpsTableBody');
    const searchInput = document.getElementById('filterSearch');
    const typeSelect = document.getElementById('filterType');

    // Tab Switching
    document.querySelectorAll('#followUpTabs [data-tab]').forEach(btn => {
        btn.addEventListener('click', () => {
            document.querySelectorAll('#followUpTabs [data-tab]').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            currentTab = btn.dataset.tab;
            currentPage = 1;
            loadFollowUps();
        });
    });

    // Filters
    searchInput.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentPage = 1;
            loadFollowUps();
        }, 300);
    });

    typeSelect.addEventListener('change', () => {
        currentPage = 1;
        loadFollowUps();
    });

    document.getElementById('resetFiltersBtn')?.addEventListener('click', () => {
        searchInput.value = '';
        typeSelect.value = '';
        currentPage = 1;
        loadFollowUps();
    });

    // Load Follow-ups from API
    async function loadFollowUps() {
        tbody.innerHTML = `<tr>
            <td colspan="7" class="text-center py-5 text-muted">
                <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                <div>Loading follow-ups...</div>
            </td>
        </tr>`;

        const queryParams = new URLSearchParams({
            tab: currentTab,
            search: searchInput.value.trim(),
            type: typeSelect.value,
            page: currentPage,
            per_page: perPage,
        });

        try {
            const res = await api.get(`/api/followups?${queryParams.toString()}`);
            if (!res || !res.data) {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger small">Failed to load follow-ups.</td></tr>`;
                return;
            }

            const items = res.data.items || [];
            const total = res.data.total || 0;
            const counts = res.data.counts || {};

            updateBadges(counts);
            renderTable(items);
            renderPagination(total);
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger small">${UI.escapeHtml(err.message || 'Error loading follow-ups.')}</td></tr>`;
        }
    }

    function updateBadges(counts) {
        document.getElementById('badgeCountToday').textContent = counts.today || 0;
        document.getElementById('badgeCountUpcoming').textContent = counts.upcoming || 0;
        document.getElementById('badgeCountOverdue').textContent = counts.overdue || 0;
        document.getElementById('badgeCountDone').textContent = counts.done || 0;
        document.getElementById('badgeCountAll').textContent = counts.all || 0;
    }

    function renderTable(items) {
        if (!items || items.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-5 text-muted">
                <div class="mb-2">
                    <svg width="32" height="32" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </div>
                <div class="fw-semibold">No follow-ups for this tab</div>
                <div class="small text-muted">Scheduled interactions matching this criteria will appear here.</div>
            </td></tr>`;
            return;
        }

        const now = new Date();
        let html = '';

        items.forEach(f => {
            const dueDate = new Date((f.due_at || '').replace(' ', 'T'));
            const isOverdue = f.status === 'pending' && dueDate < now;
            const formattedDate = (f.due_at || '').substring(0, 16);

            let typeBadge = '<span class="badge bg-secondary">Call</span>';
            if (f.type === 'call') typeBadge = '<span class="badge bg-primary-subtle text-primary border">Call</span>';
            else if (f.type === 'whatsapp') typeBadge = '<span class="badge bg-success-subtle text-success border">WhatsApp</span>';
            else if (f.type === 'visit') typeBadge = '<span class="badge bg-warning-subtle text-warning border">Visit</span>';
            else if (f.type === 'meeting') typeBadge = '<span class="badge bg-info-subtle text-info border">Meeting</span>';
            else if (f.type === 'email') typeBadge = '<span class="badge bg-secondary-subtle text-secondary border">Email</span>';

            let statusBadge = '<span class="badge bg-secondary">Unknown</span>';
            if (f.status === 'pending') {
                statusBadge = isOverdue
                    ? '<span class="badge bg-danger text-white">Overdue</span>'
                    : '<span class="badge bg-warning text-dark">Pending</span>';
            } else if (f.status === 'done') {
                statusBadge = '<span class="badge bg-success">Done</span>';
            } else if (f.status === 'missed') {
                statusBadge = '<span class="badge bg-danger">Missed</span>';
            }

            // Outcome badge
            let outcomeBadge = '';
            if (f.outcome) {
                const outcomeLabels = {
                    'connected': 'Connected',
                    'not_picked': 'Not Picked',
                    'busy': 'Busy',
                    'switched_off': 'Switched Off',
                    'call_back': 'Call Back',
                };
                outcomeBadge = `<span class="badge bg-light text-dark border me-1">${UI.escapeHtml(outcomeLabels[f.outcome] || f.outcome)}</span>`;
            }

            let notesHtml = UI.escapeHtml(f.remarks || f.notes || '-');

            const isLead = (f.entity_type === 'lead' || Boolean(f.lead_id));
            const contactName = UI.escapeHtml(f.contact_name || f.client_name || f.lead_name || 'Contact');
            const contactCode = UI.escapeHtml(f.contact_code || f.client_code || f.lead_code || '');
            const phone = (f.contact_mobile || f.client_mobile || f.lead_mobile || '').replace(/[^0-9]/g, '');
            const whatsapp = (f.contact_whatsapp || phone).replace(/[^0-9]/g, '');

            const waLink = `https://wa.me/91${whatsapp}?text=${encodeURIComponent('Hello ' + contactName + ', regarding your CRM follow-up:')}`;
            const telLink = `tel:${phone}`;

            const entityBadge = isLead
                ? '<span class="badge bg-purple-subtle text-purple border" style="background:#ede9fe;color:#7c3aed;">Lead</span>'
                : '<span class="badge bg-primary-subtle text-primary border">Client</span>';

            const linkTarget = isLead ? '/leads' : `/clients/${f.client_id}`;

            html += `<tr>
                <td>
                    <div class="d-flex align-items-center gap-2">
                        ${entityBadge}
                        <a href="${linkTarget}" class="fw-bold text-decoration-none text-dark">
                            ${contactName}
                        </a>
                        <small class="text-muted font-monospace">${contactCode}</small>
                    </div>
                    ${phone ? `
                    <div class="d-flex align-items-center gap-2 mt-1">
                        <small class="text-muted">📞 ${phone}</small>
                        <a href="${waLink}" target="_blank" rel="noopener noreferrer" class="btn btn-xs btn-whatsapp px-2 py-0 rounded" title="Click to WhatsApp">WA</a>
                        <a href="${telLink}" class="btn btn-xs btn-call px-2 py-0 rounded" title="Click to Call">Call</a>
                    </div>
                    ` : ''}
                </td>
                <td>${typeBadge}</td>
                <td>
                    <div class="fw-medium small">${formattedDate}</div>
                    ${isOverdue ? '<span class="badge bg-danger text-white" style="font-size: 0.65rem;">Past Due</span>' : ''}
                </td>
                <td><small class="text-muted">${UI.escapeHtml(f.user_name || 'Unassigned')}</small></td>
                <td>
                    ${outcomeBadge}
                    <small class="text-muted d-block text-break" style="max-width: 250px;">${notesHtml}</small>
                </td>
                <td>${statusBadge}</td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm">
                        ${f.status === 'pending' && <?= can('followup.manage') ? 'true' : 'false' ?> ? `
                        <button type="button" class="btn btn-outline-success main-mark-done-btn" data-id="${f.id}" title="Complete Follow-up">
                            &#10003; Done
                        </button>` : ''}
                        <?php if (can('followup.manage')): ?>
                        <button type="button" class="btn btn-outline-danger main-delete-btn" data-id="${f.id}" title="Delete Follow-up">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        </button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>`;
        });

        tbody.innerHTML = html;
    }

    function renderPagination(total) {
        const totalPages = Math.ceil(total / perPage);
        const info = document.getElementById('paginationInfo');
        const start = total === 0 ? 0 : (currentPage - 1) * perPage + 1;
        const end = Math.min(currentPage * perPage, total);
        info.textContent = `Showing ${start} to ${end} of ${total} follow-ups`;

        const controls = document.getElementById('paginationControls');
        controls.innerHTML = '';
        if (totalPages <= 1) return;

        let html = '';
        html += `<li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
            <button class="page-link" data-page="${currentPage - 1}">&laquo;</button>
        </li>`;

        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - 2 && i <= currentPage + 2)) {
                html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                    <button class="page-link" data-page="${i}">${i}</button>
                </li>`;
            } else if (i === currentPage - 3 || i === currentPage + 3) {
                html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            }
        }

        html += `<li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
            <button class="page-link" data-page="${currentPage + 1}">&raquo;</button>
        </li>`;

        controls.innerHTML = html;
        controls.querySelectorAll('[data-page]').forEach(b => {
            b.addEventListener('click', () => {
                const target = parseInt(b.dataset.page, 10);
                if (target >= 1 && target <= totalPages) {
                    currentPage = target;
                    loadFollowUps();
                }
            });
        });
    }

    // Schedule Follow-up Modal
    let clientsLoaded = false;
    let leadsLoaded = false;

    async function loadClientOptions() {
        if (clientsLoaded) return;
        const select = document.getElementById('sched_client_id');
        try {
            const res = await api.get('/api/clients?per_page=100');
            const clients = res?.data?.items || res?.data || [];
            select.innerHTML = '<option value="">Select a client...</option>';
            clients.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = `${c.name} (${c.client_code})`;
                select.appendChild(opt);
            });
            clientsLoaded = true;
        } catch (e) {
            console.warn('Failed to load clients list', e);
        }
    }

    async function loadLeadOptions() {
        if (leadsLoaded) return;
        const select = document.getElementById('sched_lead_id');
        try {
            const res = await api.get('/api/leads?per_page=100');
            const leads = res?.data?.items || [];
            select.innerHTML = '<option value="">Select a lead...</option>';
            leads.forEach(l => {
                const opt = document.createElement('option');
                opt.value = l.id;
                opt.textContent = `${l.name} (${l.lead_code})`;
                select.appendChild(opt);
            });
            leadsLoaded = true;
        } catch (e) {
            console.warn('Failed to load leads list', e);
        }
    }

    document.getElementById('schedTargetLead')?.addEventListener('change', () => {
        document.getElementById('schedLeadGroup').style.display = 'block';
        document.getElementById('schedClientGroup').style.display = 'none';
        document.getElementById('sched_client_id').value = '';
        loadLeadOptions();
    });

    document.getElementById('schedTargetClient')?.addEventListener('change', () => {
        document.getElementById('schedClientGroup').style.display = 'block';
        document.getElementById('schedLeadGroup').style.display = 'none';
        document.getElementById('sched_lead_id').value = '';
        loadClientOptions();
    });

    const openSchedBtn = document.getElementById('openMainScheduleModalBtn');
    if (openSchedBtn) {
        openSchedBtn.addEventListener('click', async () => {
            const form = document.getElementById('mainScheduleForm');
            form.reset();
            UI.clearErrors(form);

            // Default due_at to tomorrow at 10:00 AM
            const tmr = new Date();
            tmr.setDate(tmr.getDate() + 1);
            tmr.setHours(10, 0, 0, 0);
            const isoStr = new Date(tmr.getTime() - tmr.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            document.getElementById('sched_due_at').value = isoStr;

            document.getElementById('schedTargetLead').checked = true;
            document.getElementById('schedLeadGroup').style.display = 'block';
            document.getElementById('schedClientGroup').style.display = 'none';

            await loadLeadOptions();

            const modal = new bootstrap.Modal(document.getElementById('mainScheduleModal'));
            modal.show();
        });
    }

    document.getElementById('mainScheduleForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = document.getElementById('mainScheduleForm');
        const submitBtn = document.getElementById('mainSubmitScheduleBtn');
        UI.clearErrors(form);
        UI.buttonLoading(submitBtn, true, 'Scheduling...');

        const targetType = document.querySelector('input[name="sched_target_type"]:checked').value;
        const payload = {
            due_at: document.getElementById('sched_due_at').value,
            type: document.getElementById('sched_type').value,
            notes: document.getElementById('sched_notes').value.trim(),
        };

        if (targetType === 'lead') {
            payload.lead_id = document.getElementById('sched_lead_id').value;
            if (!payload.lead_id) {
                UI.toast('Please select a lead.', 'warning');
                UI.buttonLoading(submitBtn, false);
                return;
            }
        } else {
            payload.client_id = document.getElementById('sched_client_id').value;
            if (!payload.client_id) {
                UI.toast('Please select a client.', 'warning');
                UI.buttonLoading(submitBtn, false);
                return;
            }
        }

        try {
            await api.post('/api/followups', payload);
            UI.toast('Follow-up scheduled successfully!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('mainScheduleModal')).hide();
            loadFollowUps();
        } catch (err) {
            UI.toast(err.message || 'Failed to schedule follow-up.', 'danger');
        } finally {
            UI.buttonLoading(submitBtn, false);
        }
    });

    // Mark Done Action (Outcome & Remarks)
    $('#mainFollowUpsTableBody').on('click', '.main-mark-done-btn', function () {
        const id = this.dataset.id;
        document.getElementById('mainMarkDoneId').value = id;
        document.getElementById('mainMarkDoneForm').reset();
        document.getElementById('nextFollowUpGroup').style.display = 'none';

        // Default next follow-up to 2 days later
        const d = new Date();
        d.setDate(d.getDate() + 2);
        d.setHours(11, 0, 0, 0);
        document.getElementById('next_follow_up_at').value = d.toISOString().slice(0, 16);

        const modal = new bootstrap.Modal(document.getElementById('mainMarkDoneModal'));
        modal.show();
    });

    document.getElementById('chkAutoNextFollowUp')?.addEventListener('change', function () {
        document.getElementById('nextFollowUpGroup').style.display = this.checked ? 'block' : 'none';
    });

    document.getElementById('mainMarkDoneForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('mainMarkDoneId').value;
        const outcome = document.getElementById('main_outcome_select').value;
        const remarks = document.getElementById('main_remarks').value.trim();
        const submitBtn = document.getElementById('mainSubmitMarkDoneBtn');

        const payload = {
            status: 'done',
            outcome: outcome,
            remarks: remarks,
        };

        if (document.getElementById('chkAutoNextFollowUp').checked) {
            payload.next_follow_up_at = document.getElementById('next_follow_up_at').value;
            payload.next_follow_up_type = document.getElementById('next_follow_up_type').value;
        }

        UI.buttonLoading(submitBtn, true, 'Saving...');
        try {
            await api.put(`/api/followups/${id}`, payload);
            UI.toast('Follow-up marked as completed!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('mainMarkDoneModal')).hide();
            loadFollowUps();
        } catch (err) {
            UI.toast(err.message || 'Failed to complete follow-up.', 'danger');
        } finally {
            UI.buttonLoading(submitBtn, false);
        }
    });

    // Delete Follow-up Action
    $('#mainFollowUpsTableBody').on('click', '.main-delete-btn', async function () {
        const id = this.dataset.id;
        if (!confirm('Are you sure you want to delete this follow-up?')) return;

        try {
            await api.delete(`/api/followups/${id}`);
            UI.toast('Follow-up deleted successfully.', 'success');
            loadFollowUps();
        } catch (err) {
            UI.toast(err.message || 'Failed to delete follow-up.', 'danger');
        }
    });

    // Initial Load
    loadFollowUps();
});
</script>
