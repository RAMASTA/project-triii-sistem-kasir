<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
require __DIR__ . '/includes/layout.php';
[$w, $p] = batch_scope('b');
$one = function (string $sql, array $params) { $s = db()->prepare($sql); $s->execute($params); return $s->fetch(); };

$a = $one("SELECT COUNT(*) c, COALESCE(SUM(b.initial_population),0) pop FROM batches b WHERE $w AND b.status IN ('aktif','siap_panen')", $p);
$feed = (float)$one("SELECT COALESCE(SUM(t.weight_kg),0) v FROM feedings t JOIN batches b ON b.id = t.batch_id WHERE $w", $p)['v'];
$harv = (float)$one("SELECT COALESCE(SUM(t.weight_kg),0) v FROM harvests t JOIN batches b ON b.id = t.batch_id WHERE $w", $p)['v'];

$s = db()->prepare("SELECT t.batch_id, b.batch_code, t.age_days, t.sample_weight_g FROM growth_logs t JOIN batches b ON b.id = t.batch_id WHERE $w ORDER BY t.batch_id, t.age_days");
$s->execute($p); $by = [];
foreach ($s->fetchAll() as $r) { $by[$r['batch_id']]['label'] = $r['batch_code']; $by[$r['batch_id']]['data'][] = ['x' => (int)$r['age_days'], 'y' => (float)$r['sample_weight_g']]; }
$growth = array_values($by);

$s = db()->prepare("SELECT t.feed_date d, SUM(t.weight_kg) kg FROM feedings t JOIN batches b ON b.id = t.batch_id WHERE $w AND t.feed_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) GROUP BY t.feed_date ORDER BY t.feed_date");
$s->execute($p); $fr = $s->fetchAll();

$s = db()->prepare("SELECT * FROM (
   SELECT b.created_at ts, 'Batch baru' kind, CONCAT(b.batch_code, ' - ', b.name) info FROM batches b WHERE $w
   UNION ALL SELECT t.created_at, 'Pakan', CONCAT(b.batch_code, ': ', t.feed_type, ' ', t.weight_kg, ' kg') FROM feedings t JOIN batches b ON b.id = t.batch_id WHERE $w
   UNION ALL SELECT t.created_at, 'Pertumbuhan', CONCAT(b.batch_code, ': ', t.sample_weight_g, ' g, umur ', t.age_days, ' hari') FROM growth_logs t JOIN batches b ON b.id = t.batch_id WHERE $w
   UNION ALL SELECT t.created_at, 'Lingkungan', CONCAT(b.batch_code, ': ', t.temperature_c, ' °C, ', t.humidity_pct, '%') FROM environment_logs t JOIN batches b ON b.id = t.batch_id WHERE $w
   UNION ALL SELECT t.created_at, 'Panen', CONCAT(b.batch_code, ': ', t.weight_kg, ' kg') FROM harvests t JOIN batches b ON b.id = t.batch_id WHERE $w
 ) x ORDER BY ts DESC LIMIT 8");
$s->execute([...$p, ...$p, ...$p, ...$p, ...$p]); $act = $s->fetchAll();

$GLOBALS['page_script'] = '<script>window.MT_PAGE=' . json_for_js(['growth' => $growth, 'feed' => ['labels' => array_column($fr, 'd'), 'values' => array_map('floatval', array_column($fr, 'kg'))]]) . ';</script>';
page_header('Dashboard', 'dashboard'); ?>
<div class="page-head"><div><h1>Dashboard</h1><p class="muted">Ringkasan data budidaya dari database.</p></div></div>
<div class="stats">
  <div class="stat"><i data-lucide="layers"></i><span>Batch Aktif</span><b><?= (int)$a['c'] ?></b></div>
  <div class="stat"><i data-lucide="bug"></i><span>Estimasi Populasi (populasi awal batch aktif)</span><b><?= fmt_num($a['pop'], 0) ?></b></div>
  <div class="stat"><i data-lucide="utensils"></i><span>Total Pakan</span><b><?= fmt_num($feed) ?> kg</b></div>
  <div class="stat"><i data-lucide="package"></i><span>Total Hasil Panen</span><b><?= fmt_num($harv) ?> kg</b></div>
</div>
<div class="grid-2">
  <section class="card"><h3>Pertumbuhan (berat sampel vs umur)</h3>
    <?php if ($growth): ?><div class="chart-wrap"><canvas id="growthChart"></canvas></div><?php else: ?><div class="empty small"><p>Belum ada data pertumbuhan.</p></div><?php endif; ?></section>
  <section class="card"><h3>Konsumsi Pakan (30 hari terakhir)</h3>
    <?php if ($fr): ?><div class="chart-wrap"><canvas id="feedChart"></canvas></div><?php else: ?><div class="empty small"><p>Belum ada data pakan.</p></div><?php endif; ?></section>
</div>
<section class="card"><h3>Aktivitas Terbaru</h3>
  <?php if (!$act): ?><div class="empty small"><p>Belum ada aktivitas.</p></div><?php else: ?>
  <ul class="activity"><?php foreach ($act as $r): ?><li><span class="badge"><?= e($r['kind']) ?></span> <?= e($r['info']) ?><small class="muted"><?= e(date('d M Y H:i', strtotime($r['ts']))) ?></small></li><?php endforeach; ?></ul><?php endif; ?>
</section>
<?php page_footer();
