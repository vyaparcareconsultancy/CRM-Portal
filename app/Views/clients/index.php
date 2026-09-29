<div class="row">
    <div class="col-12">
        <!-- Page Header -->
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
            <div>
                <h4 class="fw-bold mb-1">Clients Directory</h4>
                <p class="text-muted mb-0">Browse, filter, search, and manage registered client accounts.</p>
            </div>
            <div class="d-flex align-items-center gap-2">
                <?php if (can('client.export')): ?>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle d-flex align-items-center gap-2" type="button" id="exportDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false">
                        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>Export</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="exportDropdownBtn">
                        <li><a class="dropdown-item d-flex align-items-center gap-2" href="#" id="exportXlsxBtn">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            <span>Excel Spreadsheet (.xlsx)</span>
                        </a></li>
                        <li><a class="dropdown-item d-flex align-items-center gap-2" href="#" id="exportCsvBtn">
                            <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                            <span>CSV Document (.csv)</span>
                        </a></li>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if (can('client.create')): ?>
                <a href="/clients/create" class="btn btn-primary d-flex align-items-center gap-2">
                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                    <span>Register Client</span>
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-3 p-md-4">
                <form id="clientFilterForm" class="row g-3">
                    <div class="col-md-3">
                        <label for="filterStatus" class="form-label small fw-semibold">Status</label>
                        <select class="form-select form-select-sm" id="filterStatus" name="status">
                            <option value="">All Statuses</option>
                            <option value="new">New</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="filterState" class="form-label small fw-semibold">State</label>
                        <select class="form-select form-select-sm" id="filterState" name="state">
                            <option value="">All States</option>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="filterCity" class="form-label small fw-semibold">City</label>
                        <input type="text" class="form-control form-control-sm" id="filterCity" name="city" placeholder="Filter by city">
                    </div>

                    <div class="col-md-3">
                        <label for="filterLeadSource" class="form-label small fw-semibold">Lead Source</label>
                        <select class="form-select form-select-sm" id="filterLeadSource" name="lead_source">
                            <option value="">All Lead Sources</option>
                        </select>
                    </div>

                    <?php if (can('client.view_all')): ?>
                    <div class="col-md-3">
                        <label for="filterAssignedTo" class="form-label small fw-semibold">Assigned Staff</label>
                        <select class="form-select form-select-sm" id="filterAssignedTo" name="assigned_to">
                            <option value="">All Staff Reps</option>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="col-md-3">
                        <label for="filterDateFrom" class="form-label small fw-semibold">Created From</label>
                        <input type="date" class="form-control form-control-sm" id="filterDateFrom" name="date_from">
                    </div>

                    <div class="col-md-3">
                        <label for="filterDateTo" class="form-label small fw-semibold">Created To</label>
                        <input type="date" class="form-control form-control-sm" id="filterDateTo" name="date_to">
                    </div>

                    <div class="col-md-<?= can('client.view_all') ? '3' : '6' ?> d-flex align-items-end gap-2">
                        <button type="submit" class="btn btn-primary btn-sm px-3 flex-grow-1 d-flex align-items-center justify-content-center gap-1">
                            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                            <span>Apply Filters</span>
                        </button>
                        <button type="button" class="btn btn-light btn-sm border px-3" id="resetFiltersBtn">Reset</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Clients DataTables Card -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-0 p-md-3">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 w-100" id="clientsDataTable">
                        <thead class="table-light">
                            <tr>
                                <th>Code</th>
                                <th>Client / Company</th>
                                <th>Contact</th>
                                <th>Location</th>
                                <th>Assigned Rep</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteClientModal" tabindex="-1" aria-labelledby="deleteClientModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title text-danger fw-bold" id="deleteClientModalLabel">Confirm Client Deletion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <p class="mb-1">Are you sure you want to delete client <strong id="deleteClientCode"></strong> (<span id="deleteClientName"></span>)?</p>
                <p class="text-muted small mb-0">This record will be soft-deleted and removed from the active directory.</p>
            </div>
            <div class="modal-footer border-top-0 pt-0">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeleteClientBtn">Delete Client</button>
            </div>
        </div>
    </div>
</div>

