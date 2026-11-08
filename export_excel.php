<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();
require __DIR__ . '/includes/report_data.php';
$rb = report_batches(); $rm = report_monthly();
header('Content-Type: application/vnd.ms-excel; charset=utf-8');
header('Content-Disposition: attachment; filename="laporan_maggottrack_' . date('Ymd') . '.xls"');
echo "\xEF\xBB\xBF<html><head><meta charset=\"utf-8\"></head><body>";
echo '<h3>MaggotTrack - Laporan Produksi (' . e(date('d M Y')) . ')</h3><table border="1"><tr><th>ID Batch</th><th>Nama</th><th>Status</th><th>Populasi Awal</th><th>Pakan (kg)</th><th>Panen (kg)</th><th>Target (kg)</th></tr>';
foreach ($rb as $r) echo '<tr><td>' . e($r['batch_code']) . '</td><td>' . e($r['name']) . '</td><td>' . e($r['status']) . '</td><td>' . (int)$r['initial_population'] . '</td><td>' . (float)$r['feed_kg'] . '</td><td>' . (float)$r['harvest_kg'] . '</td><td>' . e($r['target_harvest_kg']) . '</td></tr>';
echo '</table><br><h3>Rekap Bulanan</h3><table border="1"><tr><th>Bulan</th><th>Pakan (kg)</th><th>Panen (kg)</th></tr>';
foreach ($rm as $ym => $x) echo '<tr><td>' . e($ym) . '</td><td>' . ($x['feed'] ?? 0) . '</td><td>' . ($x['harvest'] ?? 0) . '</td></tr>';
echo '</table></body></html>';
