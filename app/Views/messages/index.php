<?php
/**
 * Omnichannel Messaging Center View (Phase 9)
 * Provider statuses, template management, delivery audit logs, opt-outs, and broadcast queue.
 */
?>
<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-2">
        <div>
            <h1 class="h3 mb-1 fw-bold text-dark">Omnichannel Messaging Hub</h1>
            <p class="text-muted small mb-0">Email (PHPMailer SMTP), WhatsApp (Meta Cloud API), and Indian DLT SMS gateways.</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#quickSendModal">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/></svg>
                <span>Quick Send</span>
            </button>
            <button class="btn btn-primary btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#broadcastModal">
                <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"/></svg>
                <span>Bulk Broadcast</span>
            </button>
        </div>
    </div>

    <!-- Alert Container -->
    <div id="alertPlaceholder"></div>

    <!-- Gateway Provider Status Cards -->
    <div class="row g-3 mb-4">
        <?php foreach ($providerStatuses as $ch => $prov): ?>
            <?php
                $isConfigured = !empty($prov['is_configured']);
                $badgeClass = $isConfigured ? 'bg-success text-white' : 'bg-warning text-dark';
                $badgeText = $isConfigured ? 'ACTIVE' : 'DISABLED';
                $cardBorder = $isConfigured ? 'border-success-subtle' : 'border-warning-subtle';
            ?>
            <div class="col-md-4">
                <div class="card h-100 shadow-sm border <?= $cardBorder ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h6 class="card-title fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                                <?php if ($ch === 'email'): ?>
                                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                                <?php elseif ($ch === 'whatsapp'): ?>
                                    <svg width="18" height="18" fill="currentColor" viewBox="0 0 24 24"><path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.582 2.128 2.182-.573c.978.58 1.911.928 3.145.929 3.178 0 5.767-2.587 5.768-5.766.001-3.187-2.575-5.77-5.764-5.771zm3.392 8.244c-.144.405-.837.774-1.17.824-.312.045-.698.074-1.118-.063-.264-.087-.604-.216-1.042-.405-1.85-.801-3.056-2.673-3.149-2.796-.092-.123-.748-.997-.748-1.901 0-.903.473-1.348.642-1.53.169-.182.37-.227.493-.227.123 0 .247.001.353.007.112.006.262-.042.41.312.152.365.518 1.264.563 1.356.045.093.076.201.015.323-.061.123-.092.199-.183.307-.092.107-.194.24-.277.323-.093.093-.19.194-.082.38.108.185.48 1.015 1.031 1.505.71.633 1.309.828 1.494.92.185.093.293.078.401-.047.108-.124.462-.538.585-.722.123-.185.246-.154.41-.093.164.062 1.043.492 1.222.582.179.09.298.136.342.213.045.076.045.444-.099.849z"/></svg>
                                <?php else: ?>
                                    <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"/></svg>
                                <?php endif; ?>
                                <span><?= htmlspecialchars($prov['name']) ?></span>
                            </h6>
                            <span class="badge <?= $badgeClass ?> small"><?= $badgeText ?></span>
                        </div>
                        <p class="card-text text-secondary small mb-2">
                            <?= htmlspecialchars($prov['status']) ?>
                        </p>
                        <div class="text-muted" style="font-size: 11px;">
                            <span class="text-secondary fw-semibold">Rule:</span> Credentials configured exclusively via <code>.env</code>.
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Navigation Tabs -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white border-bottom-0 pb-0">
            <ul class="nav nav-tabs card-header-tabs" id="msgTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active fw-semibold" id="tab-logs-btn" data-bs-toggle="tab" data-bs-target="#tab-logs" type="button" role="tab">
                        Message Delivery Logs
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-semibold" id="tab-templates-btn" data-bs-toggle="tab" data-bs-target="#tab-templates" type="button" role="tab">
                        Message Templates
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-semibold" id="tab-optouts-btn" data-bs-toggle="tab" data-bs-target="#tab-optouts" type="button" role="tab">
                        Channel Opt-Outs (Consent)
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="msgTabsContent">
                
                <!-- TAB 1: MESSAGE LOGS -->
                <div class="tab-pane fade show active" id="tab-logs" role="tabpanel">
                    <div class="row g-2 mb-3 align-items-center">
                        <div class="col-md-3">
                            <input type="text" class="form-control form-control-sm" id="logSearch" placeholder="Search recipient, subject, body...">
                        </div>
                        <div class="col-md-2">
                            <select class="form-select form-select-sm" id="logChannelFilter">
                                <option value="">All Channels</option>
                                <option value="email">Email</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="sms">SMS</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select form-select-sm" id="logStatusFilter">
                                <option value="">All Statuses</option>
                                <option value="sent">Sent</option>
                                <option value="queued">Queued</option>
                                <option value="failed">Failed</option>
                                <option value="opted_out">Opted Out</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-secondary btn-sm w-100" id="refreshLogsBtn">Refresh Logs</button>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="logsTable">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 130px;">Time</th>
                                    <th>Channel</th>
                                    <th>Recipient</th>
                                    <th>Template / Subject</th>
                                    <th>Status</th>
                                    <th>Gateway Ref / Error</th>
                                    <th>Sender</th>
                                </tr>
                            </thead>
                            <tbody id="logsTableBody">
                                <?php if (empty($recentLogs)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">No message delivery records found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recentLogs as $log): ?>
                                        <?php
                                            $st = $log['status'];
                                            $badgeClass = match($st) {
                                                'sent' => 'bg-success',
                                                'queued' => 'bg-info text-dark',
                                                'failed' => 'bg-danger',
                                                'opted_out' => 'bg-secondary',
                                                default => 'bg-light text-dark'
                                            };
                                        ?>
                                        <tr>
                                            <td class="small text-muted"><?= htmlspecialchars(substr($log['created_at'], 0, 16)) ?></td>
                                            <td>
                                                <span class="badge bg-light text-dark border text-uppercase" style="font-size: 11px;">
                                                    <?= htmlspecialchars($log['channel']) ?>
                                                </span>
                                            </td>
                                            <td class="fw-semibold small"><?= htmlspecialchars($log['recipient']) ?></td>
                                            <td>
                                                <div class="small fw-bold text-dark"><?= htmlspecialchars($log['subject'] ?: ($log['template_name'] ?? 'Direct Message')) ?></div>
                                                <div class="text-muted text-truncate" style="max-width: 280px; font-size: 11px;">
                                                    <?= htmlspecialchars(strip_tags($log['message_body'])) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge <?= $badgeClass ?> small"><?= strtoupper($st) ?></span>
                                            </td>
                                            <td class="small">
                                                <?php if (!empty($log['gateway_message_id'])): ?>
                                                    <code class="text-muted"><?= htmlspecialchars($log['gateway_message_id']) ?></code>
                                                <?php elseif (!empty($log['error_message'])): ?>
                                                    <span class="text-danger small" title="<?= htmlspecialchars($log['error_message']) ?>">
                                                        <?= htmlspecialchars(substr($log['error_message'], 0, 40)) ?>...
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small text-muted"><?= htmlspecialchars($log['sender_name'] ?? 'System') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 2: MESSAGE TEMPLATES -->
                <div class="tab-pane fade" id="tab-templates" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="text-muted small">
                            Templates support variable interpolation: <code>{name}</code>, <code>{course}</code>, <code>{amount}</code>, <code>{due_date}</code>, <code>{receipt_no}</code>, <code>{business_name}</code>.
                        </div>
                        <?php if ($canManageTemplates): ?>
                            <button class="btn btn-success btn-sm d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#templateModal" onclick="prepareCreateTemplate()">
                                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 4v16m8-8H4"/></svg>
                                <span>Create Template</span>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Channel</th>
                                    <th>Subject / Header</th>
                                    <th>Template Body</th>
                                    <th>Status</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($templates as $tpl): ?>
                                    <tr>
                                        <td><code><?= htmlspecialchars($tpl['code']) ?></code></td>
                                        <td class="fw-semibold text-dark"><?= htmlspecialchars($tpl['name']) ?></td>
                                        <td>
                                            <span class="badge bg-secondary-subtle text-secondary border text-uppercase" style="font-size: 11px;">
                                                <?= htmlspecialchars($tpl['channel']) ?>
                                            </span>
                                        </td>
                                        <td class="small text-muted"><?= htmlspecialchars($tpl['subject'] ?? '—') ?></td>
                                        <td class="small" style="max-width: 300px;">
                                            <div class="text-truncate text-secondary" title="<?= htmlspecialchars($tpl['body_template']) ?>">
                                                <?= htmlspecialchars($tpl['body_template']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?= !empty($tpl['is_active']) ? 'bg-success' : 'bg-secondary' ?> small">
                                                <?= !empty($tpl['is_active']) ? 'ACTIVE' : 'INACTIVE' ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick='testSendTemplate(<?= json_encode($tpl) ?>)' title="Test Send">
                                                    Send
                                                </button>
                                                <?php if ($canManageTemplates): ?>
                                                    <button class="btn btn-outline-secondary" onclick='editTemplate(<?= json_encode($tpl) ?>)' title="Edit">
                                                        Edit
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TAB 3: CHANNEL OPT-OUTS -->
                <div class="tab-pane fade" id="tab-optouts" role="tabpanel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="text-muted small">
                            <strong>Compliance Rule:</strong> Contacts listed here have opted out of messaging. The dispatch system will <strong>never</strong> message them on opted-out channels.
                        </div>
                        <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#optOutModal">
                            + Add Opt-Out
                        </button>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Identifier (Email / Phone)</th>
                                    <th>Channel</th>
                                    <th>Entity</th>
                                    <th>Reason</th>
                                    <th>Opted-Out At</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($optOuts)): ?>
                                    <tr><td colspan="6" class="text-center text-muted py-4">No opt-out records. All contacts currently opted-in.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($optOuts as $opt): ?>
                                        <tr>
                                            <td class="fw-bold small"><?= htmlspecialchars($opt['identifier']) ?></td>
                                            <td>
                                                <span class="badge bg-warning text-dark text-uppercase" style="font-size: 11px;">
                                                    <?= htmlspecialchars($opt['channel']) ?>
                                                </span>
                                            </td>
                                            <td class="small text-muted"><?= htmlspecialchars(ucfirst($opt['entity_type'])) ?></td>
                                            <td class="small text-secondary"><?= htmlspecialchars($opt['reason'] ?? 'User requested') ?></td>
                                            <td class="small text-muted"><?= htmlspecialchars(substr($opt['opted_out_at'], 0, 16)) ?></td>
                                            <td class="text-end">
                                                <button class="btn btn-outline-success btn-sm" onclick="optInContact('<?= htmlspecialchars($opt['channel']) ?>', '<?= htmlspecialchars($opt['identifier']) ?>')">
                                                    Opt In
                                                </button>
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
    </div>
</div>

<!-- Modal: Quick Send -->
<div class="modal fade" id="quickSendModal" tabindex="-1" aria-labelledby="quickSendModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="quickSendModalLabel">Quick Send Message</h5>
                <button type="button" class="btn-close" data-bs-toggle="modal" aria-label="Close"></button>
            </div>
            <form id="quickSendForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Channel</label>
                        <select class="form-select" id="sendChannel" required>
                            <option value="email">Email (SMTP)</option>
                            <option value="whatsapp">WhatsApp (Meta Cloud API)</option>
                            <option value="sms">SMS (MSG91 DLT Gateway)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Recipient (Email or Phone)</label>
                        <input type="text" class="form-control" id="sendTo" placeholder="e.g. user@example.com or 9876543210" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Template (Optional)</label>
                        <select class="form-select" id="sendTemplateId" onchange="onTemplateSelected(this.value)">
                            <option value="">-- Custom Message (No Template) --</option>
                            <?php foreach ($templates as $t): ?>
                                <option value="<?= $t['id'] ?>" data-subject="<?= htmlspecialchars($t['subject'] ?? '') ?>" data-body="<?= htmlspecialchars($t['body_template']) ?>">
                                    <?= htmlspecialchars($t['name']) ?> (<?= strtoupper($t['channel']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3" id="sendSubjectGroup">
                        <label class="form-label fw-semibold small">Subject</label>
                        <input type="text" class="form-control" id="sendSubject" placeholder="Email subject">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Message Body</label>
                        <textarea class="form-control" id="sendMessage" rows="4" placeholder="Enter message text... Available variables: {name}, {course}, {amount}, {due_date}, {receipt_no}"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnSendSubmit">Send Now</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Bulk Broadcast -->
<div class="modal fade" id="broadcastModal" tabindex="-1" aria-labelledby="broadcastModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="broadcastModalLabel">Bulk Omnichannel Broadcast</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="broadcastForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Broadcast Channel</label>
                        <select class="form-select" id="broadcastChannel" required>
                            <option value="email">Email</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="sms">SMS</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Target Audience Group</label>
                        <select class="form-select" id="broadcastTargetGroup" required>
                            <option value="clients">All Active Clients</option>
                            <option value="students">All Enrolled Students</option>
                            <option value="leads">All Active Leads</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Subject (for Email)</label>
                        <input type="text" class="form-control" id="broadcastSubject" placeholder="Broadcast Subject">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Broadcast Message</label>
                        <textarea class="form-control" id="broadcastMessage" rows="4" placeholder="Hello {name}, ..." required></textarea>
                        <div class="form-text small">Each message will be queued with rate limiting via the cron worker.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Rate Limit (Messages per minute)</label>
                        <input type="number" class="form-control" id="broadcastRateLimit" value="60" min="1" max="300">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnBroadcastSubmit">Queue Broadcast</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Template Editor -->
<div class="modal fade" id="templateModal" tabindex="-1" aria-labelledby="templateModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="templateModalLabel">Message Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="templateForm">
                <input type="hidden" id="tplId" value="">
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label fw-semibold small">Template Code (Unique identifier)</label>
                        <input type="text" class="form-control" id="tplCode" placeholder="e.g. TPL_PAYMENT_ALERT" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold small">Template Name</label>
                        <input type="text" class="form-control" id="tplName" placeholder="e.g. Payment Alert" required>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold small">Channel</label>
                        <select class="form-select" id="tplChannel" required>
                            <option value="all">All Channels</option>
                            <option value="email">Email</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="sms">SMS</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold small">Email Subject (Optional)</label>
                        <input type="text" class="form-control" id="tplSubject" placeholder="Email subject">
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold small">Body Template</label>
                        <textarea class="form-control" id="tplBody" rows="4" placeholder="Dear {name}, your amount of INR {amount} is due..." required></textarea>
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold small">DLT Template ID (SMS)</label>
                        <input type="text" class="form-control" id="tplDltTeId" placeholder="Govt DLT Approved Template ID">
                    </div>
                    <div class="mb-2">
                        <label class="form-label fw-semibold small">WhatsApp Template Name (Meta)</label>
                        <input type="text" class="form-control" id="tplWaName" placeholder="Approved Meta template name">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success btn-sm">Save Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Add Opt-Out -->
<div class="modal fade" id="optOutModal" tabindex="-1" aria-labelledby="optOutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="optOutModalLabel">Add Contact Opt-Out</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="optOutForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Identifier (Email or Mobile)</label>
                        <input type="text" class="form-control" id="optIdentifier" placeholder="e.g. user@example.com or 9876543210" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Channel to Opt-Out</label>
                        <select class="form-select" id="optChannel" required>
                            <option value="all">All Channels</option>
                            <option value="email">Email</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="sms">SMS</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold small">Reason</label>
                        <input type="text" class="form-control" id="optReason" placeholder="e.g. User unsubscribed">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger btn-sm">Confirm Opt-Out</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showAlert(message, type = 'success') {
    const el = document.getElementById('alertPlaceholder');
    el.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;
    setTimeout(() => {
        const al = el.querySelector('.alert');
        if (al) al.remove();
    }, 6000);
}

function onTemplateSelected(val) {
    const opt = document.querySelector(`#sendTemplateId option[value="${val}"]`);
    if (opt && val) {
        document.getElementById('sendSubject').value = opt.getAttribute('data-subject') || '';
        document.getElementById('sendMessage').value = opt.getAttribute('data-body') || '';
    }
}

// Quick Send handler
document.getElementById('quickSendForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSendSubmit');
    btn.disabled = true;
    btn.innerText = 'Sending...';

    const payload = {
        channel: document.getElementById('sendChannel').value,
        to: document.getElementById('sendTo').value,
        subject: document.getElementById('sendSubject').value,
        message: document.getElementById('sendMessage').value,
        template_id: document.getElementById('sendTemplateId').value || null
    };

    try {
        const res = await fetch('/api/messages/send', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            showAlert('Message dispatched successfully!', 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('quickSendModal'));
            if (modal) modal.hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.error || 'Failed to send message.', 'danger');
        }
    } catch (err) {
        showAlert(err.message, 'danger');
    } finally {
        btn.disabled = false;
        btn.innerText = 'Send Now';
    }
});

