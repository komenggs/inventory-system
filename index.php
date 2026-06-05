<?php
require_once 'config.php';

// Redirect jika sudah login
if (isLoggedIn()) {
    redirect('pages/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrfToken();

    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true); // cegah session fixation
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['nama']    = $user['nama'];
            $_SESSION['username']= $user['username'];
            $_SESSION['role']    = $user['role'];
            redirect('pages/dashboard.php');
        } else {
            $error = 'Username atau password salah!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — InvenTrack</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <link href="<?= base_url('assets/style.css') ?>" rel="stylesheet">
</head>
<body class="login-page">
    <div class="login-box">
        <div class="login-logo">
            <div class="login-logo-icon"><i class="fas fa-boxes-stacked"></i></div>
            <h1>Gudang Mengs</h1>
            <p>Sistem Manajemen Inventory</p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fas fa-circle-exclamation"></i> <?= $error ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?= csrfField() ?>
            <div class="form-group">
                <label class="form-label">Username</label>
                <div style="position:relative">
                    <i class="fas fa-user" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:.85rem"></i>
                    <input type="text" name="username" class="form-control" style="padding-left:36px"
                           placeholder="Masukkan username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required autocomplete="username">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Password</label>
                <div style="position:relative">
                    <i class="fas fa-lock" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:.85rem"></i>
                    <input type="password" name="password" id="passwordField" class="form-control" style="padding-left:36px;padding-right:44px"
                           placeholder="Masukkan password" required autocomplete="current-password">
                    <button type="button" id="togglePwd" style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9ca3af">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary" style="margin-top:8px">
                <i class="fas fa-right-to-bracket"></i> Masuk
            </button>
        </form>

        <div style="margin-top:24px;padding:14px;background:#f8faff;border-radius:10px;border:1px solid #dbeafe;font-size:.8rem;color:#4b5563">
            <strong style="color:#1d4ed8">Demo Login:</strong><br>
            &#128081; Admin: <code>admin</code> / <code>password</code><br>
            &#128100; Staff: <code>staff</code> / <code>password</code>
        </div>
    </div>

    <script>
    document.getElementById('togglePwd').addEventListener('click', function() {
        const f = document.getElementById('passwordField');
        const icon = this.querySelector('i');
        if (f.type === 'password') {
            f.type = 'text'; icon.className = 'fas fa-eye-slash';
        } else {
            f.type = 'password'; icon.className = 'fas fa-eye';
        }
    });
    </script>
</body>
</html>