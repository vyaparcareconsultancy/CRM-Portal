<?php
/**
 * @var array<string, mixed> $profile
 * @var bool $isTrainer
 * @var bool $canManage
 * @var bool $canMarkAttendance
 * @var bool $canUpdateProgress
 */
$st = $profile['student'] ?? [];
$attendance = $profile['attendance'] ?? [];
$enrollments = $profile['enrollments'] ?? [];
$financials = $profile['financials'] ?? null;
?>
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="/students" class="text-secondary text-decoration-none small">&larr; Back to Directory</a>
                <span class="badge bg-light text-dark border"><?= htmlspecialchars((string)($st['student_code'] ?? 'ST-0000')) ?></span>
                <span class="badge <?= ($st['status'] ?? '') === 'active' ? 'bg-success' : 'bg-secondary' ?> text-capitalize"><?= htmlspecialchars((string)($st['status'] ?? 'enrolled')) ?></span>
            </div>
            <h1 class="h3 fw-bold text-gray-800 mb-0"><?= htmlspecialchars((string)($st['name'] ?? 'Student Profile')) ?></h1>
        </div>
        <div class="d-flex gap-2">
            <a href="/attendance" class="btn btn-outline-success">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="me-1"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                Mark Attendance
            </a>
        </div>
    </div>

    <!-- Top KPI Cards -->
    <div class="row g-3 mb-4">
        <!-- Attendance Card -->
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <span class="text-uppercase small fw-bold text-muted">Attendance Rate</span>
                    <div class="d-flex align-items-baseline gap-2 mt-2">
                        <h2 class="display-6 fw-bold mb-0 <?= ($attendance['percentage'] ?? 0) >= 75 ? 'text-success' : 'text-warning' ?>">
                            <?= number_format((float)($attendance['percentage'] ?? 0), 1) ?>%
                        </h2>
                        <span class="text-muted small">overall</span>
                    </div>
                    <div class="mt-2 text-muted small">
                        <span><strong><?= $attendance['present'] ?? 0 ?></strong> Present</span> •
                        <span><strong><?= $attendance['late'] ?? 0 ?></strong> Late</span> •
                        <span><strong><?= $attendance['absent'] ?? 0 ?></strong> Absent</span>
                        (<?= $attendance['total'] ?? 0 ?> sessions)
                    </div>
                </div>
            </div>
        </div>

        <!-- Fees Due Card (Only visible to Admin, Accountant, Counselor; strictly hidden from Trainer) -->
        <?php if ($financials !== null): ?>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <span class="text-uppercase small fw-bold text-muted">Fees & Tuition Balance</span>
                    <div class="d-flex align-items-baseline gap-2 mt-2">
                        <h2 class="display-6 fw-bold mb-0 <?= ($financials['fees_due'] ?? 0) > 0 ? 'text-danger' : 'text-success' ?>">
                            ₹<?= number_format((float)($financials['fees_due'] ?? 0), 2) ?>
                        </h2>
                        <span class="text-muted small">due</span>
                    </div>
                    <div class="mt-2 text-muted small">
                        <span>Total Invoiced: ₹<?= number_format((float)($financials['total_invoiced'] ?? 0), 2) ?></span> •
                        <span class="text-success">Paid: ₹<?= number_format((float)($financials['total_paid'] ?? 0), 2) ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Contact Info Card -->
        <div class="<?= $financials !== null ? 'col-md-4' : 'col-md-8' ?>">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <span class="text-uppercase small fw-bold text-muted">Student Details</span>
                    <div class="mt-2 small">
                        <div><strong>Mobile:</strong> <?= htmlspecialchars((string)($st['mobile'] ?? '-')) ?></div>
                        <div><strong>Email:</strong> <?= htmlspecialchars((string)($st['email'] ?? 'Not specified')) ?></div>
                        <div><strong>Qualification:</strong> <?= htmlspecialchars((string)($st['qualification'] ?? 'N/A')) ?></div>
                        <?php if (!empty($st['guardian_name'])): ?>
                        <div><strong>Guardian:</strong> <?= htmlspecialchars((string)$st['guardian_name']) ?> (<?= htmlspecialchars((string)($st['guardian_mobile'] ?? '')) ?>)</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enrolled Courses & Batches Progress -->
    <h5 class="fw-bold text-dark mb-3">Enrolled Courses & Academic Progress</h5>

    <?php if (empty($enrollments)): ?>
    <div class="card border-0 shadow-sm p-4 text-center text-muted">
        This student has not been enrolled in any course batches yet.
    </div>
    <?php else: ?>
    <?php foreach ($enrollments as $enr): ?>
    <div class="card border-0 shadow-sm mb-4">
        <!-- Enrollment Header -->
        <div class="card-header bg-white py-3 border-bottom">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <span class="badge bg-primary-subtle text-primary border me-2"><?= htmlspecialchars((string)$enr['enrollment_no']) ?></span>
                    <h5 class="d-inline fw-bold text-dark mb-0"><?= htmlspecialchars((string)$enr['course_name']) ?></h5>
                    <span class="badge bg-light text-secondary border ms-2"><?= htmlspecialchars((string)$enr['batch_name']) ?></span>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php if (!empty($enr['certificate_ready'])): ?>
                        <span class="badge bg-success-subtle text-success border px-2 py-1 fw-bold">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="me-1"><path d="M5 13l4 4L19 7"/></svg>
                            Certificate Ready
                        </span>
                        <?php if (empty($enr['certificate_no']) && $canManage): ?>
                        <button class="btn btn-sm btn-success" onclick="issueCertificate(<?= (int)$enr['id'] ?>, '<?= htmlspecialchars(addslashes((string)$st['name'])) ?>')">
                            Issue Certificate
                        </button>
                        <?php elseif (!empty($enr['certificate_no'])): ?>
                        <span class="badge bg-info text-dark">Cert: <?= htmlspecialchars((string)$enr['certificate_no']) ?></span>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="badge bg-warning-subtle text-warning border px-2 py-1">In Progress</span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="d-flex gap-4 text-muted small mt-2">
                <span><strong>Batch Code:</strong> <?= htmlspecialchars((string)$enr['batch_code']) ?></span>
                <span><strong>Timing:</strong> <?= htmlspecialchars((string)($enr['batch_timing'] ?? 'Standard')) ?></span>
                <span><strong>Days:</strong> <?= htmlspecialchars((string)($enr['batch_days'] ?? 'All')) ?></span>
                <span><strong>Trainer:</strong> <?= htmlspecialchars((string)($enr['trainer_name'] ?? 'Assigned Faculty')) ?></span>
                <span><strong>Batch Attendance:</strong> <span class="fw-bold text-dark"><?= number_format((float)($enr['attendance_stats']['percentage'] ?? 0), 1) ?>%</span></span>
            </div>
        </div>

        <!-- Modules Progress Table -->
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 small">
                    <thead class="table-light text-secondary">
                        <tr>
                            <th class="ps-3" style="width: 40px;">#</th>
                            <th>Module Title</th>
                            <th style="width: 160px;">Status</th>
                            <th>Test Marks</th>
                            <th>Trainer Remarks</th>
                            <?php if ($canUpdateProgress): ?>
                            <th class="text-end pe-3">Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($enr['progress'])): ?>
                        <tr><td colspan="6" class="text-center py-3 text-muted">No modules registered for this course.</td></tr>
                        <?php else: ?>
                        <?php foreach ($enr['progress'] as $idx => $mod): ?>
                        <tr>
                            <td class="ps-3 text-muted"><?= $idx + 1 ?></td>
                            <td class="fw-semibold text-dark"><?= htmlspecialchars((string)$mod['module_name']) ?></td>
                            <td>
                                <?php
                                $mStatus = $mod['status'] ?? 'not_started';
                                $mBadge = match ($mStatus) {
                                    'completed' => 'bg-success',
                                    'in_progress' => 'bg-primary',
                                    default => 'bg-secondary'
                                };
                                ?>
                                <span class="badge <?= $mBadge ?> text-capitalize"><?= str_replace('_', ' ', $mStatus) ?></span>
                            </td>
                            <td>
                                <?= $mod['test_score'] !== null ? htmlspecialchars((string)$mod['test_score']) . ' / 100' : '<span class="text-muted">N/A</span>' ?>
                            </td>
                            <td class="text-muted">
                                <?= !empty($mod['trainer_remarks']) ? htmlspecialchars((string)$mod['trainer_remarks']) : '—' ?>
                            </td>
                            <?php if ($canUpdateProgress): ?>
                            <td class="text-end pe-3">
                                <button class="btn btn-sm btn-outline-secondary" onclick='openEditProgressModal(<?= (int)$enr['id'] ?>, <?= json_encode($mod) ?>)'>
                                    Update
                                </button>
                            </td>
                            <?php endif; ?>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Edit Progress Modal -->