// Bulk Broadcast handler
document.getElementById('broadcastForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnBroadcastSubmit');
    btn.disabled = true;
    btn.innerText = 'Queueing...';

    const payload = {
        channel: document.getElementById('broadcastChannel').value,
        target_group: document.getElementById('broadcastTargetGroup').value,
        subject: document.getElementById('broadcastSubject').value,
        message: document.getElementById('broadcastMessage').value,
        rate_limit_per_minute: parseInt(document.getElementById('broadcastRateLimit').value, 10) || 60
    };

    try {
        const res = await fetch('/api/messages/broadcast', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            showAlert(`Broadcast queued: ${data.queued_count} recipients queued, ${data.opted_out_count} opted-out skipped.`, 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('broadcastModal'));
            if (modal) modal.hide();
            setTimeout(() => location.reload(), 1200);
        } else {
            showAlert(data.error || 'Broadcast failed.', 'danger');
        }
    } catch (err) {
        showAlert(err.message, 'danger');
    } finally {
        btn.disabled = false;
        btn.innerText = 'Queue Broadcast';
    }
});

// Template form
function prepareCreateTemplate() {
    document.getElementById('tplId').value = '';
    document.getElementById('tplCode').value = '';
    document.getElementById('tplCode').disabled = false;
    document.getElementById('tplName').value = '';
    document.getElementById('tplChannel').value = 'all';
    document.getElementById('tplSubject').value = '';
    document.getElementById('tplBody').value = '';
    document.getElementById('tplDltTeId').value = '';
    document.getElementById('tplWaName').value = '';
}

