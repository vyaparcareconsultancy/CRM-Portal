<?php
/**
 * Reminders & Deadlines View
 * Tabs: Today, Upcoming, Overdue, Done
 * Managed Reminder Rules Modal for Admin
 */
?>
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h1 class="h3 mb-1 fw-bold text-dark">Reminders & Compliance Deadlines</h1>
            <p class="text-muted small mb-0">Automated statutory deadlines, fee schedules, renewals and relationship reminders.</p>
        </div>
        <div class="d-flex gap-2">
            <?php if (!empty($canManage)): ?>
            <button class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1" id="triggerCronBtn" title="Run reminder generator and notification dispatcher now">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>Run Generator Now</span>
            </button>
            <button class="btn btn-primary btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#rulesModal">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span>Reminder Rules</span>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alert Container -->
    <div id="alertPlaceholder"></div>

    <!-- Navigation Tabs -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-bottom-0 pb-0">
            <ul class="nav nav-tabs card-header-tabs" id="reminderTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-semibold d-flex align-items-center gap-2" id="tab-today" data-tab="today" type="button" role="tab">
                        <span>Today</span>
                        <span class="badge bg-primary rounded-pill" id="badge-today">0</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold d-flex align-items-center gap-2" id="tab-upcoming" data-tab="upcoming" type="button" role="tab">
                        <span>Upcoming</span>
                        <span class="badge bg-info text-dark rounded-pill" id="badge-upcoming">0</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold d-flex align-items-center gap-2" id="tab-overdue" data-tab="overdue" type="button" role="tab">
                        <span>Overdue</span>
                        <span class="badge bg-danger rounded-pill" id="badge-overdue">0</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold d-flex align-items-center gap-2" id="tab-done" data-tab="done" type="button" role="tab">
                        <span>Done</span>
                        <span class="badge bg-success rounded-pill" id="badge-done">0</span>
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="remindersTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 140px;">Status</th>
                            <th>Title & Description</th>
                            <th>Entity / Client</th>
                            <th>Due Date</th>
                            <th>Assigned Staff</th>
                            <th>Channels</th>
                            <th class="text-end" style="width: 140px;">Action</th>
                        </tr>
                    </thead>
                    <tbody id="remindersTableBody">
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                                Loading reminders...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Mark Done with Note -->
<div class="modal fade" id="markDoneModal" tabindex="-1" aria-labelledby="markDoneModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="markDoneModalLabel">Complete Reminder</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="markDoneForm">
                <input type="hidden" id="markDoneReminderId" value="">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">Reminder</label>
                        <div class="p-2 bg-light rounded border fw-medium small" id="markDoneTitleDisplay"></div>
                    </div>
                    <div class="mb-3">
                        <label for="markDoneNote" class="form-label fw-semibold small">Completion Note / Remarks</label>
                        <textarea class="form-control form-control-sm" id="markDoneNote" rows="3" placeholder="e.g. Filed return ARN #123456, or payment received via UPI" required></textarea>
                        <div class="form-text">Provide reference, ack number, or action taken.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm d-flex align-items-center gap-1" id="markDoneSubmitBtn">
                        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                        <span>Mark as Done</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Reminder Rules Master (Admin) -->
