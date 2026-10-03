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

    <!-- Phase 6: Student Documents & Unified Activity Timeline -->
    <div class="row g-4 mt-1 mb-4">
        <!-- Student Documents Column -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <h6 class="fw-bold mb-0 text-primary">Student Documents</h6>
                        <span class="badge bg-light text-secondary border" id="studentDocsBadge">0</span>
                    </div>
                    <?php if (can('document.manage')): ?>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#uploadStudentDocModal">
                        + Upload Document
                    </button>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="studentDocsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Document & Title</th>
                                    <th>Type</th>
                                    <th>Doc Number</th>
                                    <th>Date</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody id="studentDocsTableBody">
                                <tr><td colspan="5" class="text-center py-4 text-muted">Loading documents...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Student Unified Timeline Column -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold mb-0 text-primary">Activity & Notes Timeline</h6>
                    <!-- Filter Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="studentTimelineFilterBtn" data-bs-toggle="dropdown" aria-expanded="false">
                            Filter: <span id="currentStudentFilterLabel">All</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm" id="studentTimelineFilterMenu">
                            <li><a class="dropdown-item active student-filter-opt" href="#" data-filter="all">All Events</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item student-filter-opt" href="#" data-filter="note">Notes (@mentions)</a></li>
                            <li><a class="dropdown-item student-filter-opt" href="#" data-filter="payment">Payments</a></li>
                            <li><a class="dropdown-item student-filter-opt" href="#" data-filter="document">Documents</a></li>
                            <li><a class="dropdown-item student-filter-opt" href="#" data-filter="status">Status Changes</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Quick Add Note Box -->
                <div class="p-3 border-bottom bg-light">
                    <form id="studentQuickNoteForm" onsubmit="addStudentNote(event)">
                        <div class="mb-2">
                            <textarea class="form-control form-control-sm" id="studentQuickNoteText" rows="2" placeholder="Write a note... Use @name to mention staff" required></textarea>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="form-check form-check-inline mb-0">
                                <input class="form-check-input" type="checkbox" id="studentQuickNotePin">
                                <label class="form-check-label small text-muted" for="studentQuickNotePin">Pin to top</label>
                            </div>
                            <button type="submit" class="btn btn-sm btn-primary" id="studentSubmitNoteBtn">
                                Add Note
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Timeline Items -->
                <div class="card-body p-3" style="max-height: 420px; overflow-y: auto;">
                    <div id="studentTimelineList" class="timeline small">
                        <div class="text-muted text-center py-3">Loading timeline...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Student Document Modal -->
<div class="modal fade" id="uploadStudentDocModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="uploadStudentDocForm" onsubmit="uploadStudentDoc(event)">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Upload Student Document</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Document Type *</label>
                    <select class="form-select" id="stdDocType" required>
                        <option value="pan">PAN Card (Encrypted at rest)</option>
                        <option value="aadhaar">Aadhaar Card (Masked last 4 digits, Admin/Accountant only)</option>
                        <option value="photo">Passport Photo</option>
                        <option value="bank_statement_cheque">Bank Statement / Cheque (Encrypted at rest)</option>
                        <option value="other" selected>Academic Certificate / Other</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Title</label>
                    <input type="text" class="form-control" id="stdDocTitle" placeholder="e.g. 12th Marks Sheet, Aadhaar Card">
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">ID / Document Number</label>
                    <input type="text" class="form-control font-monospace" id="stdDocNumber" placeholder="e.g. 1234 5678 9012">
                    <div class="form-text small text-warning-emphasis">Aadhaar is automatically masked (only last 4 digits stored).</div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Select File (PDF, JPG, PNG) *</label>
                    <input type="file" class="form-control" id="stdDocFile" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="stdDocUploadSubmitBtn">Upload Securely</button>
            </div>
        </form>
    </div>
</div>

<script>
const currentStudentId = <?= (int)($st['id'] ?? 0) ?>;
let studentCurrentFilter = 'all';