function editTemplate(tpl) {
    document.getElementById('tplId').value = tpl.id;
    document.getElementById('tplCode').value = tpl.code;
    document.getElementById('tplCode').disabled = true;
    document.getElementById('tplName').value = tpl.name;
    document.getElementById('tplChannel').value = tpl.channel;
    document.getElementById('tplSubject').value = tpl.subject || '';
    document.getElementById('tplBody').value = tpl.body_template;
    document.getElementById('tplDltTeId').value = tpl.dlt_template_id || '';
    document.getElementById('tplWaName').value = tpl.whatsapp_template_name || '';

    const modal = new bootstrap.Modal(document.getElementById('templateModal'));
    modal.show();
}

function testSendTemplate(tpl) {
    document.getElementById('sendTemplateId').value = tpl.id;
    document.getElementById('sendChannel').value = (tpl.channel !== 'all') ? tpl.channel : 'email';
    document.getElementById('sendSubject').value = tpl.subject || '';
    document.getElementById('sendMessage').value = tpl.body_template || '';

    const modal = new bootstrap.Modal(document.getElementById('quickSendModal'));
    modal.show();
}

document.getElementById('templateForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const id = document.getElementById('tplId').value;
    const isEdit = !!id;

    const payload = {
        code: document.getElementById('tplCode').value,
        name: document.getElementById('tplName').value,
        channel: document.getElementById('tplChannel').value,
        subject: document.getElementById('tplSubject').value,
        body_template: document.getElementById('tplBody').value,
        dlt_template_id: document.getElementById('tplDltTeId').value,
        whatsapp_template_name: document.getElementById('tplWaName').value
    };

    const url = isEdit ? `/api/message-templates/${id}` : '/api/message-templates';

    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            showAlert('Template saved successfully.', 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('templateModal'));
            if (modal) modal.hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.error || 'Failed to save template.', 'danger');
        }
    } catch (err) {
        showAlert(err.message, 'danger');
    }
});

