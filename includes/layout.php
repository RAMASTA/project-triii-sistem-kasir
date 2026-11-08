<?php
declare(strict_types=1);

function nav_items(): array {
    return [
        ['dashboard.php', 'dashboard', 'Dashboard', 'layout-dashboard'],
        ['module.php?m=batches', 'batches', 'Data Batch', 'layers'],
        ['module.php?m=growth', 'growth', 'Pertumbuhan', 'trending-up'],
        ['module.php?m=feedings', 'feedings', 'Pemberian Pakan', 'utensils'],
        ['module.php?m=environment', 'environment', 'Lingkungan', 'thermometer'],
        ['schedule.php', 'schedule', 'Jadwal Panen', 'calendar-check'],
        ['module.php?m=harvests', 'harvests', 'Hasil Panen', 'package'],
        ['reports.php', 'reports', 'Laporan', 'bar-chart-3'],
    ];
}

function head_tags(string $title): void { ?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> | MaggotTrack</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/style.css">
<script>try{var t=localStorage.getItem('mt-theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}</script>
</head>
<?php }

function page_header(string $title, string $active): void {
    head_tags($title);
    $notes = notifications();
    $name = $_SESSION['name'] ?? 'User'; ?>
<body>
<div class="app">
  <aside class="sidebar" id="sidebar">
    <div class="brand"><span class="logo"><i data-lucide="bug"></i></span><div><b>MaggotTrack</b><small>Monitor Growth. Manage Waste. Maximize Harvest.</small></div></div>
    <nav>
      <?php foreach (nav_items() as [$href, $key, $label, $icon]): ?>
        <a href="<?= e($href) ?>" class="<?= $key === $active ? 'active' : '' ?>"><i data-lucide="<?= e($icon) ?>"></i><span><?= e($label) ?></span></a>
      <?php endforeach; ?>
    </nav>
  </aside>
  <div class="overlay" id="overlay"></div>
  <div class="main">
    <header class="topbar">
      <button class="icon-btn menu-btn" id="menuBtn" aria-label="Menu"><i data-lucide="menu"></i></button>
      <div class="grow"></div>
      <button class="icon-btn" id="themeBtn" aria-label="Mode gelap"><i data-lucide="moon"></i></button>
      <details class="notif">
        <summary class="icon-btn" aria-label="Notifikasi"><i data-lucide="bell"></i><?php if ($notes): ?><span class="dot"><?= count($notes) ?></span><?php endif; ?></summary>
        <div class="notif-box">
          <strong>Notifikasi Panen</strong>
          <?php if (!$notes): ?><p class="muted">Tidak ada batch yang mendekati jadwal panen.</p><?php endif; ?>
          <?php foreach ($notes as $n): $d = (int)$n['days_left']; ?>
            <div class="notif-item"><b><?= e($n['batch_code']) ?></b> <?= e($n['name']) ?><br>
              <small><?= $d < 0 ? 'Terlambat ' . abs($d) . ' hari dari target' : ($d === 0 ? 'Target panen hari ini' : "Target panen $d hari lagi") ?></small></div>
          <?php endforeach; ?>
        </div>
      </details>
      <div class="profile"><span class="avatar"><?= e(mb_strtoupper(mb_substr($name, 0, 1))) ?></span><span class="pname"><?= e($name) ?><small><?= is_admin() ? 'Admin' : 'Staff' ?></small></span></div>
      <form method="post" action="logout.php"><?= csrf_field() ?><button class="icon-btn" title="Keluar"><i data-lucide="log-out"></i></button></form>
    </header>
    <main class="content">
      <?php foreach (take_flash() as [$t, $m]): ?><div class="alert <?= e($t) ?>"><?= e($m) ?></div><?php endforeach; ?>
<?php }

function page_footer(): void { ?>
    </main>
  </div>
</div>
<script src="https://unpkg.com/lucide@0.263.1/dist/umd/lucide.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script src="assets/js/app.js"></script>
<?php if (!empty($GLOBALS['page_script'])) echo $GLOBALS['page_script']; ?>
</body>
</html>
<?php }

function auth_head(string $title): void { head_tags($title); echo '<body class="auth"><div class="auth-card"><div class="brand center"><span class="logo"><i data-lucide="bug"></i></span><div><b>MaggotTrack</b><small>Monitor Growth. Manage Waste. Maximize Harvest.</small></div></div>'; }
function auth_foot(): void { echo '</div><script src="https://unpkg.com/lucide@0.263.1/dist/umd/lucide.min.js"></script><script src="assets/js/app.js"></script></body></html>'; }

function json_for_js($v): string { return json_encode($v, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); }
