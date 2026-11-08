<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
require __DIR__ . '/includes/layout.php';
[$w, $p] = batch_scope('b');
$s = db()->prepare("SELECT b.batch_code, b.name, b.status, b.hatch_date, b.target_harvest_date,
   DATEDIFF(CURDATE(), b.hatch_date) AS age, DATEDIFF(b.target_harvest_date, CURDATE()) AS days_left
   FROM batches b WHERE $w AND b.status IN ('aktif','siap_panen') ORDER BY b.target_harvest_date IS NULL, b.target_harvest_date");
$s->execute($p); $rows = $s->fetchAll();
page_header('Jadwal Panen', 'schedule'); ?>
<div class="page-head"><div><h1>Jadwal Panen</h1><p class="muted">Berdasarkan tanggal target panen yang Anda isi pada Data Batch. Sistem tidak membuat prediksi tanpa dasar data.</p></div></div>
<section class="card">
<?php if (!$rows): ?><div class="empty"><i data-lucide="calendar-check"></i><h3>Tidak ada batch aktif</h3><p>Batch dengan status Aktif atau Siap Panen akan tampil di sini.</p></div>
<?php else: ?><div class="table-wrap"><table><thead><tr><th>Batch</th><th>Umur</th><th>Target Panen</th><th>Sisa Hari</th><th>Status Kesiapan</th></tr></thead><tbody>
<?php foreach ($rows as $r):
  $dl = $r['days_left']; $left = $dl === null ? '-' : ((int)$dl < 0 ? 'Lewat ' . abs((int)$dl) . ' hari' : $dl . ' hari');
  if ($r['status'] === 'siap_panen') [$lbl, $cls] = ['Siap panen (ditandai)', 's-ok'];
  elseif ($dl === null) [$lbl, $cls] = ['Target belum diatur', ''];
  elseif ((int)$dl < 0) [$lbl, $cls] = ['Melewati target', 's-bad'];
  elseif ((int)$dl <= 3) [$lbl, $cls] = ['Segera panen', 's-warn'];
  else [$lbl, $cls] = ['Belum siap', '']; ?>
<tr><td><b><?= e($r['batch_code']) ?></b> <?= e($r['name']) ?></td>
<td><?= $r['age'] !== null && (int)$r['age'] >= 0 ? (int)$r['age'] . ' hari' : '<span class="muted">tanggal penetasan belum diisi</span>' ?></td>
<td><?= e(fmt_date($r['target_harvest_date'])) ?></td><td><?= e($left) ?></td><td><span class="badge <?= $cls ?>"><?= e($lbl) ?></span></td></tr>
<?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<?php page_footer();