// Opt-Out Form
document.getElementById('optOutForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    const payload = {
        identifier: document.getElementById('optIdentifier').value,
        channel: document.getElementById('optChannel').value,
        reason: document.getElementById('optReason').value,
        action: 'opt_out'
    };

    try {
        const res = await fetch('/api/messages/opt-out', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
            showAlert(data.message, 'success');
            const modal = bootstrap.Modal.getInstance(document.getElementById('optOutModal'));
            if (modal) modal.hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.error || 'Failed to opt-out.', 'danger');
        }
    } catch (err) {
        showAlert(err.message, 'danger');
    }
});

async function optInContact(channel, identifier) {
    if (!confirm(`Are you sure you want to opt in ${identifier} for ${channel} messages?`)) return;

    try {
        const res = await fetch('/api/messages/opt-out', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({identifier, channel, action: 'opt_in'})
        });
        const data = await res.json();
        if (data.success) {
            showAlert(data.message, 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(data.error || 'Failed to opt in.', 'danger');
        }
    } catch (err) {
        showAlert(err.message, 'danger');
    }
}

// Refresh Logs handler
document.getElementById('refreshLogsBtn').addEventListener('click', async function() {
    const ch = document.getElementById('logChannelFilter').value;
    const st = document.getElementById('logStatusFilter').value;
    const q = document.getElementById('logSearch').value;

    const params = new URLSearchParams();
    if (ch) params.append('channel', ch);
    if (st) params.append('status', st);
    if (q) params.append('search', q);

    try {
        const res = await fetch('/api/message-logs?' + params.toString());
        const json = await res.json();
        if (json.success) {
            const tbody = document.getElementById('logsTableBody');
            tbody.innerHTML = '';
            if (!json.data.length) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-muted py-4">No matching records found.</td></tr>';
                return;
            }
            json.data.forEach(log => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="small text-muted">${log.created_at.substring(0, 16)}</td>
                    <td><span class="badge bg-light text-dark border text-uppercase" style="font-size: 11px;">${log.channel}</span></td>
                    <td class="fw-semibold small">${escapeHtml(log.recipient)}</td>
                    <td>
                        <div class="small fw-bold text-dark">${escapeHtml(log.subject || log.template_name || 'Direct')}</div>
                        <div class="text-muted text-truncate" style="max-width: 280px; font-size: 11px;">${escapeHtml(log.message_body.replace(/<[^>]*>?/gm, ''))}</div>
                    </td>
                    <td><span class="badge ${getBadge(log.status)} small">${log.status.toUpperCase()}</span></td>
                    <td class="small">${log.gateway_message_id ? `<code>${escapeHtml(log.gateway_message_id)}</code>` : (log.error_message ? `<span class="text-danger">${escapeHtml(log.error_message.substring(0,40))}</span>` : '-')}</td>
                    <td class="small text-muted">${escapeHtml(log.sender_name || 'System')}</td>
                `;
                tbody.appendChild(tr);
            });
        }
    } catch (err) {
        showAlert(err.message, 'danger');
    }
});

function getBadge(status) {
    switch(status) {
        case 'sent': return 'bg-success';
        case 'queued': return 'bg-info text-dark';
        case 'failed': return 'bg-danger';
        case 'opted_out': return 'bg-secondary';
        default: return 'bg-light text-dark';
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
</script>
