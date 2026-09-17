<?php
$pageTitle = 'My Profile';
$activeNav = 'profile';

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

require_login();

$user = current_user();
if (!$user) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$fullName  = trim((string)($user['full_name'] ?? ''));
$dispName  = $fullName !== '' ? $fullName : (string)$user['username'];
$roleLabel = ucfirst((string)($user['role'] ?? 'User'));
$company   = !empty($user['company']) ? $user['company'] : 'Internal';
$createdAt = !empty($user['created_at']) ? date('M d, Y H:i', strtotime($user['created_at'])) : '—';
$avatar    = !empty($user['avatar']) ? $user['avatar'] : null;

$words = preg_split('/\s+/', $dispName);
$initials = strtoupper(($words[0][0] ?? 'U') . ($words[1][0] ?? ''));

include __DIR__ . '/../includes/header.php';
?>

<?php include __DIR__ . '/../includes/flash.php'; ?>

<div class="profile-page-container">

    <div class="profile-header-bar">
        <div class="section-title">My Profile</div>
    </div>

    <div class="profile-layout">

        <!-- Left Profile Overview Card -->
        <div class="profile-sidebar-card">
            <div class="profile-large-avatar" id="avatarPreviewContainer">
                <?php if ($avatar && is_file(__DIR__ . '/../' . $avatar)): ?>
                    <img src="<?php echo BASE_URL . '/' . e($avatar); ?>" alt="<?php echo e($dispName); ?>" id="avatarImgPreview">
                <?php else: ?>
                    <span id="avatarInitialsPreview"><?php echo e($initials); ?></span>
                <?php endif; ?>
            </div>

            <div class="profile-name-title"><?php echo e($dispName); ?></div>
            <div class="profile-username-tag">@<?php echo e($user['username']); ?></div>
            <span class="badge status-<?php echo e(strtolower($user['role'] ?? 'user')); ?>" style="display:inline-block; padding: 4px 12px; font-size:12px; font-weight:600; text-transform:capitalize;">
                <?php echo e($roleLabel); ?>
            </span>

            <div class="profile-meta-list">
                <div class="profile-meta-item">
                    <span class="profile-meta-label">User ID</span>
                    <span class="profile-meta-value">#<?php echo (int)$user['id']; ?></span>
                </div>
                <div class="profile-meta-item">
                    <span class="profile-meta-label">Organization</span>
                    <span class="profile-meta-value"><?php echo e($company); ?></span>
                </div>
                <div class="profile-meta-item">
                    <span class="profile-meta-label">Member Since</span>
                    <span class="profile-meta-value"><?php echo e($createdAt); ?></span>
                </div>
            </div>
        </div>

        <!-- Right Content / Settings Cards -->
        <div class="profile-main-content">

            <!-- Card 1: Personal Information -->
            <div class="profile-card">
                <div class="profile-card-header">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" style="color:var(--blue)">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    <h3>Personal Information</h3>
                </div>

                <form action="<?php echo BASE_URL; ?>/actions/update-profile.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="update_profile">

                    <div class="avatar-upload-zone">
                        <div class="avatar-preview-thumb" id="thumbPreviewBox">
                            <?php if ($avatar && is_file(__DIR__ . '/../' . $avatar)): ?>
                                <img src="<?php echo BASE_URL . '/' . e($avatar); ?>" alt="Preview" id="thumbImg">
                            <?php else: ?>
                                <span id="thumbInitials"><?php echo e($initials); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="avatar-upload-controls">
                            <div class="field" style="margin-bottom:0;">
                                <label for="avatarFileInput">Profile Picture</label>
                                <input type="file" name="avatar" id="avatarFileInput" accept="image/jpeg,image/png,image/webp,image/gif" onchange="previewAvatar(this)">
                                <span style="font-size:11px; color:var(--muted); margin-top:3px; display:block;">.jpg, .png, or .webp</span>
                                <?php if ($avatar): ?>
                                    <div style="margin-top:6px;">
                                        <label style="font-size:12px; color:var(--red); cursor:pointer; display:inline-flex; align-items:center; gap:4px; font-weight:normal;">
                                            <input type="checkbox" name="remove_avatar" value="1" style="height:auto; width:auto;"> Remove current photo
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-grid">
                        <div class="field">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" value="<?php echo e($fullName); ?>" placeholder="e.g. Eleanor Vance">
                        </div>

                        <div class="field">
                            <label for="username_disp">Username</label>
                            <input type="text" id="username_disp" value="<?php echo e($user['username']); ?>" disabled>
                            <span style="font-size:11px; color:var(--muted); margin-top:2px; display:block;">Username is managed by administration.</span>
                        </div>

                        <div class="field">
                            <label for="role_disp">Role</label>
                            <input type="text" id="role_disp" value="<?php echo e($roleLabel); ?>" disabled>
                        </div>

                        <div class="field">
                            <label for="company_disp">Company / Client</label>
                            <input type="text" id="company_disp" value="<?php echo e($company); ?>" disabled>
                        </div>
                    </div>

                    <div style="margin-top:20px; display:flex; justify-content:flex-end;">
                        <button type="submit" class="btn primary">Save Profile</button>
                    </div>
                </form>
            </div>

            <!-- Card 2: Security & Password -->
            <div class="profile-card">
                <div class="profile-card-header">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" style="color:var(--blue)">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                    <h3>Change Password</h3>
                </div>

                <form action="<?php echo BASE_URL; ?>/actions/update-profile.php" method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrf_token()); ?>">
                    <input type="hidden" name="action" value="update_password">

                    <div class="form-grid">
                        <div class="field" style="grid-column: 1 / -1;">
                            <label for="current_password">Current Password *</label>
                            <input type="password" id="current_password" name="current_password" required autocomplete="current-password" placeholder="Enter your current password">
                        </div>

                        <div class="field">
                            <label for="new_password">New Password *</label>
                            <input type="password" id="new_password" name="new_password" required minlength="6" autocomplete="new-password" placeholder="Min. 6 characters">
                        </div>

                        <div class="field">
                            <label for="confirm_password">Confirm New Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="6" autocomplete="new-password" placeholder="Re-enter new password">
                        </div>
                    </div>

                    <div style="margin-top:20px; display:flex; justify-content:flex-end;">
                        <button type="submit" class="btn primary">Update Password</button>
                    </div>
                </form>
            </div>

        </div>

    </div>

</div>

<script>
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const thumbBox = document.getElementById('thumbPreviewBox');
            thumbBox.innerHTML = '<img src="' + e.target.result + '" style="width:100%; height:100%; object-fit:cover;">';
            
            const bigBox = document.getElementById('avatarPreviewContainer');
            bigBox.innerHTML = '<img src="' + e.target.result + '" style="width:100%; height:100%; object-fit:cover;">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
