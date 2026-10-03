<?php
/**
 * @var array<int, array<string, mixed>> $batches
 * @var bool $isTrainer
 * @var bool $canManage
 */
?>
<div class="container-fluid py-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 fw-bold text-gray-800 mb-1"><?= $isTrainer ? 'My Batch Students' : 'Students & Admissions' ?></h1>
            <p class="text-muted small mb-0">Admit students from leads or walk-ins, link fee plans to Phase 4 invoicing, and track academic status.</p>
        </div>
        <?php if ($canManage): ?>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#admissionModal" onclick="openAdmissionModal()">
            <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="me-1"><path d="M12 4v16m8-8H4"/></svg>
            New Student Admission
        </button>
        <?php endif; ?>
    </div>

    <!-- Filters Header -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-5">
                    <input type="text" class="form-control" id="studentSearch" placeholder="Search by name, student code, mobile..." oninput="debounceSearch()">
                </div>
                <div class="col-md-4">
                    <select class="form-select" id="filterBatch" onchange="loadStudents()">
                        <option value="">-- All Batches --</option>
                        <?php foreach ($batches as $b): ?>
                        <option value="<?= (int)$b['id'] ?>"><?= htmlspecialchars((string)$b['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3 text-end">
                    <button class="btn btn-outline-secondary w-100" onclick="loadStudents()">Filter Students</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Students Directory Table -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small text-uppercase text-secondary">
                        <tr>
                            <th class="ps-3">Student Code</th>
                            <th>Student Name</th>
                            <th>Mobile / WhatsApp</th>
                            <th>Course</th>
                            <th>Batch</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Profile</th>
                        </tr>
                    </thead>
                    <tbody class="small" id="studentsTableBody">
                        <tr><td colspan="7" class="text-center py-4">Loading students directory...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Admission Modal -->
<?php if ($canManage): ?>
<div class="modal fade" id="admissionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form class="modal-content" id="admissionForm" onsubmit="submitAdmission(event)">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Student Admission & Course Enrollment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Source Selection -->
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">Admission Source</label>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="student_source" id="srcNew" value="new" checked onchange="toggleSourceFields()">
                            <label class="form-check-label" for="srcNew">New Student Registration</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="student_source" id="srcLead" value="lead" onchange="toggleSourceFields()">
                            <label class="form-check-label" for="srcLead">From Inbound Lead</label>
                        </div>
                    </div>
                </div>

                <!-- Lead Source Fields -->
                <div id="leadSourceFields" class="d-none mb-3 p-3 bg-light rounded border">
                    <label class="form-label small fw-semibold">Select Lead *</label>
                    <select class="form-select" id="leadSelect" name="lead_id" onchange="populateFromLead()">
                        <option value="">-- Choose Lead --</option>
                    </select>
                </div>

                <!-- New Student Fields -->
                <div id="newStudentFields">
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Student Full Name *</label>
                            <input type="text" name="name" id="admName" class="form-control" required placeholder="Full Name">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Mobile Number *</label>
                            <input type="text" name="mobile" id="admMobile" class="form-control" required placeholder="10-digit mobile">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Address</label>
                            <input type="email" name="email" id="admEmail" class="form-control" placeholder="student@example.com">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Highest Qualification</label>
                            <input type="text" name="qualification" id="admQual" class="form-control" placeholder="e.g. B.Com, M.Com, MBA">
                        </div>
                    </div>
                </div>

                <hr class="my-3">

                <!-- Course & Batch Selection -->
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Course *</label>
                        <select name="course_id" id="admCourse" class="form-select" required onchange="onCourseSelected()">
                            <option value="">-- Select Course --</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Batch Cohort *</label>
                        <select name="batch_id" id="admBatch" class="form-select" required>
                            <option value="">-- Select Batch --</option>
                        </select>
                    </div>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Admission Date *</label>
                        <input type="date" name="admission_date" id="admDate" class="form-control" required value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Agreed Tuition Fee (₹) *</label>
                        <input type="number" step="0.01" name="agreed_fee" id="admAgreedFee" class="form-control" required placeholder="0.00">
                    </div>
                </div>

                <!-- Phase 4 Invoicing & Fee Plan -->
                <div class="card bg-light border-0 mb-3">
                    <div class="card-body p-3">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="create_invoice" id="chkCreateInvoice" value="1" checked onchange="toggleInvoiceOptions()">
                            <label class="form-check-label fw-semibold small" for="chkCreateInvoice">
                                Generate Phase 4 Invoice & Fee Plan (Tuition Billing)
                            </label>
                        </div>
                        <div id="invoiceOptions">
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label small">Concession / Discount (₹)</label>
                                    <input type="number" step="0.01" name="discount_amount" id="admDiscount" class="form-control form-control-sm" value="0.00">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">GST Rate %</label>
                                    <select name="gst_rate_pct" id="admGstRate" class="form-select form-select-sm">
                                        <option value="0">0% (Exempt)</option>
                                        <option value="18">18% (Standard)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small">Installment Plan</label>
                                    <select name="installment_count" id="admInstallments" class="form-select form-select-sm">
                                        <option value="1">1 Payment (Full)</option>
                                        <option value="2">2 Installments</option>
                                        <option value="3">3 Installments</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="btnSubmitAdmission">Admit & Enroll Student</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
let searchTimeout = null;
let allCourses = [];
let allBatches = [];
let inboundLeads = [];

document.addEventListener('DOMContentLoaded', () => {
    loadStudents();
    loadCoursesAndBatches();
});

function debounceSearch() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(loadStudents, 300);
}

async function loadStudents() {
    const search = document.getElementById('studentSearch').value;
    const batchId = document.getElementById('filterBatch').value;
    const tbody = document.getElementById('studentsTableBody');

    let url = '/api/students?';
    if (search) url += `search=${encodeURIComponent(search)}&`;
    if (batchId) url += `batch_id=${encodeURIComponent(batchId)}`;

    try {
        const res = await fetch(url);
        const data = await res.json();
        if (data.status === 'success' && data.data) {
            const list = data.data;
            if (list.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No students found matching query.</td></tr>';
                return;
            }

            tbody.innerHTML = list.map(st => `
                <tr>
                    <td class="ps-3"><span class="badge bg-light text-dark border">${st.student_code}</span></td>
                    <td class="fw-bold"><a href="/students/${st.id}" class="text-dark text-decoration-none">${st.name}</a></td>
                    <td>
                        <div>${st.mobile}</div>
                        ${st.email ? `<small class="text-muted">${st.email}</small>` : ''}
                    </td>
                    <td><span class="text-primary">${st.current_course_name || st.course_name || '-'}</span></td>
                    <td><span class="badge bg-light text-secondary border">${st.current_batch_name || '-'}</span></td>
                    <td>
                        <span class="badge ${st.status === 'active' ? 'bg-success' : (st.status === 'completed' ? 'bg-info text-dark' : 'bg-secondary')}">${st.status}</span>
                    </td>
                    <td class="text-end pe-3">
                        <a href="/students/${st.id}" class="btn btn-sm btn-outline-primary">View Profile</a>
                    </td>
                </tr>
            `).join('');
        }
    } catch (err) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-danger text-center">Failed to load students directory.</td></tr>';
    }
}

