<?php
/**
 * @var array<int, array<string, mixed>> $batches
 * @var bool $isTrainer
 * @var bool $canManage
 */
$selectedBatchId = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : ($batches[0]['id'] ?? 0);
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$selectedMonth = $_GET['month'] ?? date('Y-m');
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold text-gray-800 mb-1">Attendance Tracker</h1>
            <p class="text-muted small mb-0">Daily roll call, bulk status marking, and monthly attendance sheets.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" onclick="toggleMonthlySheet()">
                <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="me-1"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span id="btnSheetToggleText">View Monthly Sheet</span>
            </button>
        </div>
    </div>

    <!-- Batch & Date Filter Header -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label small fw-semibold text-secondary">Select Batch *</label>
                    <select class="form-select" id="attBatchSelect" onchange="loadAttendance()">
                        <?php foreach ($batches as $b): ?>
                        <option value="<?= (int)$b['id'] ?>" <?= (int)$b['id'] === (int)$selectedBatchId ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string)$b['name']) ?> (<?= htmlspecialchars((string)($b['course_name'] ?? 'Course')) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3" id="colSessionDate">
                    <label class="form-label small fw-semibold text-secondary">Session Date</label>
                    <input type="date" class="form-control" id="attSessionDate" value="<?= htmlspecialchars($selectedDate) ?>" onchange="loadAttendance()">
                </div>
                <div class="col-md-3 d-none" id="colMonthlySelector">
                    <label class="form-label small fw-semibold text-secondary">Month (YYYY-MM)</label>
                    <input type="month" class="form-control" id="attMonthInput" value="<?= htmlspecialchars($selectedMonth) ?>" onchange="loadMonthlySheet()">
                </div>
                <div class="col-md-4 d-flex gap-2" id="colQuickActions">
                    <button class="btn btn-outline-success btn-sm flex-grow-1" onclick="markAll('present')">All Present</button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="markAll('absent')">All Absent</button>
                    <button class="btn btn-primary btn-sm flex-grow-1" onclick="saveAttendance()">Save Roll Call</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Daily Roster Card -->
    <div class="card shadow-sm border-0" id="dailyAttendanceSection">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark">Student Roll Call — <span id="displayDate" class="text-primary"><?= htmlspecialchars($selectedDate) ?></span></h6>
            <span class="badge bg-light text-secondary border" id="studentCountBadge">0 Students</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="attendanceTable">
                    <thead class="table-light small text-uppercase text-secondary">
                        <tr>
                            <th class="ps-3">Code</th>
                            <th>Student Name</th>
                            <th>Mobile</th>
                            <th style="width: 320px;">Attendance Status</th>
                            <th>Remarks (Optional)</th>
                        </tr>
                    </thead>
                    <tbody class="small" id="attendanceBody">
                        <tr><td colspan="5" class="text-center py-4">Loading student roster...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer bg-white py-3 d-flex justify-content-between align-items-center">
            <div class="small text-muted" id="savedStatusText">Changes not saved</div>
            <button class="btn btn-primary" onclick="saveAttendance()">Save Attendance</button>
        </div>
    </div>

    <!-- Monthly Sheet Card (Hidden by default) -->
    <div class="card shadow-sm border-0 d-none" id="monthlySheetSection">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="fw-bold mb-0 text-dark">Monthly Attendance Register — <span id="displayMonth" class="text-primary"><?= htmlspecialchars($selectedMonth) ?></span></h6>
            <button class="btn btn-sm btn-outline-secondary" onclick="loadMonthlySheet()">Refresh Register</button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive" style="max-height: 550px;">
                <table class="table table-bordered table-sm align-middle text-center mb-0 small" id="monthlyTable">
                    <thead class="table-light sticky-top" id="monthlyThead">
                        <tr>
                            <th class="text-start ps-3" style="min-width: 180px;">Student Name</th>
                            <th>Attendance %</th>
                        </tr>
                    </thead>
                    <tbody id="monthlyTbody">
                        <tr><td colspan="3" class="text-center py-4">Loading monthly sheet...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
let currentRoster = [];
let isMonthlyMode = false;

document.addEventListener('DOMContentLoaded', () => {
    loadAttendance();
});

function toggleMonthlySheet() {
    isMonthlyMode = !isMonthlyMode;
    const dailySec = document.getElementById('dailyAttendanceSection');
    const monthSec = document.getElementById('monthlySheetSection');
    const colDate = document.getElementById('colSessionDate');
    const colMonth = document.getElementById('colMonthlySelector');
    const colActions = document.getElementById('colQuickActions');
    const btnText = document.getElementById('btnSheetToggleText');

    if (isMonthlyMode) {
        dailySec.classList.add('d-none');
        monthSec.classList.remove('d-none');
        colDate.classList.add('d-none');
        colMonth.classList.remove('d-none');
        colActions.classList.add('d-none');
        btnText.innerText = 'Back to Daily Roll Call';
        loadMonthlySheet();
    } else {
        dailySec.classList.remove('d-none');
        monthSec.classList.add('d-none');
        colDate.classList.remove('d-none');
        colMonth.classList.add('d-none');
        colActions.classList.remove('d-none');
        btnText.innerText = 'View Monthly Sheet';
        loadAttendance();
    }
}

async function loadAttendance() {
    const batchId = document.getElementById('attBatchSelect').value;
    const date = document.getElementById('attSessionDate').value;
    if (!batchId) return;

    document.getElementById('displayDate').innerText = date;
    const tbody = document.getElementById('attendanceBody');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4">Loading student roster...</td></tr>';

    try {
        const res = await fetch(`/api/batches/${batchId}/attendance?date=${encodeURIComponent(date)}`);
        const data = await res.json();
        if (data.status === 'success' && data.data) {
            currentRoster = data.data.roster || [];
            document.getElementById('studentCountBadge').innerText = `${currentRoster.length} Students`;
            renderDailyTable(currentRoster);
            document.getElementById('savedStatusText').innerText = 'Data loaded';
        } else {
            tbody.innerHTML = `<tr><td colspan="5" class="text-danger text-center py-4">${data.message || 'Error'}</td></tr>`;
        }
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-danger text-center py-4">Failed to load roster.</td></tr>';
    }
}

function renderDailyTable(roster) {
    const tbody = document.getElementById('attendanceBody');
    if (roster.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No students enrolled in this batch.</td></tr>';
        return;
    }

    tbody.innerHTML = roster.map(st => {
        const status = st.status || 'present';
        const remarks = st.remarks || '';
        const sid = st.student_id;

        return `
            <tr data-student-id="${sid}">
                <td class="ps-3"><span class="badge bg-light text-dark border">${st.student_code}</span></td>
                <td>
                    <a href="/students/${sid}" class="fw-bold text-dark text-decoration-none">${st.student_name}</a>
                </td>
                <td class="text-muted">${st.mobile || '-'}</td>
                <td>
                    <div class="btn-group btn-group-sm w-100" role="group">
                        <input type="radio" class="btn-check" name="status_${sid}" id="pres_${sid}" value="present" ${status === 'present' ? 'checked' : ''} onchange="markDirty()">
                        <label class="btn btn-outline-success" for="pres_${sid}">Present</label>

                        <input type="radio" class="btn-check" name="status_${sid}" id="abs_${sid}" value="absent" ${status === 'absent' ? 'checked' : ''} onchange="markDirty()">
                        <label class="btn btn-outline-danger" for="abs_${sid}">Absent</label>

                        <input type="radio" class="btn-check" name="status_${sid}" id="late_${sid}" value="late" ${status === 'late' ? 'checked' : ''} onchange="markDirty()">
                        <label class="btn btn-outline-warning" for="late_${sid}">Late</label>

                        <input type="radio" class="btn-check" name="status_${sid}" id="exc_${sid}" value="excused" ${status === 'excused' ? 'checked' : ''} onchange="markDirty()">
                        <label class="btn btn-outline-secondary" for="exc_${sid}">Excused</label>
                    </div>
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm" id="rem_${sid}" value="${remarks}" placeholder="e.g. Arrived 15m late" onchange="markDirty()">
                </td>
            </tr>
        `;
    }).join('');
}

function markAll(status) {
    currentRoster.forEach(st => {
        const el = document.getElementById(`${status.substring(0, 4)}_${st.student_id}`);
        if (el) el.checked = true;
    });
    markDirty();
}

function markDirty() {
    document.getElementById('savedStatusText').innerText = 'Unsaved changes pending...';
    document.getElementById('savedStatusText').className = 'small text-warning fw-semibold';
}

async function saveAttendance() {
    const batchId = document.getElementById('attBatchSelect').value;
    const date = document.getElementById('attSessionDate').value;
    if (!batchId) return;

    const records = {};
    const rows = document.querySelectorAll('#attendanceBody tr[data-student-id]');
    rows.forEach(tr => {
        const sid = tr.getAttribute('data-student-id');
        const checked = tr.querySelector(`input[name="status_${sid}"]:checked`);
        const status = checked ? checked.value : 'present';
        const remEl = document.getElementById(`rem_${sid}`);
        const remarks = remEl ? remEl.value : null;

        records[sid] = {status, remarks};
    });

    try {
        const res = await fetch(`/api/batches/${batchId}/attendance`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                session_date: date,
                attendance: records
            })
        });
        const data = await res.json();
        if (data.status === 'success') {
            document.getElementById('savedStatusText').innerText = `Saved ${data.data.marked_count} records successfully at ${new Date().toLocaleTimeString()}`;
            document.getElementById('savedStatusText').className = 'small text-success fw-semibold';
        } else {
            alert(data.message || 'Failed to save attendance');
        }
    } catch (err) {
        alert('Error: ' + err.message);
    }
}

