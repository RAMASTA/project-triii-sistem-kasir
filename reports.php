<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
require __DIR__ . '/includes/layout.php';
require __DIR__ . '/includes/report_data.php';
$rb = report_batches(); $rm = report_monthly();
$tf = array_sum(array_column($rb, 'feed_kg')); $th = array_sum(array_column($rb, 'harvest_kg'));
$GLOBALS['page_script'] = '<script>window.MT_PAGE=' . json_for_js([
  'monthly' => ['labels' => array_keys($rm), 'feed' => array_map(fn($x) => $x['feed'] ?? 0, array_values($rm)), 'harvest' => array_map(fn($x) => $x['harvest'] ?? 0, array_values($rm))],
  'batches' => ['labels' => array_column($rb, 'batch_code'), 'feed' => array_map('floatval', array_column($rb, 'feed_kg')), 'harvest' => array_map('floatval', array_column($rb, 'harvest_kg'))],
]) . ';</script>';
page_header('Laporan', 'reports'); ?>
<div class="page-head"><div><h1>Laporan</h1><p class="muted">Statistik produksi dan perbandingan antarbatch.</p></div>
  <div class="actions noprint"><a class="btn" href="export_excel.php"><i data-lucide="bar-chart-3"></i> Export Excel</a><button class="btn primary" onclick="window.print()">Export PDF</button></div></div>
<div class="stats">
  <div class="stat"><i data-lucide="layers"></i><span>Total Batch</span><b><?= count($rb) ?></b></div>
  <div class="stat"><i data-lucide="utensils"></i><span>Total Pakan</span><b><?= fmt_num($tf) ?> kg</b></div>
  <div class="stat"><i data-lucide="package"></i><span>Total Panen</span><b><?= fmt_num($th) ?> kg</b></div>
  <div class="stat"><i data-lucide="trending-up"></i><span>Panen per kg Pakan</span><b><?= $tf > 0 ? fmt_num($th / $tf, 3) : '-' ?></b></div>
</div>
<?php if (!$rb): ?><section class="card"><div class="empty"><i data-lucide="bar-chart-3"></i><h3>Belum ada data laporan</h3><p>Tambahkan batch, pakan, dan hasil panen terlebih dahulu.</p></div></section>
<?php else: ?>
<div class="grid-2">
  <section class="card"><h3>Pakan dan Panen per Bulan (kg)</h3><?php if ($rm): ?><div class="chart-wrap"><canvas id="monthlyChart"></canvas></div><?php else: ?><div class="empty small"><p>Belum ada data.</p></div><?php endif; ?></section>
  <section class="card"><h3>Perbandingan Batch (kg)</h3><div class="chart-wrap"><canvas id="batchChart"></canvas></div></section>
</div>
<section class="card"><h3>Perbandingan Batch</h3><div class="table-wrap"><table><thead><tr><th>ID Batch</th><th>Nama</th><th>Status</th><th>Populasi Awal</th><th>Pakan (kg)</th><th>Panen (kg)</th><th>Target (kg)</th><th>Capaian</th><th>Panen/Pakan</th></tr></thead><tbody>
<?php foreach ($rb as $r): ?><tr><td><b><?= e($r['batch_code']) ?></b></td><td><?= e($r['name']) ?></td><td><span class="badge s-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
<td><?= fmt_num($r['initial_population'], 0) ?></td><td><?= fmt_num($r['feed_kg']) ?></td><td><?= fmt_num($r['harvest_kg']) ?></td>
<td><?= $r['target_harvest_kg'] !== null ? fmt_num($r['target_harvest_kg']) : '-' ?></td>
<td><?= (float)$r['target_harvest_kg'] > 0 ? fmt_num($r['harvest_kg'] / $r['target_harvest_kg'] * 100, 1) . '%' : '-' ?></td>
<td><?= (float)$r['feed_kg'] > 0 ? fmt_num($r['harvest_kg'] / $r['feed_kg'], 3) : '-' ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<?php endif; page_footer();
