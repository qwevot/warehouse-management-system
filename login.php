<?php
require_once __DIR__ . '/includes/auth.php';

// Already logged in? Go straight to the dashboard.
if (current_user() !== null) {
    header('Location: pages/dashboard.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        try {
            $st = db()->prepare('SELECT * FROM users WHERE username = ?');
            $st->execute([$username]);
            $user = $st->fetch();

            if ($user !== false && password_verify($password, $user['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int)$user['id'];
                header('Location: pages/dashboard.php');
                exit;
            }

            $error = 'Invalid username or password.';
        } catch (Exception $e) {
            $error = 'Login is temporarily unavailable.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Inventory System</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="login-body">

<div class="page-loader" id="pageLoader" aria-hidden="true">
    <div class="spinner"></div>
</div>

<div class="login-page">

    <div class="login-card">

        <div class="login-brand">

            <div class="login-title">
                Login
            </div>

        </div>

        <?php if ($error !== ''): ?>
            <div class="login-error">
                <?php echo e($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php" class="login-form" autocomplete="off">

            <div class="field">
                <label for="username">Username</label>
                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Enter username"
                    value="<?php echo e($_POST['username'] ?? ''); ?>"
                    autofocus
                    required
                >
            </div>

            <div class="field">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter password"
                    required
                >
            </div>

            <button type="submit" class="btn primary">
                Sign In
            </button>

        </form>

    </div>

</div>

<script>
    document.querySelector(".login-form").addEventListener("submit", function () {
        document.getElementById("pageLoader").classList.add("active");
    });
</script>

</body>
</html>