async function loadCoursesAndBatches() {
    try {
        const [cRes, bRes] = await Promise.all([
            fetch('/api/courses'),
            fetch('/api/batches')
        ]);
        const cData = await cRes.json();
        const bData = await bRes.json();

        if (cData.status === 'success') allCourses = cData.data || [];
        if (bData.status === 'success') allBatches = bData.data || [];

        const cSelect = document.getElementById('admCourse');
        if (cSelect) {
            cSelect.innerHTML = '<option value="">-- Select Course --</option>' +
                allCourses.map(c => `<option value="${c.id}" data-fee="${c.fee}">${c.name} (₹${parseFloat(c.fee).toLocaleString()})</option>`).join('');
        }
    } catch (e) {}
}

function onCourseSelected() {
    const cSelect = document.getElementById('admCourse');
    const selectedCourseId = parseInt(cSelect.value);
    const selectedOpt = cSelect.options[cSelect.selectedIndex];
    const fee = selectedOpt ? selectedOpt.getAttribute('data-fee') : 0;

    if (fee) {
        document.getElementById('admAgreedFee').value = fee;
    }

    const bSelect = document.getElementById('admBatch');
    const filtered = allBatches.filter(b => parseInt(b.course_id) === selectedCourseId);
    bSelect.innerHTML = '<option value="">-- Select Batch --</option>' +
        filtered.map(b => `<option value="${b.id}">${b.name} (${b.timing || 'Standard'})</option>`).join('');
}