<div class="modal fade" id="rulesModal" tabindex="-1" aria-labelledby="rulesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title fw-bold" id="rulesModalLabel">Reminder Rules Management</h5>
                    <p class="text-muted small mb-0">Dynamic deadline rules (due dates & extensions change, zero hardcoded statutory dates).</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Add / Edit Rule Form Accordion -->
                <div class="accordion mb-4" id="ruleFormAccordion">
                    <div class="accordion-item border shadow-sm">
                        <h2 class="accordion-header" id="headingRuleForm">
                            <button class="accordion-button collapsed fw-semibold text-primary py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRuleForm" aria-expanded="false" aria-controls="collapseRuleForm" id="ruleFormToggleBtn">
                                + Create New Reminder Rule
                            </button>
                        </h2>
                        <div id="collapseRuleForm" class="accordion-collapse collapse" aria-labelledby="headingRuleForm" data-bs-parent="#ruleFormAccordion">
                            <div class="accordion-body bg-light">
                                <form id="reminderRuleForm">
                                    <input type="hidden" id="ruleFormId" value="">
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label small fw-semibold">Rule Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm" id="ruleName" placeholder="e.g. GSTR-1, GSTR-3B, ITR, Birthday" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-semibold">Applies To <span class="text-danger">*</span></label>
                                            <select class="form-select form-select-sm" id="ruleAppliesTo" required>
                                                <option value="client_service">Client Service Type</option>
                                                <option value="student_fee">Student Tuition Fee</option>
                                                <option value="all_clients">All Clients (Renewal / Birthday)</option>
                                            </select>
                                        </div>
                                        <div class="col-md-3" id="serviceSelectGroup">
                                            <label class="form-label small fw-semibold">Specific Service (Optional)</label>
                                            <select class="form-select form-select-sm" id="ruleServiceId">
                                                <option value="">-- All Active Services --</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-semibold">Remind Days Before</label>
                                            <input type="number" class="form-control form-control-sm" id="ruleRemindDays" value="3" min="0" max="90" required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-semibold">Monthly Due Day (1-31)</label>
                                            <input type="number" class="form-control form-control-sm" id="ruleDueDay" placeholder="e.g. 11, 20, 15" min="1" max="31">
                                            <div class="form-text">For monthly/recurring returns.</div>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label small fw-semibold">Specific Due Date (Period/Extension)</label>
                                            <input type="date" class="form-control form-control-sm" id="ruleDueDate">
                                            <div class="form-text">For one-time or extended dates (ITR, ROC).</div>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label small fw-semibold">Dispatch Channels</label>
                                            <div class="d-flex flex-wrap gap-3 pt-1">
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="channelInApp" value="in_app" checked>
                                                    <label class="form-check-label small" for="channelInApp">In-App Bell</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="channelEmail" value="email" checked>
                                                    <label class="form-check-label small" for="channelEmail">Email</label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="channelWhatsApp" value="whatsapp">
                                                    <label class="form-check-label small text-muted" for="channelWhatsApp">WhatsApp <span class="badge bg-secondary" style="font-size: 9px;">Phase 9</span></label>
                                                </div>
                                                <div class="form-check form-check-inline">
                                                    <input class="form-check-input" type="checkbox" id="channelSms" value="sms">
                                                    <label class="form-check-label small text-muted" for="channelSms">SMS <span class="badge bg-secondary" style="font-size: 9px;">Phase 9</span></label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-2">
                                            <label class="form-label small fw-semibold">Status</label>
                                            <div class="form-check form-switch pt-1">
                                                <input class="form-check-input" type="checkbox" id="ruleIsActive" checked>
                                                <label class="form-check-label small" for="ruleIsActive">Active</label>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label small fw-semibold">Description / Statutory Notes</label>
                                            <input type="text" class="form-control form-control-sm" id="ruleDescription" placeholder="e.g. Regular monthly GSTR-3B return for tax payment">
                                        </div>
                                        <div class="col-12 text-end">
                                            <button type="button" class="btn btn-light btn-sm me-2" id="cancelRuleEditBtn">Cancel</button>
                                            <button type="submit" class="btn btn-primary btn-sm" id="saveRuleBtn">Save Rule</button>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rules List Table -->
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0" id="rulesListTable">
                        <thead class="table-light">
                            <tr>
                                <th>Rule Name</th>
                                <th>Applies To</th>
                                <th>Due Day / Date</th>
                                <th>Remind Before</th>
                                <th>Channels</th>
                                <th>Active</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="rulesListBody">
                            <tr><td colspan="7" class="text-center py-3 text-muted">Loading rules...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentTab = 'today';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // Load initial reminders
    loadReminders('today');

    // Tab switcher
    document.querySelectorAll('#reminderTabs button[data-tab]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            document.querySelectorAll('#reminderTabs button').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentTab = this.getAttribute('data-tab');
            loadReminders(currentTab);
        });
    });

    // Fetch and render reminders
    async function loadReminders(tab) {
        const tbody = document.getElementById('remindersTableBody');
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted"><div class="spinner-border spinner-border-sm text-primary me-2"></div>Loading...</td></tr>`;

        try {
            const res = await fetch(`/api/reminders?tab=${tab}`);
            const data = await res.json();

            if (data.status === 'success') {
                updateBadges(data.data.counts || {});
                renderReminders(data.data.reminders || []);
            } else {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger">${escapeHtml(data.message || 'Error')}</td></tr>`;
            }
        } catch (err) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-danger">Failed to load reminders.</td></tr>`;
        }
    }

    function updateBadges(counts) {
        document.getElementById('badge-today').textContent = counts.today ?? 0;
        document.getElementById('badge-upcoming').textContent = counts.upcoming ?? 0;
        document.getElementById('badge-overdue').textContent = counts.overdue ?? 0;
        document.getElementById('badge-done').textContent = counts.done ?? 0;
    }

    function renderReminders(items) {
        const tbody = document.getElementById('remindersTableBody');
        if (!items || items.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-4 text-muted">No reminders found in this view.</td></tr>`;
            return;
        }

        tbody.innerHTML = items.map(rem => {
            const statusBadge = getStatusBadge(rem);
            const channelsBadge = renderChannels(rem.channels_sent);

            let actionHtml = '';
            if (rem.status === 'done') {
                actionHtml = `
                    <div class="text-end">
                        <span class="badge bg-success mb-1">Completed</span>
                        <div class="small text-muted" style="font-size: 11px;">By: ${escapeHtml(rem.done_by_name || 'Staff')}</div>
                        ${rem.done_note ? `<div class="small text-dark fst-italic" style="font-size: 11px;" title="${escapeHtml(rem.done_note)}">"${escapeHtml(rem.done_note.substring(0, 35))}${rem.done_note.length > 35 ? '...' : ''}"</div>` : ''}
                    </div>
                `;
            } else {
                actionHtml = `
                    <div class="text-end">
                        <button class="btn btn-outline-success btn-sm mark-done-btn d-inline-flex align-items-center gap-1"
                                data-id="${rem.id}"
                                data-title="${escapeHtml(rem.title)}">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M5 13l4 4L19 7"/></svg>
                            <span>Mark Done</span>
                        </button>
                    </div>
                `;
            }

            return `
                <tr>
                    <td>${statusBadge}</td>
                    <td>
                        <div class="fw-semibold text-dark">${escapeHtml(rem.title)}</div>
                        <div class="text-muted small">${escapeHtml(rem.description || '')}</div>
                        ${rem.period ? `<span class="badge bg-light text-secondary border mt-1" style="font-size: 10px;">Period: ${escapeHtml(rem.period)}</span>` : ''}
                    </td>
                    <td>
                        <div class="fw-medium">${escapeHtml(rem.entity_name || 'N/A')}</div>
                        <div class="text-muted small">${escapeHtml(rem.entity_code || '')}</div>
                        ${rem.invoice_no ? `<div class="small text-primary">Inv: ${escapeHtml(rem.invoice_no)} (Due: ₹${rem.invoice_due_amount || 0})</div>` : ''}
                    </td>
                    <td>
                        <div class="fw-semibold ${isOverdue(rem.due_date, rem.status) ? 'text-danger' : 'text-dark'}">${rem.due_date}</div>
                        <div class="text-muted small" style="font-size: 11px;">Alert: ${rem.remind_date}</div>
                    </td>
                    <td>
                        <span class="small text-secondary">${escapeHtml(rem.assigned_user_name || 'System Admin')}</span>
                    </td>
                    <td>${channelsBadge}</td>
                    <td>${actionHtml}</td>
                </tr>
            `;
        }).join('');

        // Wire up Mark Done buttons
        tbody.querySelectorAll('.mark-done-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                const title = this.getAttribute('data-title');
                openMarkDoneModal(id, title);
            });
        });
    }

    function isOverdue(dueDate, status) {
        if (status === 'done') return false;
        const today = new Date().toISOString().split('T')[0];
        return dueDate < today;
    }

    function getStatusBadge(rem) {
        if (rem.status === 'done') {
            return `<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Done</span>`;
        }
        if (isOverdue(rem.due_date, rem.status)) {
            return `<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">Overdue</span>`;
        }
        if (rem.status === 'notified') {
            return `<span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1">Alert Sent</span>`;
        }
        return `<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle px-2 py-1">Scheduled</span>`;
    }

    function renderChannels(sentLog) {
        if (!sentLog) return `<span class="badge bg-light text-muted border">In-App</span>`;
        let parsed = sentLog;
        if (typeof sentLog === 'string') {
            try { parsed = JSON.parse(sentLog); } catch(e) { parsed = {}; }
        }
        const keys = Object.keys(parsed);
        if (keys.length === 0) return `<span class="badge bg-light text-muted border">In-App</span>`;

        return keys.map(k => {
            if (k === 'in_app') return `<span class="badge bg-info-subtle text-info-emphasis border border-info-subtle me-1" title="In-App Notification">Bell</span>`;
            if (k === 'email') return `<span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1" title="Email Dispatched">Email</span>`;
            return `<span class="badge bg-secondary me-1">${escapeHtml(k)}</span>`;
        }).join('');
    }

    // Modal: Mark Done
    const markDoneModal = new bootstrap.Modal(document.getElementById('markDoneModal'));
    function openMarkDoneModal(id, title) {
        document.getElementById('markDoneReminderId').value = id;
        document.getElementById('markDoneTitleDisplay').textContent = title;
        document.getElementById('markDoneNote').value = '';
        markDoneModal.show();
    }

    document.getElementById('markDoneForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const id = document.getElementById('markDoneReminderId').value;
        const note = document.getElementById('markDoneNote').value.trim();
        const submitBtn = document.getElementById('markDoneSubmitBtn');

        submitBtn.disabled = true;
        try {
            const res = await fetch(`/api/reminders/${id}/done`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ note }),
            });
            const data = await res.json();
            if (data.status === 'success') {
                markDoneModal.hide();
                showAlert('Reminder marked as completed!', 'success');
                loadReminders(currentTab);
            } else {
                alert(data.message || 'Error completing reminder');
            }
        } catch (err) {
            alert('Failed to submit: ' + err.message);
        } finally {
            submitBtn.disabled = false;
        }
    });

    // Run Generator On-Demand
    const triggerCronBtn = document.getElementById('triggerCronBtn');
    if (triggerCronBtn) {
        triggerCronBtn.addEventListener('click', async function() {
            if (!confirm('Run reminder generator and notification dispatcher now?')) return;
            triggerCronBtn.disabled = true;
            try {
                const res = await fetch('/api/reminders/generate-now', {
                    method: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrfToken },
                });
                const data = await res.json();
                if (data.status === 'success') {
                    showAlert(data.message, 'success');
                    loadReminders(currentTab);
                } else {
                    showAlert(data.message || 'Generator error', 'danger');
                }
            } catch (err) {
                showAlert('Error running generator: ' + err.message, 'danger');
            } finally {
                triggerCronBtn.disabled = false;
            }
        });
    }

    // =========================================================================
    // REMINDER RULES MODAL
    // =========================================================================
    const rulesModalEl = document.getElementById('rulesModal');
    if (rulesModalEl) {
        rulesModalEl.addEventListener('show.bs.modal', function() {
            loadRules();
            loadServicesDropdown();
        });
    }

    async function loadServicesDropdown() {
        const select = document.getElementById('ruleServiceId');
        if (select.children.length > 1) return; // already loaded
        try {
            const res = await fetch('/api/services');
            const data = await res.json();
            if (data.status === 'success' && Array.isArray(data.data)) {
                data.data.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.id;
                    opt.textContent = `${s.name} (${s.code || ''})`;
                    select.appendChild(opt);
                });
            }
        } catch(e) {}
    }

    async function loadRules() {
        const tbody = document.getElementById('rulesListBody');
        tbody.innerHTML = `<tr><td colspan="7" class="text-center py-3 text-muted">Loading rules...</td></tr>`;
        try {
            const res = await fetch('/api/reminder-rules');
            const data = await res.json();
            if (data.status === 'success') {
                renderRules(data.data || []);
            } else {
                tbody.innerHTML = `<tr><td colspan="7" class="text-center py-3 text-danger">${escapeHtml(data.message)}</td></tr>`;
            }
        } catch (e) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-3 text-danger">Failed to load rules.</td></tr>`;
        }
    }

    function renderRules(rules) {
        const tbody = document.getElementById('rulesListBody');
        if (!rules || rules.length === 0) {
            tbody.innerHTML = `<tr><td colspan="7" class="text-center py-3 text-muted">No reminder rules defined.</td></tr>`;
            return;
        }

        tbody.innerHTML = rules.map(r => {
            const dueStr = r.due_day ? `Day ${r.due_day} of month` : (r.due_date || 'Period due date');
            const channelsStr = (r.channels_array || []).join(', ');

            return `
                <tr>
                    <td class="fw-semibold">${escapeHtml(r.name)}</td>
                    <td><span class="badge bg-light text-dark border">${escapeHtml(r.applies_to)}</span></td>
                    <td>${escapeHtml(dueStr)}</td>
                    <td>${r.remind_days_before} days before</td>
                    <td class="small text-muted">${escapeHtml(channelsStr)}</td>
                    <td>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input toggle-rule-btn" type="checkbox" data-id="${r.id}" ${r.is_active == 1 ? 'checked' : ''}>
                        </div>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-link btn-sm text-primary p-0 me-2 edit-rule-btn" data-rule='${JSON.stringify(r).replace(/'/g, "&#39;")}'>Edit</button>
                        <button class="btn btn-link btn-sm text-danger p-0 delete-rule-btn" data-id="${r.id}">Delete</button>
                    </td>
                </tr>
            `;
        }).join('');

        // Wire up toggle switches
        tbody.querySelectorAll('.toggle-rule-btn').forEach(sw => {
            sw.addEventListener('change', async function() {
                const id = this.getAttribute('data-id');
                const is_active = this.checked ? 1 : 0;
                await fetch(`/api/reminder-rules/${id}/toggle`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                    body: JSON.stringify({ is_active })
                });
            });
        });

        // Wire up delete buttons
        tbody.querySelectorAll('.delete-rule-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                if (!confirm('Are you sure you want to delete this reminder rule?')) return;
                const id = this.getAttribute('data-id');
                const res = await fetch(`/api/reminder-rules/${id}`, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-TOKEN': csrfToken }
                });
                const d = await res.json();
                if (d.status === 'success') loadRules();
                else alert(d.message || 'Error deleting rule');
            });
        });

        // Wire up edit buttons
        tbody.querySelectorAll('.edit-rule-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const rule = JSON.parse(this.getAttribute('data-rule'));
                document.getElementById('ruleFormId').value = rule.id;
                document.getElementById('ruleName').value = rule.name;
                document.getElementById('ruleAppliesTo').value = rule.applies_to;
                document.getElementById('ruleServiceId').value = rule.service_id || '';
                document.getElementById('ruleRemindDays').value = rule.remind_days_before;
                document.getElementById('ruleDueDay').value = rule.due_day || '';
                document.getElementById('ruleDueDate').value = rule.due_date || '';
                document.getElementById('ruleDescription').value = rule.description || '';
                document.getElementById('ruleIsActive').checked = rule.is_active == 1;

                const channels = rule.channels_array || [];
                document.getElementById('channelInApp').checked = channels.includes('in_app');
                document.getElementById('channelEmail').checked = channels.includes('email');
                document.getElementById('channelWhatsApp').checked = channels.includes('whatsapp');
                document.getElementById('channelSms').checked = channels.includes('sms');

                document.getElementById('ruleFormToggleBtn').textContent = `Edit Rule: ${rule.name}`;
                const collapseEl = new bootstrap.Collapse(document.getElementById('collapseRuleForm'), { toggle: false });
                collapseEl.show();
            });
        });
    }

    // Cancel rule edit
    document.getElementById('cancelRuleEditBtn')?.addEventListener('click', function() {
        resetRuleForm();
        const collapseEl = bootstrap.Collapse.getInstance(document.getElementById('collapseRuleForm'));
        if (collapseEl) collapseEl.hide();
    });

    function resetRuleForm() {
        document.getElementById('ruleFormId').value = '';
        document.getElementById('reminderRuleForm').reset();
        document.getElementById('channelInApp').checked = true;
        document.getElementById('channelEmail').checked = true;
        document.getElementById('ruleIsActive').checked = true;
        document.getElementById('ruleFormToggleBtn').textContent = '+ Create New Reminder Rule';
    }

    // Save Rule (Create or Update)
    document.getElementById('reminderRuleForm')?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const id = document.getElementById('ruleFormId').value;
        const channels = [];
        if (document.getElementById('channelInApp').checked) channels.push('in_app');
        if (document.getElementById('channelEmail').checked) channels.push('email');
        if (document.getElementById('channelWhatsApp').checked) channels.push('whatsapp');
        if (document.getElementById('channelSms').checked) channels.push('sms');

        const payload = {
            name: document.getElementById('ruleName').value.trim(),
            applies_to: document.getElementById('ruleAppliesTo').value,
            service_id: document.getElementById('ruleServiceId').value || null,
            remind_days_before: parseInt(document.getElementById('ruleRemindDays').value, 10),
            due_day: document.getElementById('ruleDueDay').value ? parseInt(document.getElementById('ruleDueDay').value, 10) : null,
            due_date: document.getElementById('ruleDueDate').value || null,
            channels: channels,
            is_active: document.getElementById('ruleIsActive').checked ? 1 : 0,
            description: document.getElementById('ruleDescription').value.trim(),
        };

        const isEdit = !!id;
        const url = isEdit ? `/api/reminder-rules/${id}` : '/api/reminder-rules';
        const method = isEdit ? 'PUT' : 'POST';

        try {
            const res = await fetch(url, {
                method: method,
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
                body: JSON.stringify(payload),
            });
            const data = await res.json();
            if (data.status === 'success') {
                resetRuleForm();
                const collapseEl = bootstrap.Collapse.getInstance(document.getElementById('collapseRuleForm'));
                if (collapseEl) collapseEl.hide();
                loadRules();
            } else {
                alert(data.message || 'Error saving rule');
            }
        } catch(err) {
            alert('Failed to save rule: ' + err.message);
        }
    });

    function showAlert(msg, type = 'info') {
        const ph = document.getElementById('alertPlaceholder');
        ph.innerHTML = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${escapeHtml(msg)}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>`;
        setTimeout(() => { ph.innerHTML = ''; }, 4000);
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
});
</script>