<?php if ($canUpdateProgress): ?>
<div class="modal fade" id="progressModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="progressForm" onsubmit="saveProgress(event)">
            <input type="hidden" id="progEnrollmentId">
            <input type="hidden" id="progModuleName">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="progressModalTitle">Update Module Progress</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Module</label>
                    <input type="text" id="progDisplayModuleName" class="form-control" readonly disabled>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Status *</label>
                    <select id="progStatus" class="form-select" required>
                        <option value="not_started">Not Started</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Test Marks / Score (Optional, out of 100)</label>
                    <input type="number" step="0.1" id="progTestScore" class="form-control" placeholder="e.g. 85.0">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Trainer Remarks / Evaluation</label>
                    <textarea id="progRemarks" class="form-control" rows="3" placeholder="Notes on student performance, practical exercises..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Progress</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditProgressModal(enrollmentId, mod) {
    document.getElementById('progEnrollmentId').value = enrollmentId;
    document.getElementById('progModuleName').value = mod.module_name;
    document.getElementById('progDisplayModuleName').value = mod.module_name;
    document.getElementById('progStatus').value = mod.status || 'not_started';
    document.getElementById('progTestScore').value = mod.test_score !== null ? mod.test_score : '';
    document.getElementById('progRemarks').value = mod.trainer_remarks || '';

    const modal = new bootstrap.Modal(document.getElementById('progressModal'));
    modal.show();
}

