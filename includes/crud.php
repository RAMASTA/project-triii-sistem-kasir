<?php
declare(strict_types=1);

function owned_batches(): array {
    [$w, $p] = batch_scope('b');
    $s = db()->prepare("SELECT b.id, b.batch_code, b.name, b.hatch_date, b.status FROM batches b WHERE $w ORDER BY b.id DESC");
    $s->execute($p);
    return $s->fetchAll();
}

/** Filter kepemilikan untuk UPDATE/DELETE (kolom tanpa alias). */
function own_filter(string $table): array {
    if (is_admin()) return ['', []];
    if ($table === 'batches') return [' AND user_id = ?', [uid()]];
    return [' AND batch_id IN (SELECT id FROM batches WHERE user_id = ?)', [uid()]];
}

function validate_fields(array $fields, array $in, array &$errors): array {
    $out = [];
    foreach ($fields as $f) {
        if (($f['form'] ?? true) === false) continue;
        $n = $f['name']; $label = $f['label'];
        $raw = $in[$n] ?? '';
        $v = is_string($raw) ? trim($raw) : '';
        if ($v === '') { if (!empty($f['required'])) $errors[] = "$label wajib diisi."; $out[$n] = null; continue; }
        switch ($f['type']) {
            case 'text': case 'textarea':
                $max = $f['max'] ?? 150;
                if (mb_strlen($v) > $max) $errors[] = "$label maksimal $max karakter.";
                $out[$n] = $v; break;
            case 'int': case 'batch':
                if (!preg_match('/^\d+$/', $v)) { $errors[] = "$label harus bilangan bulat."; break; }
                if (isset($f['min']) && (int)$v < $f['min']) $errors[] = "$label minimal {$f['min']}.";
                if (isset($f['max']) && (int)$v > $f['max']) $errors[] = "$label maksimal {$f['max']}.";
                $out[$n] = (int)$v; break;
            case 'number':
                $v = str_replace(',', '.', $v);
                if (!is_numeric($v) || !is_finite((float)$v)) { $errors[] = "$label harus berupa angka."; break; }
                if (isset($f['min']) && (float)$v < $f['min']) $errors[] = "$label minimal {$f['min']}.";
                if (isset($f['max']) && (float)$v > $f['max']) $errors[] = "$label maksimal {$f['max']}.";
                $out[$n] = $v; break;
            case 'date':
                $d = DateTime::createFromFormat('Y-m-d', $v);
                if (!$d || $d->format('Y-m-d') !== $v) { $errors[] = "$label tidak valid."; break; }
                $out[$n] = $v; break;
            case 'select':
                if (!array_key_exists($v, $f['options'])) { $errors[] = "$label tidak valid."; break; }
                $out[$n] = $v; break;
        }
    }
    return $out;
}

function save_photo(array $file, int $batchId, int $logId, ?string $caption): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) return 'Upload foto gagal.';
    if ($file['size'] > 2 * 1024 * 1024) return 'Ukuran foto maksimal 2 MB.';
    if (!@getimagesize($file['tmp_name'])) return 'File bukan gambar yang valid.';
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext) return 'Format foto harus JPG, PNG, atau WebP.';
    $name = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/../uploads/' . $name)) return 'Gagal menyimpan foto.';
    db()->prepare('INSERT INTO batch_photos (batch_id, growth_log_id, file_path, caption) VALUES (?,?,?,?)')
        ->execute([$batchId, $logId, $name, $caption ? mb_substr($caption, 0, 150) : null]);
    return null;
}

function delete_photo_files(string $col, int $id): void {
    $s = db()->prepare("SELECT file_path FROM batch_photos WHERE $col = ?");
    $s->execute([$id]);
    foreach ($s->fetchAll() as $r) { $p = __DIR__ . '/../uploads/' . basename($r['file_path']); if (is_file($p)) @unlink($p); }
}
