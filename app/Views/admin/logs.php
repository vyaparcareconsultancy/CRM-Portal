<div class="container-fluid py-4">
    <!-- Header Section -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <h4 class="fw-bold mb-1">System Logs</h4>
            <p class="text-muted small mb-0">Inspect the last 200 lines of error and security audit trails.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <div class="input-group input-group-sm">
                <span class="input-group-text bg-white border-end-0">
                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                </span>
                <input type="date" class="form-control form-control-sm border-start-0 ps-0" id="logDate" value="<?= e($today) ?>" max="<?= e($today) ?>">
            </div>
            <button class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1" id="refreshBtn">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>Refresh</span>
            </button>
        </div>
    </div>

    <!-- Log Channel Tabs -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-bottom-0 pb-0 pt-3">
            <ul class="nav nav-tabs card-header-tabs" id="logTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-semibold text-danger d-flex align-items-center gap-2" id="error-tab" data-bs-toggle="tab" data-bs-target="#error-pane" type="button" role="tab" onclick="switchChannel('error')">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                        <span>Error Logs</span>
                        <span class="badge bg-danger rounded-pill" id="errorCount"><?= count($initialErrors) ?></span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-semibold text-warning d-flex align-items-center gap-2" id="security-tab" data-bs-toggle="tab" data-bs-target="#security-pane" type="button" role="tab" onclick="switchChannel('security')">
                        <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                        <span>Security Logs</span>
                        <span class="badge bg-warning text-dark rounded-pill" id="securityCount"><?= count($initialSecurity) ?></span>
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body p-0">
            <!-- Filter Bar -->
            <div class="p-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                <div class="input-group input-group-sm" style="max-width: 320px;">
                    <span class="input-group-text bg-white border-end-0">
                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
                    </span>
                    <input type="text" class="form-control form-control-sm border-start-0" id="filterInput" placeholder="Filter by message, IP, request ID...">
                </div>
                <div class="text-muted small">Showing up to 200 most recent lines (newest first)</div>
            </div>

            <!-- Tab Contents -->
            <div class="tab-content" id="logTabContent">
                <!-- Error Log Pane -->
                <div class="tab-pane fade show active" id="error-pane" role="tabpanel">
                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="table table-hover table-sm align-middle mb-0" id="errorTable">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="width: 170px;">Timestamp</th>
                                    <th style="width: 90px;">Level</th>
                                    <th style="width: 140px;">Request ID</th>
                                    <th style="width: 120px;">User / IP</th>
                                    <th>Message</th>
                                    <th style="width: 80px;" class="text-end">Details</th>
                                </tr>
                            </thead>
                            <tbody id="errorTbody">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Security Log Pane -->
                <div class="tab-pane fade" id="security-pane" role="tabpanel">
                    <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                        <table class="table table-hover table-sm align-middle mb-0" id="securityTable">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th style="width: 170px;">Timestamp</th>
                                    <th style="width: 90px;">Level</th>
                                    <th style="width: 140px;">Request ID</th>
                                    <th style="width: 120px;">User / IP</th>
                                    <th>Event Description</th>
                                    <th style="width: 80px;" class="text-end">Details</th>
                                </tr>
                            </thead>
                            <tbody id="securityTbody">
                                <!-- Populated dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Log Details Modal -->
