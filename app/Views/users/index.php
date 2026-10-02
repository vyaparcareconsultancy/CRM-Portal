<?php
$rolesList = $roles ?? [];
$currentUid = (int)($currentUserId ?? \App\Core\Session::get('user_id') ?? 0);
?>
<div class="row" id="usersApp" data-current-user-id="<?= $currentUid ?>">
    <div class="col-12">
        <!-- Page Header -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h4 class="fw-bold mb-1">User Administration</h4>
                <p class="text-muted mb-0">Manage staff access, roles, account statuses, and passwords.</p>
            </div>
            <div>
                <button type="button" class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#createUserModal" id="openCreateUserBtn">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                    <span>Add User</span>
                </button>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-3 p-md-4">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label for="userSearchInput" class="form-label small fw-semibold">Search</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text bg-light border-end-0">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </span>
                            <input type="text" class="form-control border-start-0" id="userSearchInput" placeholder="Filter by name or email...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label for="filterRole" class="form-label small fw-semibold">Role</label>
                        <select class="form-select form-select-sm" id="filterRole">
                            <option value="">All Roles</option>
                            <?php foreach ($rolesList as $r): ?>
                            <option value="<?= e($r['name']) ?>"><?= e($r['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="filterStatus" class="form-label small fw-semibold">Status</label>
                        <select class="form-select form-select-sm" id="filterStatus">
                            <option value="">All Statuses</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <div class="col-md-1 d-flex align-items-end">
                        <button type="button" class="btn btn-light btn-sm border w-100" id="resetFiltersBtn" title="Reset Filters">Reset</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Users Table Card -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-0 p-md-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 w-100" id="usersTable">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">
                                    <span class="spinner-border spinner-border-sm me-2" role="status"></span>Loading users...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Create User -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="createUserForm" novalidate>
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold" id="createUserModalLabel">Create New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label for="createName" class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="createName" name="name" required placeholder="e.g. Jane Doe">
                        <div class="invalid-feedback" id="createNameError"></div>
                    </div>

                    <div class="mb-3">
                        <label for="createEmail" class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control form-control-sm" id="createEmail" name="email" required placeholder="e.g. jane@company.com">
                        <div class="invalid-feedback" id="createEmailError"></div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label for="createRole" class="form-label small fw-semibold">Role <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="createRole" name="role_id" required>
                                <option value="">Select Role</option>
                                <?php foreach ($rolesList as $r): ?>
                                <option value="<?= (int)$r['id'] ?>"><?= e($r['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback" id="createRoleError"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="createMobile" class="form-label small fw-semibold">Mobile (Optional)</label>
                            <input type="text" class="form-control form-control-sm" id="createMobile" name="mobile" placeholder="10-digit number">
                            <div class="invalid-feedback" id="createMobileError"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="createPassword" class="form-label small fw-semibold">Initial Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control form-control-sm" id="createPassword" name="password" required placeholder="Min 8 characters, letters & numbers">
                        <div class="form-text small">At least 8 characters containing both letters and numbers.</div>
                        <div class="invalid-feedback" id="createPasswordError"></div>
                    </div>

                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="createIsActive" name="is_active" value="1" checked>
                        <label class="form-check-label small" for="createIsActive">Active Account (Allowed to sign in)</label>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light border btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3" id="saveCreateUserBtn">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Edit User -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="editUserForm" novalidate>
                <input type="hidden" id="editUserId" name="id">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold" id="editUserModalLabel">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <div class="mb-3">
                        <label for="editName" class="form-label small fw-semibold">Full Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="editName" name="name" required>
                        <div class="invalid-feedback" id="editNameError"></div>
                    </div>

                    <div class="mb-3">
                        <label for="editEmail" class="form-label small fw-semibold">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control form-control-sm" id="editEmail" name="email" required>
                        <div class="invalid-feedback" id="editEmailError"></div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label for="editRole" class="form-label small fw-semibold">Role <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="editRole" name="role_id" required>
                                <?php foreach ($rolesList as $r): ?>
                                <option value="<?= (int)$r['id'] ?>"><?= e($r['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="invalid-feedback" id="editRoleError"></div>
                        </div>
                        <div class="col-md-6">
                            <label for="editMobile" class="form-label small fw-semibold">Mobile</label>
                            <input type="text" class="form-control form-control-sm" id="editMobile" name="mobile">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="editStatus" class="form-label small fw-semibold">Status <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="editStatus" name="is_active">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                        <div id="selfDeactivateNote" class="form-text text-muted small d-none">
                            You cannot deactivate your own account.
                        </div>
                        <div class="invalid-feedback" id="editStatusError"></div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light border btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm px-3" id="saveEditUserBtn">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Reset Password -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1" aria-labelledby="resetPasswordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="resetPasswordForm" novalidate>
                <input type="hidden" id="resetPasswordUserId" name="id">
                <div class="modal-header border-bottom-0 pb-0">
                    <h5 class="modal-title fw-bold" id="resetPasswordModalLabel">Reset User Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body py-3">
                    <p class="text-muted small mb-3">
                        Setting a new password for <strong id="resetPasswordUserName"></strong> (<span id="resetPasswordUserEmail"></span>).
                    </p>
                    <div class="mb-3">
                        <label for="newPasswordInput" class="form-label small fw-semibold">New Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control form-control-sm" id="newPasswordInput" name="password" required placeholder="Min 8 characters, letters & numbers">
                        <div class="form-text small">Must be at least 8 characters and include letters and numbers.</div>
                        <div class="invalid-feedback" id="newPasswordError"></div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light border btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning btn-sm px-3" id="submitResetPasswordBtn">Reset Password</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Delete User -->
<div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title text-danger fw-bold" id="deleteUserModalLabel">Confirm User Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <p class="mb-1">Are you sure you want to delete user <strong id="deleteUserName"></strong> (<span id="deleteUserEmail"></span>)?</p>
                <p class="text-muted small mb-0">This user account will be soft-deleted. The system must always keep at least one active admin.</p>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-light border btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger btn-sm px-3" id="confirmDeleteUserBtn">Delete User</button>
            </div>
        </div>
    </div>
</div>
