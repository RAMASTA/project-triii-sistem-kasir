<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/layout.php';
if (uid()) redirect('dashboard.php');
$first = (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0;
if (!$first && !cfg()['allow_register']) { http_response_code(403); exit('Pendaftaran ditutup.'); }
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim((string)($_POST['name'] ?? '')); $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pw = (string)($_POST['password'] ?? ''); $pw2 = (string)($_POST['password2'] ?? '');
    if ($name === '' || mb_strlen($name) > 100) $errors[] = 'Nama wajib diisi (maks. 100 karakter).';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) $errors[] = 'Email tidak valid.';
    if (strlen($pw) < 8) $errors[] = 'Kata sandi minimal 8 karakter.';
    if ($pw !== $pw2) $errors[] = 'Konfirmasi kata sandi tidak sama.';
    if (!$errors) {
        try {
            db()->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,?)')
                ->execute([$name, $email, password_hash($pw, PASSWORD_DEFAULT), $first ? 'admin' : 'staff']);
            flash('success', 'Akun berhasil dibuat. Silakan masuk.');
            redirect('login.php');
        } catch (PDOException $ex) {
            $errors[] = ($ex->errorInfo[1] ?? 0) === 1062 ? 'Email sudah terdaftar.' : 'Gagal membuat akun.';
        }
    }
}
auth_head('Daftar');
foreach (take_flash() as [$t, $m]) echo '<div class="alert ' . e($t) . '">' . e($m) . '</div>'; ?>
<h2>Buat Akun<?= $first ? ' Admin' : '' ?></h2>
<?php foreach ($errors as $er): ?><div class="alert error"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" class="form"><?= csrf_field() ?>
  <label>Nama<input name="name" required maxlength="100" value="<?= e($_POST['name'] ?? '') ?>"></label>
  <label>Email<input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"></label>
  <label>Kata Sandi (min. 8)<input type="password" name="password" required minlength="8"></label>
  <label>Ulangi Kata Sandi<input type="password" name="password2" required minlength="8"></label>
  <button class="btn primary block">Daftar</button>
</form>
<p class="muted center-t"><a href="login.php">Kembali ke halaman masuk</a></p>
<?php auth_foot();
