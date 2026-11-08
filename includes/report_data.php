<?php
declare(strict_types=1);

function report_batches(): array {
    [$w, $p] = batch_scope('b');
    $s = db()->prepare("SELECT b.batch_code, b.name, b.status, b.initial_population, b.target_harvest_kg,
        (SELECT COALESCE(SUM(weight_kg),0) FROM feedings WHERE batch_id = b.id) AS feed_kg,
        (SELECT COALESCE(SUM(weight_kg),0) FROM harvests WHERE batch_id = b.id) AS harvest_kg
        FROM batches b WHERE $w ORDER BY b.id");
    $s->execute($p);
    return $s->fetchAll();
}

function report_monthly(): array {
    [$w, $p] = batch_scope('b');
    $m = [];
    foreach (['feed' => ['feedings', 'feed_date'], 'harvest' => ['harvests', 'harvest_date']] as $k => [$t, $d]) {
        $s = db()->prepare("SELECT DATE_FORMAT(t.$d, '%Y-%m') ym, SUM(t.weight_kg) kg FROM $t t JOIN batches b ON b.id = t.batch_id WHERE $w GROUP BY ym");
        $s->execute($p);
        foreach ($s->fetchAll() as $r) $m[$r['ym']][$k] = (float)$r['kg'];
    }
    ksort($m);
    return $m;
}
