<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/crud.php';
require __DIR__ . '/includes/python.php';

$mods = require __DIR__ . '/includes/modules.php';
$m = (string)($_GET['m'] ?? '');
if (!isset($mods[$m])) { http_response_code(404); exit('Modul tidak ditemukan.'); }
$mod = $mods[$m]; $table = $mod['table'];   // nama tabel hanya dari whitelist
$self = 'module.php?m=' . $m;
$batches = owned_batches();
$batchMap = array_column($batches, null, 'id');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    [$of, $op] = own_filter($table);

    if ($act === 'delete' && $id > 0) {
        $chk = db()->prepare("SELECT id FROM $table WHERE id = ?$of"); $chk->execute([$id, ...$op]);
        if ($chk->fetch()) {
            if ($m === 'batches') delete_photo_files('batch_id', $id);
            if ($m === 'growth') delete_photo_files('growth_log_id', $id);
            db()->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
            flash('success', 'Data berhasil dihapus.');
        } else flash('error', 'Data tidak ditemukan.');
        redirect($self);
    }

    if ($act === 'save') {
        $errors = [];
        $data = validate_fields($mod['fields'], $_POST, $errors);
        if ($m === 'batches') {
            if (!empty($data['hatch_date']) && !empty($data['egg_date']) && $data['hatch_date'] < $data['egg_date']) $errors[] = 'Tanggal penetasan tidak boleh sebelum tanggal telur.';
        } else {
            $bid = (int)($data['batch_id'] ?? 0);
            if (!isset($batchMap[$bid])) $errors[] = 'Batch tidak valid.';
            elseif ($m === 'growth' && !$errors) {
                if (!$batchMap[$bid]['hatch_date']) $errors[] = 'Isi tanggal penetasan pada batch terlebih dahulu untuk menghitung umur.';
                else {
                    $age = (int)(new DateTime($batchMap[$bid]['hatch_date']))->diff(new DateTime($data['measure_date']))->format('%r%a');
                    if ($age < 0) $errors[] = 'Tanggal pengukuran sebelum tanggal penetasan.';
                    $data['age_days'] = $age;
                }
            }
        }
        if ($errors) { flash('error', implode(' ', $errors)); redirect($self); }

        if ($id > 0) {
            $chk = db()->prepare("SELECT id FROM $table WHERE id = ?$of"); $chk->execute([$id, ...$op]);
            if (!$chk->fetch()) { flash('error', 'Data tidak ditemukan.'); redirect($self); }
            $set = implode(', ', array_map(fn($c) => "$c = ?", array_keys($data)));
            db()->prepare("UPDATE $table SET $set WHERE id = ?")->execute([...array_values($data), $id]);
        } else {
            if ($m === 'batches') { $data['user_id'] = uid(); $data['batch_code'] = 'TMP' . bin2hex(random_bytes(8)); }
            $cols = implode(', ', array_keys($data));
            db()->prepare("INSERT INTO $table ($cols) VALUES (" . implode(',', array_fill(0, count($data), '?')) . ')')->execute(array_values($data));
            $id = (int)db()->lastInsertId();
            if ($m === 'batches') db()->prepare('UPDATE batches SET batch_code = ? WHERE id = ?')->execute([sprintf('BSF-%s-%04d', date('Y'), $id), $id]);
        }
        if ($m === 'growth' && isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            $err = save_photo($_FILES['photo'], (int)$data['batch_id'], $id, trim((string)($_POST['caption'] ?? '')) ?: null);
            if ($err) flash('error', 'Data tersimpan, tetapi foto gagal: ' . $err);
        }
        flash('success', 'Data berhasil disimpan.');
        redirect($self);
    }
    redirect($self);
}

