<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/app/bootstrap.php';

if (current_admin() !== null) {
    redirect('admin/index.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_or_fail();
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?: '';
    $password = (string)($_POST['password'] ?? '');

    if ($email !== '' && $password !== '') {
        $stmt = Database::connection()->prepare(
            'SELECT id, name, email, password_hash, role, status FROM admins WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => mb_strtolower(trim($email))]);
        $row = $stmt->fetch();

        if ($row && $row['status'] === 'active' && password_verify($password, $row['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin'] = [
                'id' => (int)$row['id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'role' => $row['role'],
            ];
            Database::connection()->prepare('UPDATE admins SET last_login_at = NOW() WHERE id = :id')
                ->execute(['id' => (int)$row['id']]);
            redirect('admin/index.php');
        }
    }
    $error = 'Email atau password tidak valid.';
}
?><!doctype html>
<html lang="id">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Admin Login | Bali Tarantula Adventure</title>
<style>
:root{color-scheme:dark;--orange:#ff5a1f}*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;background:#111;color:#f7f5f0;font:16px/1.5 Arial,sans-serif;padding:24px}.panel{width:min(430px,100%);background:#1b1b1b;border:1px solid #343434;border-top:4px solid var(--orange);padding:30px}.brand{font-weight:900;letter-spacing:1px}.brand span{color:var(--orange)}h1{font-size:28px;margin:28px 0 6px}p{color:#aaa;font-size:13px}label{display:block;font-size:12px;margin:17px 0 7px}input{width:100%;padding:13px;background:#101010;border:1px solid #454545;color:white;font:inherit}button{width:100%;margin-top:22px;padding:14px;border:0;background:var(--orange);font-weight:900;cursor:pointer}.error{padding:10px;background:#4a2018;color:#ffd9cc;font-size:13px}
</style>
</head><body><main class="panel"><div class="brand">BALI TARANTULA <span>ADVENTURE</span></div><h1>Admin sign in</h1><p>Masuk untuk mengelola aktivitas, booking, pembayaran, dan tiket.</p>
<?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
<form method="post" action="login.php" autocomplete="on"><?= csrf_field() ?>
<label for="email">Email admin</label><input id="email" name="email" type="email" autocomplete="username" required>
<label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required>
<button type="submit">SIGN IN ↗</button></form></main></body></html>
