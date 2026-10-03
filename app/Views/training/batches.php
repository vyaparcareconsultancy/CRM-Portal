<?php
/**
 * @var array<int, array<string, mixed>> $batches
 * @var array<int, array<string, mixed>> $courses
 * @var array<int, array<string, mixed>> $trainers
 * @var bool $isTrainer
 * @var bool $canManage
 */
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold text-gray-800 mb-1"><?= $isTrainer ? 'My Assigned Batches' : 'Batches & Scheduling' ?></h1>
            <p class="text-muted small mb-0">Track cohort schedules, timings, days, assigned trainers, and seat occupancy.</p>
        </div>
        <?php if ($canManage): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#batchModal" onclick="openCreateBatchModal()">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="me-1"><path d="M12 4v16m8-8H4"/></svg>
            Schedule New Batch
        </button>
        <?php endif; ?>
    </div>

    <!-- Batches Table -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-secondary small text-uppercase">
                        <tr>
                            <th class="ps-3">Batch Code & Name</th>
                            <th>Course</th>
                            <th>Schedule & Days</th>
                            <th>Dates</th>
                            <th>Trainer</th>
                            <th>Enrolled / Capacity</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="small">
                        <?php if (empty($batches)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No batches found.</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($batches as $b): ?>
                        <tr>
                            <td class="ps-3">
                                <span class="badge bg-light text-dark border me-1"><?= htmlspecialchars((string)$b['batch_code']) ?></span>
                                <div class="fw-bold text-dark mt-1"><?= htmlspecialchars((string)$b['name']) ?></div>
                            </td>
                            <td>
                                <span class="fw-semibold text-primary"><?= htmlspecialchars((string)($b['course_name'] ?? 'N/A')) ?></span>
                            </td>
                            <td>
                                <div><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-muted me-1"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg><?= htmlspecialchars((string)($b['timing'] ?? 'Flexible')) ?></div>
                                <div class="text-muted"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="text-muted me-1"><path d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg><?= htmlspecialchars((string)($b['days'] ?? 'All days')) ?></div>
                            </td>
                            <td>
                                <div><span class="text-muted">Start:</span> <?= htmlspecialchars((string)$b['start_date']) ?></div>
                                <?php if (!empty($b['end_date'])): ?>
                                <div><span class="text-muted">End:</span> <?= htmlspecialchars((string)$b['end_date']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= !empty($b['trainer_name']) ? htmlspecialchars((string)$b['trainer_name']) : '<span class="text-muted fst-italic">Unassigned</span>' ?>
                            </td>
                            <td>
                                <?php
                                $enrolled = (int)($b['enrolled_count'] ?? 0);
                                $capacity = (int)($b['capacity'] ?? 30);
                                $percent = $capacity > 0 ? min(100, round(($enrolled / $capacity) * 100)) : 0;
                                ?>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span><?= $enrolled ?> / <?= $capacity ?></span>
                                    <span class="text-muted"><?= $percent ?>%</span>
                                </div>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar <?= $percent >= 100 ? 'bg-danger' : 'bg-primary' ?>" style="width: <?= $percent ?>%"></div>
                                </div>
                            </td>
                            <td>
                                <?php
                                $badgeClass = match ($b['status'] ?? 'upcoming') {
                                    'active' => 'bg-success',
                                    'completed' => 'bg-info text-dark',
                                    'cancelled' => 'bg-danger',
                                    default => 'bg-warning text-dark'
                                };
                                ?>
                                <span class="badge <?= $badgeClass ?> text-capitalize"><?= htmlspecialchars((string)($b['status'] ?? 'upcoming')) ?></span>
                            </td>
                            <td class="text-end pe-3">
                                <div class="btn-group btn-group-sm">
                                    <a href="/attendance?batch_id=<?= (int)$b['id'] ?>" class="btn btn-outline-success" title="Mark Attendance">
                                        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                                        Roll Call
                                    </a>
                                    <button class="btn btn-outline-secondary" onclick="viewRoster(<?= (int)$b['id'] ?>, '<?= htmlspecialchars(addslashes((string)$b['name'])) ?>')">
                                        Roster
                                    </button>
                                    <?php if ($canManage): ?>
                                    <button class="btn btn-outline-secondary" onclick='openEditBatchModal(<?= json_encode($b) ?>)'>Edit</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Batch Create/Edit Modal -->
<?php if ($canManage): ?>
<div class="modal fade" id="batchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="batchForm" onsubmit="saveBatch(event)">
            <input type="hidden" name="id" id="batchId">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="batchModalTitle">Schedule Batch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Course *</label>
                    <select name="course_id" id="batchCourse" class="form-select" required>
                        <option value="">-- Select Course --</option>
                        <?php foreach ($courses as $c): ?>
                        <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars((string)$c['name']) ?> (<?= htmlspecialchars((string)$c['course_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Batch Name / Cohort Label *</label>
                    <input type="text" name="name" id="batchName" class="form-control" required placeholder="e.g. October Morning Batch (Weekday)">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Start Date *</label>
                        <input type="date" name="start_date" id="batchStart" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">End Date (Projected)</label>
                        <input type="date" name="end_date" id="batchEnd" class="form-control">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Timing</label>
                        <input type="text" name="timing" id="batchTiming" class="form-control" placeholder="e.g. 08:00 AM - 10:00 AM">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Days</label>
                        <input type="text" name="days" id="batchDays" class="form-control" placeholder="e.g. Mon, Wed, Fri">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Assigned Trainer</label>
                        <select name="trainer_id" id="batchTrainer" class="form-select">
                            <option value="">-- Unassigned --</option>
                            <?php foreach ($trainers as $t): ?>
                            <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars((string)$t['name']) ?> (<?= htmlspecialchars((string)$t['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Seat Capacity *</label>
                        <input type="number" name="capacity" id="batchCapacity" class="form-control" required value="30">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Status</label>
                    <select name="status" id="batchStatus" class="form-select">
                        <option value="upcoming">Upcoming</option>
                        <option value="active">Active</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSaveBatch">Save Batch</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Batch Roster Modal -->
<div class="modal fade" id="rosterModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="rosterModalTitle">Batch Student Roster</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0 small" id="rosterTable">
                        <thead class="table-light">
                            <tr>
                                <th>Student Code</th>
                                <th>Name</th>
                                <th>Mobile</th>
                                <th>Admission Date</th>
                                <th>Status</th>
                                <th class="text-end">Profile</th>
                            </tr>
                        </thead>
                        <tbody id="rosterBody">
                            <tr><td colspan="6" class="text-center py-3">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function openCreateBatchModal() {
    document.getElementById('batchForm').reset();
    document.getElementById('batchId').value = '';
    document.getElementById('batchCapacity').value = '30';
    document.getElementById('batchModalTitle').innerText = 'Schedule Batch';
}

function openEditBatchModal(b) {
    document.getElementById('batchId').value = b.id;
    document.getElementById('batchCourse').value = b.course_id;
    document.getElementById('batchName').value = b.name || '';
    document.getElementById('batchStart').value = b.start_date || '';
    document.getElementById('batchEnd').value = b.end_date || '';
    document.getElementById('batchTiming').value = b.timing || '';
    document.getElementById('batchDays').value = b.days || '';
    document.getElementById('batchTrainer').value = b.trainer_id || '';
    document.getElementById('batchCapacity').value = b.capacity || 30;
    document.getElementById('batchStatus').value = b.status || 'upcoming';

    document.getElementById('batchModalTitle').innerText = 'Edit Batch: ' + b.name;
    const modal = new bootstrap.Modal(document.getElementById('batchModal'));
    modal.show();
}

async function saveBatch(e) {
    e.preventDefault();
    const id = document.getElementById('batchId').value;
    const payload = {
        course_id: document.getElementById('batchCourse').value,
        name: document.getElementById('batchName').value,
        start_date: document.getElementById('batchStart').value,
        end_date: document.getElementById('batchEnd').value || null,
        timing: document.getElementById('batchTiming').value,
        days: document.getElementById('batchDays').value,
        trainer_id: document.getElementById('batchTrainer').value || null,
        capacity: parseInt(document.getElementById('batchCapacity').value) || 30,
        status: document.getElementById('batchStatus').value
    };

    const url = id ? `/api/batches/${id}` : '/api/batches';
    const method = id ? 'PUT' : 'POST';

    try {
        const res = await fetch(url, {
            method: method,
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.status === 'success') {
            location.reload();
        } else {
            alert(data.message || 'Failed to save batch');
        }
    } catch (err) {
        alert('Error: ' + err.message);
    }
}

async function viewRoster(batchId, batchName) {
    document.getElementById('rosterModalTitle').innerText = 'Student Roster — ' + batchName;
    const tbody = document.getElementById('rosterBody');
    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-3">Loading students...</td></tr>';
    const modal = new bootstrap.Modal(document.getElementById('rosterModal'));
    modal.show();

    try {
        const res = await fetch(`/api/batches/${batchId}/roster`);
        const data = await res.json();
        if (data.status === 'success' && data.data) {
            if (data.data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No students enrolled in this batch yet.</td></tr>';
                return;
            }
            tbody.innerHTML = data.data.map(st => `
                <tr>
                    <td><span class="badge bg-light text-dark border">${st.student_code}</span></td>
                    <td class="fw-bold">${st.student_name}</td>
                    <td>${st.student_mobile || '-'}</td>
                    <td>${st.admission_date}</td>
                    <td><span class="badge ${st.status === 'active' ? 'bg-success' : 'bg-secondary'}">${st.status}</span></td>
                    <td class="text-end">
                        <a href="/students/${st.student_id}" class="btn btn-sm btn-outline-primary">Profile</a>
                    </td>
                </tr>
            `).join('');
        } else {
            tbody.innerHTML = `<tr><td colspan="6" class="text-danger text-center">${data.message || 'Error'}</td></tr>`;
        }
    } catch (err) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-danger text-center">Failed to load roster.</td></tr>`;
    }
}
</script>
