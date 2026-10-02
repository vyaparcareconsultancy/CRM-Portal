<div class="row mb-4 align-items-center">
    <div class="col-md-7">
        <h4 class="fw-bold mb-1">Services Master Catalog</h4>
        <p class="text-secondary small mb-0">Manage standardized tax, compliance, and consultancy service offerings and standard fees.</p>
    </div>
    <div class="col-md-5 text-md-end mt-3 mt-md-0 d-flex gap-2 justify-content-md-end">
        <input type="text" id="serviceSearchInput" class="form-control form-control-sm w-auto" placeholder="Filter services...">
        <?php if (can('service.manage')): ?>
        <button type="button" class="btn btn-sm btn-primary d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#createServiceModal">
            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
            <span>Add Service</span>
        </button>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="servicesMasterTable">
            <thead class="table-light">
                <tr>
                    <th style="min-width: 140px;">Service Code</th>
                    <th style="min-width: 220px;">Service Name</th>
                    <th>Category</th>
                    <th>Type</th>
                    <th>Frequency</th>
                    <th class="text-end">Default Fee (₹)</th>
                    <th class="text-center">Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody id="servicesTableBody">
                <?php if (empty($services)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-4 text-muted">No services found in catalog.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($services as $srv): ?>
                        <tr class="service-row" data-name="<?= strtolower(e($srv['name'])) ?>" data-code="<?= strtolower(e($srv['code'])) ?>" data-category="<?= strtolower(e($srv['category'] ?? '')) ?>">
                            <td class="font-monospace fw-semibold text-secondary small">
                                <?= e($srv['code']) ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= e($srv['name']) ?></div>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark border text-uppercase small"><?= e($srv['category'] ?? 'other') ?></span>
                            </td>
                            <td>
                                <?php if (($srv['type'] ?? '') === 'one_time'): ?>
                                    <span class="badge bg-info-subtle text-info border border-info-subtle">One-Time</span>
                                <?php else: ?>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle">Recurring</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($srv['frequency'])): ?>
                                    <span class="badge bg-secondary-subtle text-secondary border"><?= ucfirst(e($srv['frequency'])) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end font-monospace fw-bold text-dark">
                                ₹<?= number_format((float)($srv['default_fee'] ?? 0), 2) ?>
                            </td>
                            <td class="text-center">
                                <?php if (!empty($srv['is_active'])): ?>
                                    <span class="badge bg-success-subtle text-success border border-success-subtle">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary text-white">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-secondary edit-service-btn" 
                                    data-id="<?= (int)$srv['id'] ?>"
                                    data-code="<?= e($srv['code']) ?>"
                                    data-name="<?= e($srv['name']) ?>"
                                    data-type="<?= e($srv['type'] ?? 'recurring') ?>"
                                    data-frequency="<?= e($srv['frequency'] ?? '') ?>"
                                    data-default-fee="<?= (float)($srv['default_fee'] ?? 0) ?>"
                                    data-category="<?= e($srv['category'] ?? 'other') ?>"
                                    data-is-active="<?= !empty($srv['is_active']) ? '1' : '0' ?>"
                                    title="Edit Service">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger delete-service-btn" 
                                    data-id="<?= (int)$srv['id'] ?>"
                                    data-name="<?= e($srv['name']) ?>"
                                    title="Delete Service">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal: Create Service -->
<div class="modal fade" id="createServiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="createServiceForm">
            <input type="hidden" name="_csrf_token" value="<?= e($csrf_token ?? '') ?>">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Add New Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Service Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. GST Annual Return, Trademark Filing" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Service Code (optional)</label>
                        <input type="text" name="code" class="form-control font-monospace" placeholder="e.g. SRV-GST-ANN">
                        <span class="text-muted small">Auto-generated if left blank</span>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Category</label>
                        <select name="category" class="form-select">
                            <option value="gst">GST</option>
                            <option value="itr">ITR</option>
                            <option value="accounting">Accounting / Bookkeeping</option>
                            <option value="audit">Audit</option>
                            <option value="registration">Business Registration</option>
                            <option value="roc">ROC</option>
                            <option value="tds">TDS</option>
                            <option value="pf_esi">PF/ESI</option>
                            <option value="other" selected>Other</option>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Type <span class="text-danger">*</span></label>
                        <select name="type" id="createServiceType" class="form-select" required>
                            <option value="recurring" selected>Recurring</option>
                            <option value="one_time">One-Time</option>
                        </select>
                    </div>
                    <div class="col-md-6" id="createServiceFreqWrapper">
                        <label class="form-label fw-semibold small">Frequency <span class="text-danger">*</span></label>
                        <select name="frequency" id="createServiceFrequency" class="form-select">
                            <option value="monthly" selected>Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Default Fee (₹) <span class="text-danger">*</span></label>
                    <input type="number" name="default_fee" step="0.01" min="0" class="form-control font-monospace" placeholder="2500.00" required>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="createServiceActive" checked>
                    <label class="form-check-label small fw-semibold" for="createServiceActive">Active for Client Subscriptions</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="saveServiceBtn">Save Service</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Service -->
<div class="modal fade" id="editServiceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form class="modal-content" id="editServiceForm">
            <input type="hidden" name="_csrf_token" value="<?= e($csrf_token ?? '') ?>">
            <input type="hidden" name="id" id="editServiceId">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Edit Service</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Service Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="editServiceName" class="form-control" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Service Code</label>
                        <input type="text" id="editServiceCode" class="form-control font-monospace" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Category</label>
                        <select name="category" id="editServiceCategory" class="form-select">
                            <option value="gst">GST</option>
                            <option value="itr">ITR</option>
                            <option value="accounting">Accounting / Bookkeeping</option>
                            <option value="audit">Audit</option>
                            <option value="registration">Business Registration</option>
                            <option value="roc">ROC</option>
                            <option value="tds">TDS</option>
                            <option value="pf_esi">PF/ESI</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold small">Type <span class="text-danger">*</span></label>
                        <select name="type" id="editServiceType" class="form-select" required>
                            <option value="recurring">Recurring</option>
                            <option value="one_time">One-Time</option>
                        </select>
                    </div>
                    <div class="col-md-6" id="editServiceFreqWrapper">
                        <label class="form-label fw-semibold small">Frequency</label>
                        <select name="frequency" id="editServiceFrequency" class="form-select">
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold small">Default Fee (₹) <span class="text-danger">*</span></label>
                    <input type="number" name="default_fee" id="editServiceDefaultFee" step="0.01" min="0" class="form-control font-monospace" required>
                </div>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="editServiceActive">
                    <label class="form-check-label small fw-semibold" for="editServiceActive">Active</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="updateServiceBtn">Update Service</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = '<?= e($csrf_token ?? '') ?>';

    // Type toggling for create modal
    const createType = document.getElementById('createServiceType');
    const createFreqWrapper = document.getElementById('createServiceFreqWrapper');
    createType.addEventListener('change', () => {
        createFreqWrapper.style.display = createType.value === 'one_time' ? 'none' : 'block';
    });

    // Type toggling for edit modal
    const editType = document.getElementById('editServiceType');
    const editFreqWrapper = document.getElementById('editFreqWrapper');
    editType.addEventListener('change', () => {
        editFreqWrapper.style.display = editType.value === 'one_time' ? 'none' : 'block';
    });

    // Search filter
    const searchInput = document.getElementById('serviceSearchInput');
    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase().trim();
        document.querySelectorAll('.service-row').forEach(row => {
            const name = row.dataset.name || '';
            const code = row.dataset.code || '';
            const cat = row.dataset.category || '';
            const match = name.includes(query) || code.includes(query) || cat.includes(query);
            row.style.display = match ? '' : 'none';
        });
    });

    // Add Service Submit
    const createForm = document.getElementById('createServiceForm');
    createForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const submitBtn = document.getElementById('saveServiceBtn');
        submitBtn.disabled = true;

        const formData = new FormData(createForm);
        const payload = Object.fromEntries(formData.entries());
        payload.is_active = document.getElementById('createServiceActive').checked ? 1 : 0;

        try {
            const res = await fetch('/api/services', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (res.ok && data.status === 'success') {
                location.reload();
            } else {
                alert(data.message || 'Error creating service.');
            }
        } catch (err) {
            alert('Network error creating service.');
        } finally {
            submitBtn.disabled = false;
        }
    });

    // Open Edit Modal
    const editModal = new bootstrap.Modal(document.getElementById('editServiceModal'));
    document.querySelectorAll('.edit-service-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('editServiceId').value = btn.dataset.id;
            document.getElementById('editServiceCode').value = btn.dataset.code;
            document.getElementById('editServiceName').value = btn.dataset.name;
            document.getElementById('editServiceCategory').value = btn.dataset.category;
            document.getElementById('editServiceType').value = btn.dataset.type;
            document.getElementById('editServiceDefaultFee').value = btn.dataset.defaultFee;
            document.getElementById('editServiceActive').checked = btn.dataset.isActive === '1';

            if (btn.dataset.frequency) {
                document.getElementById('editServiceFrequency').value = btn.dataset.frequency;
            }
            editFreqWrapper.style.display = btn.dataset.type === 'one_time' ? 'none' : 'block';

            editModal.show();
        });
    });

    // Update Service Submit
    const editForm = document.getElementById('editServiceForm');
    editForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const id = document.getElementById('editServiceId').value;
        const submitBtn = document.getElementById('updateServiceBtn');
        submitBtn.disabled = true;

        const formData = new FormData(editForm);
        const payload = Object.fromEntries(formData.entries());
        payload.is_active = document.getElementById('editServiceActive').checked ? 1 : 0;

        try {
            const res = await fetch(`/api/services/${id}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (res.ok && data.status === 'success') {
                location.reload();
            } else {
                alert(data.message || 'Error updating service.');
            }
        } catch (err) {
            alert('Network error updating service.');
        } finally {
            submitBtn.disabled = false;
        }
    });

    // Delete Service
    document.querySelectorAll('.delete-service-btn').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = btn.dataset.id;
            const name = btn.dataset.name;
            if (!confirm(`Are you sure you want to remove "${name}" from the services catalog?`)) {
                return;
            }

            try {
                const res = await fetch(`/api/services/${id}/delete`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    }
                });
                const data = await res.json();
                if (res.ok && data.status === 'success') {
                    location.reload();
                } else {
                    alert(data.message || 'Error deleting service.');
                }
            } catch (err) {
                alert('Network error deleting service.');
            }
        });
    });
});
</script>
