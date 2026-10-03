<?php
/**
 * @var array<int, array<string, mixed>> $courses
 * @var bool $canManage
 */
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold text-gray-800 mb-1">Courses & Certifications</h1>
            <p class="text-muted small mb-0">Manage training programs, modules, fee structures, and durations.</p>
        </div>
        <?php if ($canManage): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#courseModal" onclick="openCreateCourseModal()">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="me-1"><path d="M12 4v16m8-8H4"/></svg>
            Add New Course
        </button>
        <?php endif; ?>
    </div>

    <!-- Courses Grid -->
    <div class="row g-4" id="coursesGrid">
        <?php foreach ($courses as $c): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100 shadow-sm border-0">
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="badge bg-primary-subtle text-primary fw-semibold px-2 py-1"><?= htmlspecialchars((string)$c['course_code']) ?></span>
                        <span class="badge <?= !empty($c['is_active']) ? 'bg-success' : 'bg-secondary' ?>"><?= !empty($c['is_active']) ? 'Active' : 'Archived' ?></span>
                    </div>
                    <h5 class="card-title fw-bold text-dark mb-2"><?= htmlspecialchars((string)$c['name']) ?></h5>
                    <div class="d-flex align-items-center gap-3 text-muted small mb-3">
                        <span><strong>Duration:</strong> <?= htmlspecialchars((string)($c['duration'] ?? ($c['duration_weeks'] . ' Weeks'))) ?></span>
                        <span>•</span>
                        <span><strong>Fee:</strong> ₹<?= number_format((float)$c['fee'], 2) ?></span>
                    </div>

                    <?php if (!empty($c['modules'])): ?>
                    <h6 class="small fw-bold text-uppercase text-secondary mb-2">Syllabus / Modules (<?= count($c['modules']) ?>)</h6>
                    <ul class="list-group list-group-flush mb-3 flex-grow-1 small">
                        <?php foreach (array_slice($c['modules'], 0, 5) as $mod): ?>
                        <li class="list-group-item px-0 py-1 border-0 text-truncate text-secondary">
                            <span class="text-primary me-1">•</span> <?= htmlspecialchars(is_array($mod) ? ($mod['name'] ?? '') : (string)$mod) ?>
                        </li>
                        <?php endforeach; ?>
                        <?php if (count($c['modules']) > 5): ?>
                        <li class="list-group-item px-0 py-1 border-0 text-muted fst-italic">
                            + <?= count($c['modules']) - 5 ?> more modules
                        </li>
                        <?php endif; ?>
                    </ul>
                    <?php else: ?>
                    <p class="text-muted small fst-italic flex-grow-1">No modules listed yet.</p>
                    <?php endif; ?>

                    <?php if ($canManage): ?>
                    <div class="pt-2 border-top d-flex gap-2">
                        <button class="btn btn-sm btn-outline-secondary flex-grow-1" onclick='openEditCourseModal(<?= json_encode($c) ?>)'>Edit Course</button>
                        <button class="btn btn-sm btn-outline-danger" onclick="deleteCourse(<?= (int)$c['id'] ?>)">Delete</button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Course Modal -->
<?php if ($canManage): ?>
<div class="modal fade" id="courseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form class="modal-content" id="courseForm" onsubmit="saveCourse(event)">
            <input type="hidden" name="id" id="courseId">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="courseModalTitle">Add Course</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Course Name *</label>
                    <input type="text" name="name" id="courseName" class="form-control" required placeholder="e.g. GST Practitioner Certification">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Course Code</label>
                        <input type="text" name="course_code" id="courseCode" class="form-control" placeholder="e.g. CRS-GST">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Tuition Fee (₹) *</label>
                        <input type="number" step="0.01" name="fee" id="courseFee" class="form-control" required placeholder="8000.00">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Duration (Weeks)</label>
                        <input type="number" name="duration_weeks" id="courseWeeks" class="form-control" value="6">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Duration Label</label>
                        <input type="text" name="duration" id="courseDuration" class="form-control" placeholder="e.g. 6 Weeks">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Syllabus / Modules (One per line) *</label>
                    <textarea name="modules" id="courseModules" class="form-control" rows="5" placeholder="Module 1: Fundamentals&#10;Module 2: Practice&#10;Module 3: Filing Workshop"></textarea>
                    <div class="form-text small">Each line becomes an individual trackable module for enrolled students.</div>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" id="courseActive" value="1" checked>
                    <label class="form-check-label small" for="courseActive">Active for Admissions</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSaveCourse">Save Course</button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateCourseModal() {
    document.getElementById('courseForm').reset();
    document.getElementById('courseId').value = '';
    document.getElementById('courseModalTitle').innerText = 'Add Course';
}

function openEditCourseModal(c) {
    document.getElementById('courseId').value = c.id;
    document.getElementById('courseName').value = c.name || '';
    document.getElementById('courseCode').value = c.course_code || '';
    document.getElementById('courseFee').value = c.fee || 0;
    document.getElementById('courseWeeks').value = c.duration_weeks || 4;
    document.getElementById('courseDuration').value = c.duration || '';
    document.getElementById('courseActive').checked = parseInt(c.is_active) === 1;

    const mods = (c.modules || []).map(m => typeof m === 'object' ? (m.name || '') : m).join('\n');
    document.getElementById('courseModules').value = mods;
    document.getElementById('courseModalTitle').innerText = 'Edit Course: ' + c.name;
    const modal = new bootstrap.Modal(document.getElementById('courseModal'));
    modal.show();
}

async function saveCourse(e) {
    e.preventDefault();
    const id = document.getElementById('courseId').value;
    const payload = {
        name: document.getElementById('courseName').value,
        course_code: document.getElementById('courseCode').value,
        fee: parseFloat(document.getElementById('courseFee').value) || 0,
        duration_weeks: parseInt(document.getElementById('courseWeeks').value) || 4,
        duration: document.getElementById('courseDuration').value,
        is_active: document.getElementById('courseActive').checked ? 1 : 0,
        modules: document.getElementById('courseModules').value
    };

    const url = id ? `/api/courses/${id}` : '/api/courses';
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
            alert(data.message || 'Failed to save course');
        }
    } catch (err) {
        alert('Error: ' + err.message);
    }
}

async function deleteCourse(id) {
    if (!confirm('Are you sure you want to delete this course?')) return;
    try {
        const res = await fetch(`/api/courses/${id}`, {method: 'DELETE'});
        const data = await res.json();
        if (data.status === 'success') {
            location.reload();
        } else {
            alert(data.message || 'Failed to delete');
        }
    } catch (err) {
        alert('Error: ' + err.message);
    }
}
</script>
<?php endif; ?>
