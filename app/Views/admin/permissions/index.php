<div class="row mb-4">
    <div class="col-md-8">
        <h4 class="fw-bold mb-1">Roles & Permissions Matrix</h4>
        <p class="text-secondary small mb-0">Configure granular system capabilities per staff role. Toggles update immediately.</p>
    </div>
    <div class="col-md-4 text-md-end mt-3 mt-md-0">
        <div class="input-group input-group-sm">
            <span class="input-group-text bg-white border-end-0">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            </span>
            <input type="text" id="permSearchInput" class="form-control border-start-0" placeholder="Filter permissions or modules...">
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="matrixTable">
            <thead class="table-light">
                <tr>
                    <th style="min-width: 280px; width: 35%;">Permission / Capability</th>
                    <?php foreach ($roles as $role): ?>
                        <th class="text-center" style="min-width: 130px;">
                            <div class="fw-bold text-dark"><?= e($role['label']) ?></div>
                            <span class="badge bg-secondary-subtle text-secondary font-monospace small"><?= e($role['name']) ?></span>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($categories as $categoryName => $perms): ?>
                    <tr class="table-secondary bg-opacity-50 category-row" data-category="<?= strtolower(e($categoryName)) ?>">
                        <td colspan="<?= count($roles) + 1 ?>" class="fw-semibold text-uppercase text-secondary small py-2 px-3">
                            <span class="badge bg-dark me-2"><?= count($perms) ?></span> <?= e($categoryName) ?>
                        </td>
                    </tr>
                    <?php foreach ($perms as $p): ?>
                        <tr class="perm-row" data-perm-name="<?= strtolower(e($p['label'])) ?>" data-perm-key="<?= strtolower(e($p['name'])) ?>">
                            <td class="ps-4">
                                <div class="fw-medium text-dark"><?= e($p['label']) ?></div>
                                <div class="small text-muted font-monospace"><?= e($p['name']) ?></div>
                            </td>
                            <?php foreach ($roles as $role): ?>
                                <?php
                                    $roleId = (int)$role['id'];
                                    $permId = (int)$p['id'];
                                    $isAdmin = (strtolower($role['name']) === 'admin');
                                    $isChecked = $isAdmin || !empty($matrix[$roleId][$permId]);
                                ?>
                                <td class="text-center">
                                    <div class="form-check form-switch d-inline-block">
                                        <input 
                                            class="form-check-input perm-switch" 
                                            type="checkbox" 
                                            role="switch"
                                            data-role-id="<?= $roleId ?>"
                                            data-perm-id="<?= $permId ?>"
                                            data-role-name="<?= e($role['name']) ?>"
                                            <?= $isChecked ? 'checked' : '' ?>
                                            <?= $isAdmin ? 'disabled title="Administrators retain all permissions"' : '' ?>
                                        >
                                    </div>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 1080;">
    <div id="permToast" class="toast align-items-center text-white bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
        <div class="d-flex">
            <div class="toast-body" id="toastMessage">
                Permission updated successfully.
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const csrfToken = <?= json_encode($csrf_token ?? '') ?>;
    const searchInput = document.getElementById('permSearchInput');
    const toastEl = document.getElementById('permToast');
    const toastMsg = document.getElementById('toastMessage');
    const toast = new bootstrap.Toast(toastEl, { delay: 2500 });

    // Filter functionality
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            const rows = document.querySelectorAll('.perm-row');
            const categoryRows = document.querySelectorAll('.category-row');

            rows.forEach(row => {
                const name = row.getAttribute('data-perm-name') || '';
                const key = row.getAttribute('data-perm-key') || '';
                if (name.includes(query) || key.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            // Hide empty category headers
            categoryRows.forEach(catRow => {
                let next = catRow.nextElementSibling;
                let hasVisible = false;
                while (next && !next.classList.contains('category-row')) {
                    if (next.style.display !== 'none') {
                        hasVisible = true;
                        break;
                    }
                    next = next.nextElementSibling;
                }
                catRow.style.display = hasVisible ? '' : 'none';
            });
        });
    }

    // Toggle switch handler
    document.querySelectorAll('.perm-switch').forEach(sw => {
        sw.addEventListener('change', async (e) => {
            const input = e.target;
            const roleId = parseInt(input.getAttribute('data-role-id'), 10);
            const permId = parseInt(input.getAttribute('data-perm-id'), 10);
            const enabled = input.checked;

            input.disabled = true;

            try {
                const res = await fetch('/api/admin/permissions/toggle', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        role_id: roleId,
                        permission_id: permId,
                        enabled: enabled,
                        _csrf_token: csrfToken
                    })
                });

                const data = await res.json();
                if (!res.ok || data.status === 'error') {
                    throw new Error(data.message || 'Failed to update permission');
                }

                toastEl.classList.remove('bg-danger');
                toastEl.classList.add('bg-success');
                toastMsg.textContent = 'Permission updated successfully.';
                toast.show();
            } catch (err) {
                // Revert switch on error
                input.checked = !enabled;
                toastEl.classList.remove('bg-success');
                toastEl.classList.add('bg-danger');
                toastMsg.textContent = err.message || 'Error updating permission';
                toast.show();
            } finally {
                input.disabled = false;
            }
        });
    });
});
</script>
