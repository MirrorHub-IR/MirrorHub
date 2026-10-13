<?php
require_once '../includes/db.php';
require_once '../includes/functions.php';
require_once '../includes/layout.php';

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_unset();
    session_destroy();
    redirect(SITE_URL . '/admin/login.php');
}

if (isAdmin()) {
    redirect(SITE_URL . '/admin/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        flash('error', 'توکن امنیتی نامعتبر است.');
        redirect(SITE_URL . '/admin/login.php');
    }

    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        flash('error', 'لطفاً نام کاربری و رمز عبور را وارد کنید.');
        redirect(SITE_URL . '/admin/login.php');
    }

    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        redirect(SITE_URL . '/admin/dashboard.php');
    } else {
        flash('error', 'نام کاربری یا رمز عبور اشتباه است.');
        redirect(SITE_URL . '/admin/login.php');
    }
}

renderHeader('ورود به مدیریت', true);
?>

<section class="auth-container">
    <div class="auth-box">
        <div class="auth-logo-wrap">
            <img src="<?= asset('logo.png') ?>" alt="<?= htmlspecialchars(SITE_NAME) ?>" class="auth-logo" width="72" height="72">
        </div>
        <h1>ورود به پنل مدیریت</h1>
        <form action="<?= SITE_URL ?>/admin/login.php" method="POST" class="auth-form">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= generateCsrfToken() ?>">
            
            <div class="form-group">
                <label for="username">نام کاربری</label>
                <input type="text" id="username" name="username" required autofocus autocomplete="username" dir="ltr" placeholder="admin">
            </div>

            <div class="form-group">
                <label for="password">رمز عبور</label>
                <input type="password" id="password" name="password" required autocomplete="current-password" dir="ltr" placeholder="••••••••">
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-block">ورود</button>
            </div>
        </form>
    </div>
</section>

<?php renderFooter(); ?>