// ---- Data untuk tampilan ----
$filter = (int)($_GET['batch'] ?? 0);
[$sw, $sp] = batch_scope('b');
if ($m === 'batches') {
    $sql = "SELECT t.* FROM batches t JOIN batches b ON b.id = t.id WHERE $sw ORDER BY t.{$mod['date']} DESC, t.id DESC LIMIT 500";
    $params = $sp;
} else {
    $sql = "SELECT t.*, b.batch_code, b.name AS batch_name FROM $table t JOIN batches b ON b.id = t.batch_id WHERE $sw"
         . ($filter ? ' AND t.batch_id = ?' : '') . " ORDER BY t.{$mod['date']} DESC, t.id DESC LIMIT 500";
    $params = $filter ? [...$sp, $filter] : $sp;
}
$st = db()->prepare($sql); $st->execute($params); $rows = $st->fetchAll();

$photos = [];
if ($m === 'growth' && $rows) {
    $ids = array_column($rows, 'id');
    $ps = db()->prepare('SELECT growth_log_id, file_path FROM batch_photos WHERE growth_log_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
    $ps->execute($ids);
    foreach ($ps->fetchAll() as $p) $photos[$p['growth_log_id']][] = $p['file_path'];
}

$chart = null; $analysis = null; $analysisTried = false;
if ($m === 'growth' || $m === 'environment') {
    $q = $m === 'growth'
        ? "SELECT t.batch_id, b.batch_code, t.age_days, t.sample_weight_g FROM growth_logs t JOIN batches b ON b.id = t.batch_id WHERE $sw ORDER BY t.batch_id, t.age_days"
        : "SELECT t.log_date, AVG(t.temperature_c) temp, AVG(t.humidity_pct) hum FROM environment_logs t JOIN batches b ON b.id = t.batch_id WHERE $sw GROUP BY t.log_date ORDER BY t.log_date";
    $cs = db()->prepare($q); $cs->execute($sp); $cr = $cs->fetchAll();
    if ($cr) {
        if ($m === 'growth') {
            $by = [];
            foreach ($cr as $r) { $by[$r['batch_id']]['label'] = $r['batch_code']; $by[$r['batch_id']]['pts'][] = ['x' => (int)$r['age_days'], 'y' => (float)$r['sample_weight_g']]; $by[$r['batch_id']]['logs'][] = ['age' => $r['age_days'], 'weight' => $r['sample_weight_g']]; }
            $chart = ['type' => 'growth', 'sets' => array_map(fn($b) => ['label' => $b['label'], 'data' => $b['pts']], array_values($by))];
            $payload = [];
            foreach ($by as $bid => $b) $payload[] = ['id' => $bid, 'code' => $b['label'], 'name' => $batchMap[$bid]['name'] ?? '', 'logs' => $b['logs']];
            $analysis = run_growth_analysis($payload); $analysisTried = true;
        } else {
            $chart = ['type' => 'env', 'labels' => array_map(fn($r) => $r['log_date'], $cr),
                      'temp' => array_map(fn($r) => round((float)$r['temp'], 1), $cr), 'hum' => array_map(fn($r) => round((float)$r['hum'], 1), $cr)];
        }
    }
}

$GLOBALS['page_script'] = '<script>window.MT_PAGE=' . json_for_js(['chart' => $chart]) . ';</script>';
page_header($mod['title'], $m); ?>
<div class="page-head">
  <div><h1><?= e($mod['title']) ?></h1><p class="muted"><?= e($mod['sub']) ?></p></div>
  <?php if ($m === 'batches' || $batches): ?><button class="btn primary" id="addBtn"><i data-lucide="plus"></i> Tambah Data</button><?php endif; ?>
</div>

<?php if ($m !== 'batches' && !$batches): ?>
  <div class="empty"><i data-lucide="layers"></i><h3>Belum ada batch</h3><p>Buat batch terlebih dahulu sebelum mencatat data ini.</p><a class="btn primary" href="module.php?m=batches">Buat Batch</a></div>
<?php else: ?>

<?php if ($m !== 'batches'): ?>
<form method="get" class="filter"><input type="hidden" name="m" value="<?= e($m) ?>">
  <select name="batch" onchange="this.form.submit()"><option value="0">Semua batch</option>
    <?php foreach ($batches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= $filter === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['batch_code'] . ' - ' . $b['name']) ?></option><?php endforeach; ?>
  </select>
</form>
<?php endif; ?>

<?php if ($m === 'growth' || $m === 'environment'): ?>
<section class="card"><h3><?= $m === 'growth' ? 'Grafik Pertumbuhan (berat sampel vs umur)' : 'Grafik Suhu dan Kelembapan' ?></h3>
  <?php if ($chart): ?><div class="chart-wrap"><canvas id="pageChart"></canvas></div><?php else: ?><div class="empty small"><p>Belum ada data untuk ditampilkan.</p></div><?php endif; ?>
</section>
<?php endif; ?>

<?php if ($m === 'growth' && $chart): ?>
<section class="card"><h3>Analisis Data (Python)</h3>
<?php if (!$analysis): ?><p class="muted">Modul analisis Python tidak tersedia saat ini. Pencatatan dan grafik tetap berfungsi normal.</p>
<?php else: ?>
  <div class="table-wrap"><table><thead><tr><th>Batch</th><th>Data</th><th>Berat Awal → Akhir (g)</th><th>Perubahan</th><th>Laju (g/hari)</th><th>Tren</th><th>Estimasi Berat Sampel</th></tr></thead><tbody>
  <?php foreach ($analysis['batches'] as $a): ?><tr>
    <td><?= e($a['code']) ?></td><td><?= (int)$a['n_logs'] ?></td>
    <td><?= isset($a['first_weight']) ? e(fmt_num($a['first_weight'], 3) . ' → ' . fmt_num($a['last_weight'], 3)) : '-' ?></td>
    <td><?= isset($a['change_g']) ? e(fmt_num($a['change_g'], 3) . ' g (' . fmt_num($a['change_pct'], 1) . '%)') : '-' ?></td>
    <td><?= isset($a['slope_g_per_day']) ? e(fmt_num($a['slope_g_per_day'], 4)) : '-' ?></td>
    <td><span class="badge"><?= e($a['trend']) ?></span></td>
    <td><?php if ($a['projection']): ?>+3 hari: <?= e(fmt_num($a['projection']['days_3'], 3)) ?> g, +7 hari: <?= e(fmt_num($a['projection']['days_7'], 3)) ?> g<br><small class="muted"><?= e($a['projection']['note']) ?></small>
      <?php else: ?><small class="muted"><?= e($a['note'] ?? 'Data belum cukup.') ?></small><?php endif; ?></td>
  </tr><?php endforeach; ?></tbody></table></div>
  <?php if (count($analysis['ranking']) > 1): ?><p class="muted">Peringkat laju pertumbuhan: <?= e(implode(' > ', array_map(fn($r) => $r['code'] . ' (' . fmt_num($r['slope_g_per_day'], 4) . ' g/hari)', $analysis['ranking']))) ?></p><?php endif; ?>
<?php endif; ?></section>
<?php endif; ?>

<section class="card">
<?php if (!$rows): ?>
  <div class="empty"><i data-lucide="<?= e($mod['icon']) ?>"></i><h3>Belum ada data</h3><p>Klik "Tambah Data" untuk mulai mencatat.</p></div>
<?php else: ?>
<div class="table-wrap"><table><thead><tr>
  <?php if ($m !== 'batches'): ?><th>Batch</th><?php else: ?><th>ID Batch</th><?php endif; ?>
  <?php foreach ($mod['fields'] as $f): if ($f['name'] === 'batch_id') continue; ?><th><?= e($f['label']) ?></th><?php endforeach; ?>
  <?php if ($m === 'growth'): ?><th>Foto</th><?php endif; ?><th class="act">Aksi</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr>
  <td><b><?= e($r['batch_code']) ?></b></td>
  <?php foreach ($mod['fields'] as $f): if ($f['name'] === 'batch_id') continue; $v = $r[$f['name']] ?? null; ?>
    <td class="<?= $f['type'] === 'textarea' ? 'ellipsis' : '' ?>"><?php
      if ($v === null || $v === '') echo '-';
      elseif ($f['type'] === 'date') echo e(fmt_date($v));
      elseif ($f['type'] === 'select') echo '<span class="badge s-' . e(str_replace(' ', '-', $v)) . '">' . e($f['options'][$v] ?? $v) . '</span>';
      elseif ($f['type'] === 'number') echo e(rtrim(rtrim(number_format((float)$v, 3, ',', '.'), '0'), ','));
      elseif ($f['type'] === 'int') echo e(number_format((int)$v, 0, ',', '.'));
      else echo e($v); ?></td>
  <?php endforeach; ?>
  <?php if ($m === 'growth'): ?><td><?php foreach ($photos[$r['id']] ?? [] as $ph): ?><a href="uploads/<?= e($ph) ?>" target="_blank" rel="noopener"><img class="thumb" src="uploads/<?= e($ph) ?>" alt="Foto"></a><?php endforeach; ?></td><?php endif; ?>
  <td class="act"><button class="icon-btn edit" data-row="<?= e(json_encode($r, JSON_UNESCAPED_UNICODE)) ?>" title="Edit"><i data-lucide="pencil"></i></button>
      <button class="icon-btn del" data-id="<?= (int)$r['id'] ?>" title="Hapus"><i data-lucide="trash-2"></i></button></td>
</tr><?php endforeach; ?></tbody></table></div>
<?php endif; ?>
</section>

<div class="modal" id="modal"><div class="modal-box">
  <div class="modal-head"><h3 id="modalTitle">Tambah Data</h3><button class="icon-btn" data-close><i data-lucide="x"></i></button></div>
  <form method="post" enctype="multipart/form-data" id="dataForm" class="form grid2"><?= csrf_field() ?>
    <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="0">
    <?php foreach ($mod['fields'] as $f): if (($f['form'] ?? true) === false) continue; $req = !empty($f['required']) ? 'required' : ''; ?>
      <label class="<?= $f['type'] === 'textarea' ? 'full' : '' ?>"><?= e($f['label']) ?><?= $req ? ' *' : '' ?>
      <?php if ($f['type'] === 'batch'): ?>
        <select name="batch_id" required><option value="">- pilih batch -</option><?php foreach ($batches as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['batch_code'] . ' - ' . $b['name']) ?></option><?php endforeach; ?></select>
      <?php elseif ($f['type'] === 'select'): ?>
        <select name="<?= e($f['name']) ?>" <?= $req ?>><?php if (!$req): ?><option value="">-</option><?php endif; foreach ($f['options'] as $k => $lbl): ?><option value="<?= e($k) ?>"><?= e($lbl) ?></option><?php endforeach; ?></select>
      <?php elseif ($f['type'] === 'textarea'): ?>
        <textarea name="<?= e($f['name']) ?>" rows="3" maxlength="<?= (int)($f['max'] ?? 1000) ?>"></textarea>
      <?php elseif ($f['type'] === 'date'): ?>
        <input type="date" name="<?= e($f['name']) ?>" <?= $req ?> <?= $req ? 'data-today="1"' : '' ?>>
      <?php elseif ($f['type'] === 'int' || $f['type'] === 'number'): ?>
        <input type="number" name="<?= e($f['name']) ?>" <?= $req ?> step="<?= $f['type'] === 'int' ? '1' : 'any' ?>" min="<?= e($f['min'] ?? '') ?>" max="<?= e($f['max'] ?? '') ?>">
      <?php else: ?>
        <input type="text" name="<?= e($f['name']) ?>" <?= $req ?> maxlength="<?= (int)($f['max'] ?? 150) ?>">
      <?php endif; ?></label>
    <?php endforeach; ?>
    <?php if ($m === 'growth'): ?>
      <label>Foto Perkembangan (JPG/PNG/WebP, maks. 2 MB)<input type="file" name="photo" accept="image/jpeg,image/png,image/webp"></label>
      <label>Keterangan Foto<input type="text" name="caption" maxlength="150"></label>
    <?php endif; ?>
    <div class="full actions"><button type="button" class="btn" data-close>Batal</button><button class="btn primary">Simpan</button></div>
  </form>
</div></div>
<form method="post" id="delForm"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="0"></form>
<?php endif;
page_footer();