<div class="modal fade" id="logModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fs-6 fw-bold">Log Record Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label text-muted small fw-semibold">Message</label>
                    <div class="p-2 bg-light rounded text-break font-monospace small" id="modalMessage"></div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted small fw-semibold">Request ID</label>
                        <div class="p-2 bg-light rounded font-monospace small" id="modalRequestId"></div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small fw-semibold">IP Address</label>
                        <div class="p-2 bg-light rounded font-monospace small" id="modalIp"></div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small fw-semibold">User ID</label>
                        <div class="p-2 bg-light rounded font-monospace small" id="modalUserId"></div>
                    </div>
                </div>
                <div>
                    <label class="form-label text-muted small fw-semibold">Sanitized Context / Trace</label>
                    <pre class="p-3 bg-dark text-light rounded font-monospace small mb-0" style="max-height: 350px; overflow-y: auto;" id="modalContext"></pre>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    let currentChannel = 'error';
    let rawErrorLogs = <?= json_encode($initialErrors, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    let rawSecurityLogs = <?= json_encode($initialSecurity, JSON_HEX_TAG | JSON_HEX_AMP) ?>;

    function renderTable(tbodyId, logs, filter = '') {
        const tbody = document.getElementById(tbodyId);
        tbody.innerHTML = '';

        const filterLower = filter.toLowerCase().trim();
        const filtered = logs.filter(item => {
            if (!filterLower) return true;
            return (
                (item.message && item.message.toLowerCase().includes(filterLower)) ||
                (item.request_id && item.request_id.toLowerCase().includes(filterLower)) ||
                (item.ip && item.ip.toLowerCase().includes(filterLower)) ||
                (item.level && item.level.toLowerCase().includes(filterLower))
            );
        });

        if (filtered.length === 0) {
            tbody.innerHTML = `<tr><td colspan="6" class="text-center py-4 text-muted">No log entries found for this selection.</td></tr>`;
            return;
        }

        filtered.forEach((log, idx) => {
            const tr = document.createElement('tr');
            
            // Level badge color
            let badgeClass = 'bg-secondary';
            const lvl = (log.level || '').toUpperCase();
            if (lvl === 'ERROR' || lvl === 'CRITICAL' || lvl === 'EMERGENCY') badgeClass = 'bg-danger';
            else if (lvl === 'WARNING') badgeClass = 'bg-warning text-dark';
            else if (lvl === 'NOTICE') badgeClass = 'bg-info text-dark';
            else if (lvl === 'INFO') badgeClass = 'bg-primary';

            // User / IP display
            const userIp = `${log.ip || '-'}${log.user_id ? ' (U#' + log.user_id + ')' : ''}`;

            tr.innerHTML = `
                <td class="text-muted small">${escapeHtml(log.datetime)}</td>
                <td><span class="badge ${badgeClass} text-uppercase" style="font-size: 0.7rem;">${escapeHtml(log.level)}</span></td>
                <td><code class="small text-truncate d-inline-block" style="max-width: 130px;" title="${escapeHtml(log.request_id)}">${escapeHtml(log.request_id)}</code></td>
                <td class="small text-truncate" style="max-width: 120px;" title="${escapeHtml(userIp)}">${escapeHtml(userIp)}</td>
                <td class="text-break small">${escapeHtml(log.message)}</td>
                <td class="text-end">
                    <button type="button" class="btn btn-outline-secondary btn-xs py-0 px-2" style="font-size: 0.75rem;" onclick="viewDetails('${currentChannel}', ${idx})">View</button>
                </td>
            `;
            tbody.appendChild(tr);
        });
    }

    function switchChannel(channel) {
        currentChannel = channel;
        document.getElementById('filterInput').value = '';
        if (channel === 'error') {
            renderTable('errorTbody', rawErrorLogs);
        } else {
            renderTable('securityTbody', rawSecurityLogs);
        }
    }

    function viewDetails(channel, index) {
        const list = (channel === 'error') ? rawErrorLogs : rawSecurityLogs;
        const item = list[index];
        if (!item) return;

        document.getElementById('modalMessage').textContent = item.message;
        document.getElementById('modalRequestId').textContent = item.request_id || '-';
        document.getElementById('modalIp').textContent = item.ip || '-';
        document.getElementById('modalUserId').textContent = item.user_id ? '#' + item.user_id : 'Guest / System';
        document.getElementById('modalContext').textContent = JSON.stringify(item.context || {}, null, 2);

        const modal = new bootstrap.Modal(document.getElementById('logModal'));
        modal.show();
    }

    async function fetchLogs() {
        const date = document.getElementById('logDate').value;
        const refreshBtn = document.getElementById('refreshBtn');
        refreshBtn.disabled = true;

        try {
            const res = await api.get(`/api/admin/logs?channel=${currentChannel}&date=${date}`);
            if (res.status === 'success' && res.data) {
                if (currentChannel === 'error') {
                    rawErrorLogs = res.data.lines || [];
                    document.getElementById('errorCount').textContent = rawErrorLogs.length;
                    renderTable('errorTbody', rawErrorLogs, document.getElementById('filterInput').value);
                } else {
                    rawSecurityLogs = res.data.lines || [];
                    document.getElementById('securityCount').textContent = rawSecurityLogs.length;
                    renderTable('securityTbody', rawSecurityLogs, document.getElementById('filterInput').value);
                }
            }
        } catch (err) {
            UI.toast('Failed to load logs: ' + (err.message || 'Unknown error'), 'danger');
        } finally {
            refreshBtn.disabled = false;
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        const p = document.createElement('p');
        p.textContent = str;
        return p.innerHTML;
    }

    document.getElementById('filterInput').addEventListener('input', function (e) {
        const query = e.target.value;
        if (currentChannel === 'error') {
            renderTable('errorTbody', rawErrorLogs, query);
        } else {
            renderTable('securityTbody', rawSecurityLogs, query);
        }
    });

    document.getElementById('logDate').addEventListener('change', fetchLogs);
    document.getElementById('refreshBtn').addEventListener('click', fetchLogs);

    // Initial render
    renderTable('errorTbody', rawErrorLogs);
</script>