function toggleSourceFields() {
    const isLead = document.getElementById('srcLead').checked;
    document.getElementById('leadSourceFields').classList.toggle('d-none', !isLead);
    document.getElementById('admName').required = !isLead;
    document.getElementById('admMobile').required = !isLead;

    if (isLead && inboundLeads.length === 0) {
        loadLeadsList();
    }
}

async function loadLeadsList() {
    try {
        const res = await fetch('/api/leads');
        const data = await res.json();
        if (data.status === 'success' && data.data) {
            inboundLeads = data.data.filter(l => l.status !== 'converted');
            const lSelect = document.getElementById('leadSelect');
            lSelect.innerHTML = '<option value="">-- Choose Lead --</option>' +
                inboundLeads.map(l => `<option value="${l.id}">${l.name} (${l.mobile}) - ${l.interested_in || 'Inquiry'}</option>`).join('');
        }
    } catch (e) {}
}

function populateFromLead() {
    const leadId = parseInt(document.getElementById('leadSelect').value);
    const lead = inboundLeads.find(l => parseInt(l.id) === leadId);
    if (lead) {
        document.getElementById('admName').value = lead.name || '';
        document.getElementById('admMobile').value = lead.mobile || '';
        document.getElementById('admEmail').value = lead.email || '';
    }
}

function toggleInvoiceOptions() {
    const chk = document.getElementById('chkCreateInvoice').checked;
    document.getElementById('invoiceOptions').classList.toggle('d-none', !chk);
}

function openAdmissionModal() {
    document.getElementById('admissionForm').reset();
    document.getElementById('chkCreateInvoice').checked = true;
    toggleSourceFields();
    toggleInvoiceOptions();
}

async function submitAdmission(e) {
    e.preventDefault();
    const isLead = document.getElementById('srcLead').checked;
    const agreedFee = parseFloat(document.getElementById('admAgreedFee').value) || 0;
    const createInv = document.getElementById('chkCreateInvoice').checked;
    const count = parseInt(document.getElementById('admInstallments').value) || 1;

    let installments = null;
    if (createInv && count > 1 && agreedFee > 0) {
        const perInst = Math.round((agreedFee / count) * 100) / 100;
        installments = [];
        for (let i = 1; i <= count; i++) {
            installments.push({
                installment_no: i,
                amount: perInst,
                due_date: new Date(Date.now() + (i - 1) * 30 * 24 * 3600 * 1000).toISOString().split('T')[0]
            });
        }
    }

    const payload = {
        name: document.getElementById('admName').value,
        mobile: document.getElementById('admMobile').value,
        email: document.getElementById('admEmail').value,
        qualification: document.getElementById('admQual').value,
        course_id: document.getElementById('admCourse').value,
        batch_id: document.getElementById('admBatch').value,
        admission_date: document.getElementById('admDate').value,
        agreed_fee: agreedFee,
        discount_amount: parseFloat(document.getElementById('admDiscount').value) || 0,
        gst_rate_pct: parseFloat(document.getElementById('admGstRate').value) || 0,
        create_invoice: createInv,
        installments: installments
    };

    if (isLead) {
        payload.lead_id = document.getElementById('leadSelect').value;
    }

    try {
        const res = await fetch('/api/admissions', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.status === 'success') {
            const modalEl = document.getElementById('admissionModal');
            bootstrap.Modal.getInstance(modalEl).hide();
            loadStudents();
            alert('Student admitted successfully! Enrollment No: ' + (data.data.enrollment_no || 'Created'));
        } else {
            alert(data.message || 'Admission failed');
        }
    } catch (err) {
        alert('Error: ' + err.message);
    }
}
</script>