async function loadMonthlySheet() {
    const batchId = document.getElementById('attBatchSelect').value;
    const month = document.getElementById('attMonthInput').value;
    if (!batchId) return;

    document.getElementById('displayMonth').innerText = month;
    const thead = document.getElementById('monthlyThead');
    const tbody = document.getElementById('monthlyTbody');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4">Generating attendance matrix...</td></tr>';

    try {
        const res = await fetch(`/api/batches/${batchId}/attendance/monthly?month=${encodeURIComponent(month)}`);
        const data = await res.json();
        if (data.status === 'success' && data.data) {
            const sheet = data.data;
            const dates = sheet.dates || [];
            const students = sheet.students || [];

            // Build Header
            let thHtml = `<tr>
                <th class="text-start ps-3" style="min-width: 180px;">Student Name</th>
                <th style="min-width: 90px;">Attendance %</th>
                <th style="min-width: 90px;">Present / Total</th>`;

            dates.forEach(d => {
                const dayNum = d.split('-')[2];
                thHtml += `<th title="${d}" style="width: 38px;">${dayNum}</th>`;
            });
            thHtml += `</tr>`;
            thead.innerHTML = thHtml;

            // Build Body
            if (students.length === 0) {
                tbody.innerHTML = `<tr><td colspan="${dates.length + 3}" class="text-center py-4 text-muted">No students in batch.</td></tr>`;
                return;
            }

            tbody.innerHTML = students.map(st => {
                const summary = st.summary || {};
                const pct = summary.percentage || 0;
                let pctClass = pct >= 80 ? 'text-success fw-bold' : (pct >= 60 ? 'text-warning fw-bold' : 'text-danger fw-bold');

                let rowHtml = `<tr>
                    <td class="text-start ps-3 fw-semibold text-truncate">
                        <a href="/students/${st.id}" class="text-dark text-decoration-none">${st.name}</a>
                    </td>
                    <td class="${pctClass}">${pct}%</td>
                    <td class="text-muted">${summary.present + summary.late} / ${summary.total}</td>`;

                dates.forEach(d => {
                    const stStatus = (st.attendance_by_date || {})[d];
                    let icon = '·';
                    let bg = '';
                    if (stStatus === 'present') { icon = 'P'; bg = 'text-success fw-bold'; }
                    else if (stStatus === 'absent') { icon = 'A'; bg = 'text-danger fw-bold bg-danger-subtle'; }
                    else if (stStatus === 'late') { icon = 'L'; bg = 'text-warning fw-bold'; }
                    else if (stStatus === 'excused') { icon = 'E'; bg = 'text-secondary'; }

                    rowHtml += `<td class="${bg}">${icon}</td>`;
                });

                rowHtml += `</tr>`;
                return rowHtml;
            }).join('');
        } else {
            tbody.innerHTML = `<tr><td colspan="5" class="text-danger text-center">${data.message || 'Error'}</td></tr>`;
        }
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-danger text-center">Failed to load monthly sheet.</td></tr>';
    }
}
</script>
