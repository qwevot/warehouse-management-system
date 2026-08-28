<?php
$pageTitle = 'Users';
$activeNav = 'users';

require_once __DIR__ . '/../includes/auth.php';
require_login();
require_role('super admin');

$users = [];
$dbError = '';

try {
    $pdo = db();
    $users = $pdo->query('SELECT id, username, full_name, role, created_at FROM users ORDER BY id ASC')->fetchAll();
} catch (Exception $e) {
    $dbError = 'Could not load users from the database.';
}

$roles = available_roles();
$currentUserId = (int)(current_user()['id'] ?? 0);

include __DIR__ . '/../includes/header.php';

require_once __DIR__ . '/../includes/data-toolbar.php';
require_once __DIR__ . '/../includes/data-pagination.php';

$userSortOptions = [
    ['newest',    'Newest First'],
    ['oldest',    'Oldest First'],
    ['username',  'Username A-Z'],
    ['fullname',  'Full Name A-Z'],
    ['role',      'Role'],
];
$userToolbar    = buildToolbar('user', $userSortOptions);
$userPagination = buildPagination('user', ['pageSize' => 10]);
?>

<?php include __DIR__ . '/../includes/flash.php'; ?>

<section class="inventory-section" id="users">

    <div class="sticky-zone">
        <div class="section-header">
            <div class="section-title">Users</div>
            <button type="button" class="btn primary add-button" onclick="openAddUserModal()">+ Add User</button>
        </div>
    </div>

    <div class="toolbar-row"><?php echo $userToolbar; ?></div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>No.</th>
                    <th class="action-col">Action</th>
                    <th>Username</th>
                    <th>Full Name</th>
                    <th>Role</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody id="userBody">
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; color:#9ca3af; padding:40px;">
                            No users yet. Add one above.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $i => $u): ?>
                        <tr class="user-row"
                            data-created="<?php echo strtotime($u['created_at']); ?>"
                            data-username="<?php echo e(mb_strtolower((string)$u['username'])); ?>"
                            data-fullname="<?php echo e(mb_strtolower((string)($u['full_name'] ?? ''))); ?>"
                            data-role="<?php echo e(mb_strtolower((string)$u['role'])); ?>">
                            <td class="no-col"><?php echo $i + 1; ?></td>
                            <td>
                                <div class="row-actions">
                                    <button type="button" class="btn icon-btn edit" title="Edit" onclick="openEditUserModal(<?php echo (int)$u['id']; ?>, <?php echo e(json_encode($u['username'])); ?>, <?php echo e(json_encode($u['full_name'] ?? '')); ?>, <?php echo e(json_encode($u['role'])); ?>)">
                                        <?php echo icon_edit(); ?>
                                    </button>
                                    <?php if ((int)$u['id'] !== $currentUserId): ?>
                                        <button type="button" class="btn icon-btn delete" title="Delete" onclick="confirmDeleteUser(<?php echo (int)$u['id']; ?>, <?php echo e(json_encode($u['username'])); ?>)">
                                            <?php echo icon_delete(); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><?php echo e($u['username']); ?></td>
                            <td><?php echo dash($u['full_name']); ?></td>
                            <td><?php echo e(ucfirst($u['role'])); ?></td>
                            <td class="registered-on"><?php echo e(fmt_datetime($u['created_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php echo $userPagination; ?>

</section>

<script>
    window.USERS_DATA = <?php echo json_encode(array_map(function ($u) {
        return [
            'username'   => $u['username'],
            'full_name'  => $u['full_name'] ?? '',
            'role'       => $u['role'],
            'created_at' => $u['created_at'] ? date('Y-m-d H:i', strtotime($u['created_at'])) : '',
        ];
    }, $users)); ?>;

    document.addEventListener("DOMContentLoaded", function () {
        initDataTable({
            searchInput:    '#userSearchInput',
            sortSelect:     '#userSortSelect',
            tbody:          '#userBody',
            row:            '.user-row',
            filterWrap:     '#userFilterWrap',
            filterBtn:      '#userFilterBtn',
            filterPanel:    '#userFilterPanel',
            filterBadge:    '#userFilterBadge',
            pagePrev:       '#userPagePrev',
            pageNext:       '#userPageNext',
            pageNumbers:    '#userPageNumbers',
            pageRange:      '#userPageRange',
            pagination:     '#userPagination',
            pageSizeSelect: '#userPageSizeSelect',
            exportFn:       'userExport',
            exportName:     'users-list',
            exportCols: [
                { key: 'username',   label: 'Username' },
                { key: 'full_name',  label: 'Full Name' },
                { key: 'role',       label: 'Role' },
                { key: 'created_at', label: 'Created' },
            ],
            filterGroups: [
                {
                    title: 'Role',
                    key: 'role',
                    dataValue: (r) => r.role,
                    options: <?php echo json_encode(array_values(array_map(function ($r) {
                        return ['value' => $r, 'label' => ucfirst($r)];
                    }, $roles))); ?>,
                },
            ],
            sorters: {
                'newest':   function (a, b) { return (parseInt(b.dataset.created, 10) || 0) - (parseInt(a.dataset.created, 10) || 0); },
                'oldest':   function (a, b) { return (parseInt(a.dataset.created, 10) || 0) - (parseInt(b.dataset.created, 10) || 0); },
                'username': function (a, b) { return (a.dataset.username || '').localeCompare(b.dataset.username || ''); },
                'fullname': function (a, b) { return (a.dataset.fullname || '').localeCompare(b.dataset.fullname || ''); },
                'role':     function (a, b) { return (a.dataset.role || '').localeCompare(b.dataset.role || ''); },
            },
            data:     window.USERS_DATA,
            dataText: function (r) { return r.username + ' ' + r.full_name + ' ' + r.role; },
        });
    });
</script>

<!-- Add user modal -->
<div class="modal-backdrop" id="addUserModal">
    <form method="POST" action="<?php echo BASE_URL; ?>/actions/save-user.php" class="modal">
        <div class="modal-header">
            <div class="modal-title">Add User</div>
            <button type="button" class="modal-close" onclick="closeAddUserModal()">×</button>
        </div>
        <div class="modal-body">
            <div class="form-grid">
                <div class="field">
                    <label for="addUsername">Username *</label>
                    <input type="text" id="addUsername" name="username" required maxlength="64" autocomplete="off">
                </div>
                <div class="field">
                    <label for="addFullName">Full name</label>
                    <input type="text" id="addFullName" name="full_name" maxlength="100">
                </div>
                <div class="field">
                    <label for="addRole">Role</label>
                    <select id="addRole" name="role" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?php echo e($r); ?>"><?php echo e(ucfirst($r)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="addPassword">Password *</label>
                    <input type="password" id="addPassword" name="password" required autocomplete="new-password">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn cancel" onclick="closeAddUserModal()">Cancel</button>
            <button type="submit" class="btn primary">Add User</button>
        </div>
    </form>
</div>

<!-- Edit user modal -->
<div class="modal-backdrop" id="editUserModal">
    <form method="POST" action="<?php echo BASE_URL; ?>/actions/save-user.php" class="modal">
        <div class="modal-header">
            <div class="modal-title">Edit User</div>
            <button type="button" class="modal-close" onclick="closeEditUserModal()">×</button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="id" id="editUserId">
            <div class="form-grid">
                <div class="field">
                    <label for="editUsername">Username *</label>
                    <input type="text" id="editUsername" name="username" required maxlength="64" autocomplete="off">
                </div>
                <div class="field">
                    <label for="editFullName">Full name</label>
                    <input type="text" id="editFullName" name="full_name" maxlength="100">
                </div>
                <div class="field">
                    <label for="editRole">Role</label>
                    <select id="editRole" name="role" required>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?php echo e($r); ?>"><?php echo e(ucfirst($r)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="field">
                    <label for="editPassword">New password</label>
                    <input type="password" id="editPassword" name="password" autocomplete="new-password" placeholder="Leave blank to keep current">
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn cancel" onclick="closeEditUserModal()">Cancel</button>
            <button type="submit" class="btn primary">Save</button>
        </div>
    </form>
</div>

<script>
    function openAddUserModal() {
        document.getElementById("addUserModal").classList.add("open");
    }

    function closeAddUserModal() {
        document.getElementById("addUserModal").classList.remove("open");
    }

    function openEditUserModal(id, username, fullName, role) {
        document.getElementById("editUserId").value = id;
        document.getElementById("editUsername").value = username;
        document.getElementById("editFullName").value = fullName || "";
        document.getElementById("editRole").value = role;
        document.getElementById("editUserModal").classList.add("open");
    }

    function closeEditUserModal() {
        document.getElementById("editUserModal").classList.remove("open");
    }

    async function confirmDeleteUser(id, username) {
        const ok = await window.promptModal({
            title: "Delete user?",
            message: "Delete user \"" + username + "\"? This cannot be undone.",
            confirmLabel: "Delete",
            withNote: false,
        });
        if (ok === null) return;
        window.submitPostForm("<?php echo BASE_URL; ?>/actions/delete-user.php", { id: id });
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
