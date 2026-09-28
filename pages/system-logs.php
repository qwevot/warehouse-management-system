<?php
$pageTitle = 'System Logs';
$activeNav  = 'system-logs';

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_can('logs.view');

$pdo = db();
$currentRole = current_role();
$isSuperAdmin = ($currentRole === 'super admin');

// Fetch distinct usernames for filter
$allUsers = [];
try {
    $allUsers = $pdo->query("SELECT DISTINCT username FROM system_logs WHERE username IS NOT NULL AND username <> '' ORDER BY username ASC")->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {}

// Filtering parameters
$filterCat    = trim($_GET['cat'] ?? '');
$filterStatus = trim($_GET['status'] ?? '');
$filterUser   = trim($_GET['user'] ?? '');
$search       = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM system_logs WHERE 1=1";
$params = [];

if ($filterCat !== '') {
    $sql .= " AND action LIKE ?";
    $params[] = $filterCat . '.%';
}
if ($filterStatus !== '') {
    $sql .= " AND status = ?";
    $params[] = $filterStatus;
}
if ($filterUser !== '') {
    $sql .= " AND username = ?";
    $params[] = $filterUser;
}
if ($search !== '') {
    $sql .= " AND (action LIKE ? OR username LIKE ? OR ip_address LIKE ? OR details LIKE ?)";
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$sql .= " ORDER BY id DESC LIMIT 500";

$logs = [];
$dbError = '';
try {
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $logs = $st->fetchAll();
} catch (Exception $e) {
    $dbError = 'Could not load system logs.';
}

require_once __DIR__ . '/../includes/data-pagination.php';
$logPagination = buildPagination('sysLogs', ['pageSize' => 15]);

include __DIR__ . '/../includes/header.php';
?>

<?php include __DIR__ . '/../includes/flash.php'; ?>

<!-- Filter & Search Toolbar -->
<form method="GET" action="system-logs.php" class="logs-filter-bar">
    <input type="text" name="q" class="logs-filter-input" placeholder="Search action, username, IP address, or payload..." value="<?php echo e($search); ?>">

    <select name="cat" class="logs-select" onchange="this.form.submit()">
        <option value="">All Event Categories</option>
        <option value="auth" <?php echo $filterCat === 'auth' ? 'selected' : ''; ?>>Authentication (auth.*)</option>
        <option value="inventory" <?php echo $filterCat === 'inventory' ? 'selected' : ''; ?>>Inventory Units (inventory.*)</option>
        <option value="master" <?php echo $filterCat === 'master' ? 'selected' : ''; ?>>Master Data (master.*)</option>
        <option value="movement" <?php echo $filterCat === 'movement' ? 'selected' : ''; ?>>Movements (movement.*)</option>
        <option value="user" <?php echo $filterCat === 'user' ? 'selected' : ''; ?>>User Accounts (user.*)</option>
    </select>

    <select name="status" class="logs-select" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        <option value="success" <?php echo $filterStatus === 'success' ? 'selected' : ''; ?>>Success</option>
        <option value="failure" <?php echo $filterStatus === 'failure' ? 'selected' : ''; ?>>Failure</option>
    </select>

    <?php if (!empty($allUsers)): ?>
    <select name="user" class="logs-select" onchange="this.form.submit()">
        <option value="">All Users</option>
        <?php foreach ($allUsers as $u): ?>
            <option value="<?php echo e($u); ?>" <?php echo $filterUser === $u ? 'selected' : ''; ?>><?php echo e($u); ?></option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>

</form>

<section class="inventory-section" id="systemLogsSection">

    <div class="sticky-zone">
        <div class="section-header" style="justify-content: space-between;">
            <div>
                <div style="font-size: 13px; color: #6b7280; margin-top: 2px;">
                    Showing <strong><?php echo count($logs); ?></strong> logs.
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 8px;">

                <a href="<?php echo BASE_URL; ?>/actions/export-logs.php?<?php echo http_build_query($_GET); ?>" class="export-btn icon-export" title="Download as CSV">
                    <svg viewBox="0 0 548.29 548.291" width="22" height="22" fill="currentColor" aria-hidden="true"><path d="M486.2,196.121h-13.164V132.59c0-0.399-0.064-0.795-0.116-1.2c-0.021-2.52-0.824-5-2.551-6.96L364.656,3.677 c-0.031-0.034-0.064-0.044-0.085-0.075c-0.629-0.707-1.364-1.292-2.141-1.796c-0.231-0.157-0.462-0.286-0.704-0.419 c-0.672-0.365-1.386-0.672-2.121-0.893c-0.199-0.052-0.377-0.134-0.576-0.188C358.229,0.118,357.4,0,356.562,0H96.757 C84.893,0,75.256,9.649,75.256,21.502v174.613H62.093c-16.972,0-30.733,13.756-30.733,30.73v159.81 c0,16.966,13.761,30.736,30.733,30.736h13.163V526.79c0,11.854,9.637,21.501,21.501,21.501h354.777 c11.853,0,21.502-9.647,21.502-21.501V417.392H486.2c16.966,0,30.729-13.764,30.729-30.731v-159.81 C516.93,209.872,503.166,196.121,486.2,196.121z M96.757,21.502h249.053v110.006c0,5.94,4.818,10.751,10.751,10.751h94.973v53.861 H96.757V21.502z M258.618,313.18c-26.68-9.291-44.063-24.053-44.063-47.389c0-27.404,22.861-48.368,60.733-48.368 c18.107,0,31.447,3.811,40.968,8.107l-8.09,29.3c-6.43-3.107-17.862-7.632-33.59-7.632c-15.717,0-23.339,7.149-23.339,15.485 c0,10.247,9.047,14.769,29.78,22.632c28.341,10.479,41.681,25.239,41.681,47.874c0,26.909-20.721,49.786-64.792,49.786 c-18.338,0-36.449-4.776-45.497-9.77l7.38-30.016c9.772,5.014,24.775,10.006,40.264,10.006c16.671,0,25.488-6.908,25.488-17.396 C285.536,325.789,277.909,320.078,258.618,313.18z M69.474,302.692c0-54.781,39.074-85.269,87.654-85.269 c18.822,0,33.113,3.811,39.549,7.149l-7.392,28.816c-7.38-3.084-17.632-5.939-30.491-5.939c-28.822,0-51.206,17.375-51.206,53.099 c0,32.158,19.051,52.4,51.456,52.4c10.947,0,23.097-2.378,30.241-5.238l5.483,28.346c-6.672,3.34-21.674,6.919-41.208,6.919 C98.06,382.976,69.474,348.424,69.474,302.692z M451.534,520.962H96.757v-103.57h354.777V520.962z M427.518,380.583h-42.399 l-51.45-160.536h39.787l19.526,67.894c5.479,19.046,10.479,37.386,14.299,57.397h0.709c4.048-19.298,9.045-38.352,14.526-56.693 l20.487-68.598h38.599L427.518,380.583z"></path></svg>
                </a>

                <?php if ($isSuperAdmin): ?>
                <button type="button" class="btn danger" onclick="openPurgeModal()" style="margin: 0; height: 38px; display: inline-flex; align-items: center; gap: 6px;">
                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
                    Purge Logs
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th>Time</th>
                    <th>Status</th>
                    <th>Action Event</th>
                    <th>Actor / User</th>
                    <th>Entity Target</th>
                    <th>IP Address</th>
                    <th class="payload-col">Payload</th>
                </tr>
            </thead>
            <tbody id="sysLogsBody">
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="8" style="text-align:center; color:#9ca3af; padding:40px;">
                            No activity logs found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $i => $lg): ?>
                        <?php
                            $actionPrefix = explode('.', $lg['action'])[0] ?? 'system';
                            $detailsArr = json_decode($lg['details'] ?? '{}', true);
                            $detailJson = json_encode($detailsArr, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                        ?>
                        <tr class="sys-log-row" data-id="<?php echo (int)$lg['id']; ?>" data-time="<?php echo strtotime($lg['created_at']); ?>">
                            <td class="no-col"><?php echo $i + 1; ?></td>
                            <td class="registered-on"><?php echo e(fmt_datetime($lg['created_at'])); ?></td>
                            <td>
                                <span class="status-dot <?php echo e($lg['status']); ?>"></span>
                                <?php echo ucfirst($lg['status']); ?>
                            </td>
                            <td>
                                <span class="action-badge <?php echo e($actionPrefix); ?>">
                                    <?php echo e($lg['action']); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo e($lg['username'] ?? 'guest'); ?></strong>
                                <div style="font-size: 11px; color: #6b7280;"><?php echo e(ucfirst($lg['role'] ?? 'guest')); ?></div>
                            </td>
                            <td>
                                <?php if (!empty($lg['entity_type'])): ?>
                                    <?php echo e($lg['entity_type']); ?> #<?php echo e($lg['entity_id'] ?? '-'); ?>
                                <?php else: ?>
                                    <span style="color: #9ca3af;">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo e($lg['ip_address'] ?? 'N/A'); ?></code></td>
                            <td class="payload-cell-td">
                                <?php if (!empty($detailsArr)): ?>
                                    <div class="payload-cell" id="payload-cell-<?php echo (int)$lg['id']; ?>">
                                        <button type="button" class="payload-preview-btn" onclick="togglePayload(<?php echo (int)$lg['id']; ?>)" title="Click to inspect raw JSON payload">
                                            <span class="payload-toggle-icon">▶</span>
                                            <span class="payload-snippet"><?php echo e(json_encode($detailsArr, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?></span>
                                        </button>
                                        <div class="payload-expanded">
                                            <div class="payload-expanded-header">
                                                <span class="payload-meta-label">Payload Data</span>
                                                <button type="button" class="payload-copy-btn" onclick="copyPayload(<?php echo (int)$lg['id']; ?>, this)">Copy</button>
                                            </div>
                                            <pre class="payload-code" id="payload-code-<?php echo (int)$lg['id']; ?>"><?php echo e($detailJson); ?></pre>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span style="color: #9ca3af;">&mdash;</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php echo $logPagination; ?>

</section>

<!-- Purge Confirmation Modal -->
<?php if ($isSuperAdmin): ?>
<div class="modal-backdrop" id="purgeModal">
    <form method="POST" action="<?php echo BASE_URL; ?>/actions/purge-logs.php" class="modal" style="max-width: 480px;">
        <?php echo csrf_field('system-logs'); ?>
        <div class="modal-header">
            <div class="modal-title">Purge System Logs</div>
            <button type="button" class="modal-close" onclick="closePurgeModal()">×</button>
        </div>
        <div class="modal-body">
            <p style="font-size: 14px; color: #374151; margin-bottom: 16px;">
                Choose the retention threshold to purge. This operation cannot be undone.
            </p>
            <div class="field">
                <label for="purgeRange">Retention Policy</label>
                <select id="purgeRange" name="range" class="logs-select" style="width: 100%;">
                    <option value="30">Delete logs older than 30 days</option>
                    <option value="90">Delete logs older than 90 days</option>
                    <option value="all">Delete ALL logs (Clear Black Box)</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn cancel" onclick="closePurgeModal()">Cancel</button>
            <button type="submit" class="btn danger" onclick="return confirm('Are you certain you want to purge the selected system logs?')">Purge Now</button>
        </div>
    </form>
</div>
<?php endif; ?>

<script>
function togglePayload(id) {
    const el = document.getElementById('payload-cell-' + id);
    if (el) {
        el.classList.toggle('is-open');
    }
}

function copyPayload(id, btn) {
    const codeEl = document.getElementById('payload-code-' + id);
    if (!codeEl) return;
    navigator.clipboard.writeText(codeEl.textContent).then(() => {
        const orig = btn.textContent;
        btn.textContent = 'Copied!';
        setTimeout(() => { btn.textContent = orig; }, 1500);
    }).catch(() => {
        btn.textContent = 'Failed';
        setTimeout(() => { btn.textContent = 'Copy'; }, 1500);
    });
}

function openPurgeModal() {
    const m = document.getElementById('purgeModal');
    if (m) m.classList.add('open');
}

function closePurgeModal() {
    const m = document.getElementById('purgeModal');
    if (m) m.classList.remove('open');
}

document.addEventListener("DOMContentLoaded", function () {
    if (typeof initDataTable === "function") {
        initDataTable({
            tbody:          '#sysLogsBody',
            row:            '.sys-log-row',
            pagePrev:       '#sysLogsPagePrev',
            pageNext:       '#sysLogsPageNext',
            pageNumbers:    '#sysLogsPageNumbers',
            pageRange:      '#sysLogsPageRange',
            pagination:     '#sysLogsPagination',
            pageSizeSelect: '#sysLogsPageSizeSelect'
        });
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