async function saveProgress(e) {
    e.preventDefault();
    const enrollmentId = document.getElementById('progEnrollmentId').value;
    const moduleName = document.getElementById('progModuleName').value;
    const status = document.getElementById('progStatus').value;
    const testScore = document.getElementById('progTestScore').value;
    const remarks = document.getElementById('progRemarks').value;

    const payload = {
        module_name: moduleName,
        status: status,
        test_score: testScore !== '' ? parseFloat(testScore) : null,
        trainer_remarks: remarks
    };

    try {
        const res = await fetch(`/api/enrollments/${enrollmentId}/progress`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.status === 'success') {
            location.reload();
        } else {
            alert(data.message || 'Failed to update progress');
        }
    } catch (err) {
        alert('Error: ' + err.message);
    }
}

async function issueCertificate(enrollmentId, studentName) {
    const certNo = prompt(`Issue completion certificate for ${studentName}. Enter Certificate Number:`, `CERT-${new Date().getFullYear()}-${enrollmentId}`);
    if (!certNo) return;

    try {
        const res = await fetch(`/api/enrollments/${enrollmentId}/certificate`, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({certificate_no: certNo})
        });
        const data = await res.json();
        if (data.status === 'success') {
            alert('Certificate successfully issued!');
            location.reload();
        } else {
            alert(data.message || 'Failed to issue certificate');
        }
    } catch (err) {
        alert('Error: ' + err.message);
    }
}
</script>
<?php endif; ?>
