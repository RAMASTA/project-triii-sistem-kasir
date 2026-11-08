<?php
declare(strict_types=1);

/** Cari python.exe dari jalur yang terkontrol (config + lokasi instalasi standar). Tidak memakai shell. */
function python_binary(): ?string {
    $c = [cfg()['python_path'] ?? ''];
    foreach (['C:\\Python313', 'C:\\Python312', 'C:\\Python311', 'C:\\Python310'] as $d) $c[] = $d . '\\python.exe';
    $local = getenv('LOCALAPPDATA');
    if ($local) foreach (glob($local . '\\Programs\\Python\\Python3*\\python.exe') ?: [] as $g) $c[] = $g;
    foreach (['/usr/bin/python3', '/usr/local/bin/python3'] as $u) $c[] = $u;
    foreach ($c as $p) if ($p !== '' && is_file($p)) return $p;
    return null;
}

/** Jalankan growth_analysis.py. Mengembalikan array hasil atau null jika Python tidak tersedia/gagal. */
function run_growth_analysis(array $batches): ?array {
    $py = python_binary();
    $script = realpath(__DIR__ . '/../python/growth_analysis.py');
    if (!$py || !$script || !function_exists('proc_open')) return null;

    $clean = [];
    foreach ($batches as $b) {                     // validasi input: hanya angka/teks pendek
        $logs = [];
        foreach ($b['logs'] as $l) {
            if (!is_numeric($l['age']) || !is_numeric($l['weight'])) continue;
            $logs[] = ['age' => (int)$l['age'], 'weight' => (float)$l['weight']];
        }
        $clean[] = ['id' => (int)$b['id'], 'code' => mb_substr((string)$b['code'], 0, 30), 'name' => mb_substr((string)$b['name'], 0, 100), 'logs' => $logs];
    }
    $in = tempnam(sys_get_temp_dir(), 'mti'); $out = tempnam(sys_get_temp_dir(), 'mto');
    file_put_contents($in, json_encode(['batches' => $clean]));
    $result = null;
    try {
        $proc = @proc_open([$py, $script], [0 => ['file', $in, 'r'], 1 => ['file', $out, 'w'], 2 => ['file', $out . '.err', 'w']],
            $pipes, null, null, ['bypass_shell' => true]);
        if (is_resource($proc)) {
            $start = microtime(true); $timeout = (int)(cfg()['python_timeout'] ?? 8);
            while (proc_get_status($proc)['running']) {
                if (microtime(true) - $start > $timeout) { proc_terminate($proc); break; }
                usleep(50000);
            }
            proc_close($proc);
            if (is_file($out) && filesize($out) > 0 && filesize($out) < 1048576) {
                $j = json_decode((string)file_get_contents($out), true);
                if (is_array($j) && ($j['ok'] ?? false) === true) $result = $j;
            }
        }
    } finally {
        foreach ([$in, $out, $out . '.err'] as $f) if (is_file($f)) @unlink($f);
    }
    return $result;
}
