<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
if (uid()) redirect('dashboard.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_SESSION['fails'] ?? 0) >= 5 && time() - ($_SESSION['fail_t'] ?? 0) < 60) {
        $error = 'Terlalu banyak percobaan. Coba lagi dalam 1 menit.';
    } else {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $pw = (string)($_POST['password'] ?? '');
        $s = db()->prepare('SELECT id, name, password_hash, role FROM users WHERE email = ?');
        $s->execute([$email]);
        $u = $s->fetch();
        if ($u && password_verify($pw, $u['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION = ['uid' => (int)$u['id'], 'name' => $u['name'], 'role' => $u['role']];
            redirect('dashboard.php');
        }
        $_SESSION['fails'] = ($_SESSION['fails'] ?? 0) + 1; $_SESSION['fail_t'] = time();
        $error = 'Email atau kata sandi salah.';
    }
}
auth_head("Masuk");
foreach (take_flash() as [$t, $m]) echo "<div class=\"alert ".e($t)."\">".e($m)."</div>"; ?>
<h2>Masuk</h2>
<?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="form"><?= csrf_field() ?>
  <label>Email<input type="email" name="email" required autofocus></label>
  <label>Kata Sandi<input type="password" name="password" required></label>
  <button class="btn primary block">Masuk</button>
</form>
<?php if (cfg()['allow_register']): ?><p class="muted center-t">Belum punya akun? <a href="register.php">Daftar</a></p><?php endif;
auth_foot();