async function loadStudentDocuments() {
    const tbody = document.getElementById('studentDocsTableBody');
    const badge = document.getElementById('studentDocsBadge');
    if (!tbody) return;

    try {
        const res = await fetch(`/api/documents?entity_type=student&entity_id=${currentStudentId}`);
        const json = await res.json();
        const docs = json.data || [];
        if (badge) badge.textContent = docs.length;

        if (docs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No documents uploaded.</td></tr>';
            return;
        }

        tbody.innerHTML = docs.map(d => {
            const kb = (d.size_bytes / 1024).toFixed(1);
            const sizeStr = kb > 1024 ? (kb / 1024).toFixed(2) + ' MB' : kb + ' KB';
            const dateStr = (d.created_at || '').substring(0, 10);
            const canDownload = d.can_download !== false;

            const downloadBtn = canDownload
                ? `<a href="/api/documents/${d.id}/download" target="_blank" class="btn btn-sm btn-outline-secondary" title="Download">
                       <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                   </a>`
                : `<button type="button" class="btn btn-sm btn-outline-secondary disabled" title="Aadhaar view restricted to Admin/Accountant" disabled>
                       <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                   </button>`;

            return `<tr>
                <td>
                    <div class="fw-semibold text-dark">${d.title || d.original_name}</div>
                    <small class="text-muted">${d.original_name} • ${sizeStr}</small>
                </td>
                <td><span class="badge bg-secondary text-uppercase" style="font-size: 0.65rem;">${(d.document_type || 'other').replace(/_/g, ' ')}</span></td>
                <td><span class="font-monospace small">${d.document_number || '-'}</span></td>
                <td><small class="text-muted">${dateStr}</small></td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm">
                        ${downloadBtn}
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteStudentDoc(${d.id})" title="Delete">&times;</button>
                    </div>
                </td>
            </tr>`;
        }).join('');
    } catch (e) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-danger">Failed to load documents.</td></tr>';
    }
}

async function uploadStudentDoc(e) {
    e.preventDefault();
    const fileInput = document.getElementById('stdDocFile');
    if (!fileInput.files || fileInput.files.length === 0) return;

    const btn = document.getElementById('stdDocUploadSubmitBtn');
    btn.disabled = true;
    btn.textContent = 'Uploading...';

    const formData = new FormData();
    formData.append('document', fileInput.files[0]);
    formData.append('entity_type', 'student');
    formData.append('entity_id', currentStudentId);
    formData.append('document_type', document.getElementById('stdDocType').value);
    formData.append('title', document.getElementById('stdDocTitle').value);
    formData.append('document_number', document.getElementById('stdDocNumber').value);

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const res = await fetch('/api/documents/upload', {
            method: 'POST',
            headers: {'X-CSRF-Token': csrfToken},
            body: formData
        });
        const data = await res.json();
        if (data.status === 'success') {
            bootstrap.Modal.getInstance(document.getElementById('uploadStudentDocModal')).hide();
            document.getElementById('uploadStudentDocForm').reset();
            loadStudentDocuments();
            loadStudentTimeline(studentCurrentFilter);
        } else {
            alert(data.message || 'Upload failed');
        }
    } catch (err) {
        alert('Error: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Upload Securely';
    }
}

async function deleteStudentDoc(id) {
    if (!confirm('Are you sure you want to delete this document?')) return;
    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        await fetch(`/api/documents/${id}`, {
            method: 'DELETE',
            headers: {'X-CSRF-Token': csrfToken}
        });
        loadStudentDocuments();
        loadStudentTimeline(studentCurrentFilter);
    } catch (err) {
        alert('Failed to delete document');
    }
}

