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
?>

<?php if (!empty($dbError)): ?>
    <div class="flash error"><?php echo e($dbError); ?></div>
<?php endif; ?>

<?php if (isset($_GET['added'])): ?>
    <div class="flash success"><?php echo e($_GET['added']); ?></div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="flash error"><?php echo e($_GET['error']); ?></div>
<?php endif; ?>

<section class="inventory-section" id="users">

    <div class="sticky-zone">
        <div class="section-header">
            <div class="section-title">Users</div>
            <button type="button" class="btn primary add-button" onclick="openAddUserModal()">+ Add User</button>
        </div>
    </div>

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
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center; color:#9ca3af; padding:40px;">
                            No users yet. Add one above.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $i => $u): ?>
                        <tr>
                            <td class="no-col"><?php echo $i + 1; ?></td>
                            <td>
                                <div class="row-actions">
                                    <button type="button" class="btn icon-btn edit" title="Edit" onclick="openEditUserModal(<?php echo (int)$u['id']; ?>, <?php echo e(json_encode($u['username'])); ?>, <?php echo e(json_encode($u['full_name'] ?? '')); ?>, <?php echo e(json_encode($u['role'])); ?>)">
                                        <svg viewBox="0 -0.5 21 21" version="1.1" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" fill="currentColor"><g id="SVGRepo_bgCarrier" stroke-width="0"></g><g id="SVGRepo_tracerCarrier" stroke-linecap="round" stroke-linejoin="round"></g><g id="SVGRepo_iconCarrier"> <title>edit_fill [#1480]</title> <desc>Created with Sketch.</desc> <defs> </defs> <g id="Page-1" stroke="none" stroke-width="1" fill="none" fill-rule="evenodd"> <g id="Dribbble-Light-Preview" transform="translate(-59.000000, -400.000000)" fill="currentColor"> <g id="icons" transform="translate(56.000000, 160.000000)"> <path d="M3,260 L24,260 L24,258.010742 L3,258.010742 L3,260 Z M13.3341,254.032226 L9.3,254.032226 L9.3,249.950269 L19.63095,240 L24,244.115775 L13.3341,254.032226 Z" id="edit_fill-[#1480]"> </path></svg>
                                    </button>
                                    <?php if ((int)$u['id'] !== $currentUserId): ?>
                                        <button type="button" class="btn icon-btn delete" title="Delete" onclick="confirmDeleteUser(<?php echo (int)$u['id']; ?>, <?php echo e(json_encode($u['username'])); ?>)">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6.52381C3 6.12932 3.32671 5.80952 3.72973 5.80952H8.51787C8.52437 4.9683 8.61554 3.81504 9.45037 3.01668C10.1074 2.38839 11.0081 2 12 2C12.9919 2 13.8926 2.38839 14.5496 3.01668C15.3844 3.81504 15.4756 4.9683 15.4821 5.80952H20.2703C20.6733 5.80952 21 6.12932 21 6.52381C21 6.9183 20.6733 7.2381 20.2703 7.2381H3.72973C3.32671 7.2381 3 6.9183 3 6.52381Z" fill="currentColor"></path> <path d="M11.6066 22H12.3935C15.101 22 16.4547 22 17.3349 21.1368C18.2151 20.2736 18.3052 18.8576 18.4853 16.0257L18.7448 11.9452C18.8425 10.4086 18.8913 9.64037 18.4498 9.15352C18.0082 8.66667 17.2625 8.66667 15.7712 8.66667H8.22884C6.7375 8.66667 5.99183 8.66667 5.55026 9.15352C5.1087 9.64037 5.15756 10.4086 5.25528 11.9452L5.51479 16.0257C5.69489 18.8576 5.78494 20.2736 6.66513 21.1368C7.54532 22 8.89906 22 11.6066 22Z" fill="currentColor"></path></svg>
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

</section>

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
        const f = document.createElement("form");
        f.method = "POST";
        f.action = "<?php echo BASE_URL; ?>/actions/delete-user.php";
        const inp = document.createElement("input");
        inp.type = "hidden";
        inp.name = "id";
        inp.value = id;
        f.appendChild(inp);
        document.body.appendChild(f);
        f.submit();
    }
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