async function loadStudentTimeline(filter = 'all') {
    const container = document.getElementById('studentTimelineList');
    if (!container) return;

    try {
        const res = await fetch(`/api/timeline?entity_type=student&entity_id=${currentStudentId}&filter=${filter}`);
        const json = await res.json();
        const events = json.data || [];

        if (events.length === 0) {
            container.innerHTML = '<div class="text-muted text-center py-4 small">No events recorded.</div>';
            return;
        }

        container.innerHTML = events.map(ev => {
            const timeStr = (ev.timestamp || '').substring(0, 16);
            const isPinned = ev.is_pinned;
            const isNote = ev.type === 'note';

            let desc = (ev.description || '').replace(/@([a-zA-Z0-9_\.-]+)/g, '<span class="badge bg-primary-subtle text-primary border border-primary-subtle">@$1</span>');
            let actions = '';
            if (isNote) {
                const noteId = ev.metadata?.note_id || ev.source_id;
                actions = `
                    <button class="btn btn-link text-warning p-0 me-2" onclick="toggleStudentNotePin(${noteId})" title="${isPinned ? 'Unpin' : 'Pin to top'}">${isPinned ? '★' : '☆'}</button>
                    <button class="btn btn-link text-danger p-0" onclick="deleteStudentNote(${noteId})" title="Delete">&times;</button>
                `;
            }

            return `<div class="list-group-item px-0 py-2 border-bottom ${isPinned ? 'bg-warning-subtle p-2 rounded mb-1 border' : ''}">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div class="d-flex align-items-center gap-1">
                        <span class="badge ${ev.badge_class || 'bg-secondary'}" style="font-size: 0.65rem;">${ev.type.toUpperCase()}</span>
                        ${isPinned ? '<span class="badge bg-warning text-dark" style="font-size: 0.65rem;">PINNED</span>' : ''}
                    </div>
                    <div class="d-flex align-items-center gap-1">
                        <span class="text-muted" style="font-size: 0.72rem;">${timeStr}</span>
                        ${actions}
                    </div>
                </div>
                <div class="fw-semibold small text-dark">${ev.title}</div>
                <div class="small text-muted text-break my-1">${desc}</div>
                <div class="text-muted" style="font-size: 0.72rem;">By: <strong>${ev.author_name || 'System'}</strong></div>
            </div>`;
        }).join('');
    } catch (e) {
        container.innerHTML = '<div class="text-danger text-center py-3 small">Failed to load timeline.</div>';
    }
}

async function addStudentNote(e) {
    e.preventDefault();
    const noteInput = document.getElementById('studentQuickNoteText');
    const pinInput = document.getElementById('studentQuickNotePin');
    const text = noteInput.value.trim();
    if (!text) return;

    const btn = document.getElementById('studentSubmitNoteBtn');
    btn.disabled = true;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
        const res = await fetch('/api/timeline/notes', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                entity_type: 'student',
                entity_id: currentStudentId,
                note: text,
                is_pinned: pinInput.checked ? 1 : 0
            })
        });
        const data = await res.json();
        if (data.status === 'success') {
            noteInput.value = '';
            pinInput.checked = false;
            loadStudentTimeline(studentCurrentFilter);
        } else {
            alert(data.message || 'Failed to add note');
        }
    } catch (err) {
        alert('Error: ' + err.message);
    } finally {
        btn.disabled = false;
    }
}

async function toggleStudentNotePin(id) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    await fetch(`/api/timeline/notes/${id}/pin`, {
        method: 'POST',
        headers: {'X-CSRF-Token': csrfToken}
    });
    loadStudentTimeline(studentCurrentFilter);
}

async function deleteStudentNote(id) {
    if (!confirm('Are you sure you want to delete this note?')) return;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    await fetch(`/api/timeline/notes/${id}`, {
        method: 'DELETE',
        headers: {'X-CSRF-Token': csrfToken}
    });
    loadStudentTimeline(studentCurrentFilter);
}

document.addEventListener('DOMContentLoaded', () => {
    loadStudentDocuments();
    loadStudentTimeline(studentCurrentFilter);

    document.querySelectorAll('.student-filter-opt').forEach(opt => {
        opt.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelectorAll('.student-filter-opt').forEach(el => el.classList.remove('active'));
            opt.classList.add('active');
            studentCurrentFilter = opt.dataset.filter || 'all';
            document.getElementById('currentStudentFilterLabel').textContent = opt.textContent.trim();
            loadStudentTimeline(studentCurrentFilter);
        });
    });
});
</script>

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
