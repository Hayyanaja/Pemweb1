<?php
define('API_MODE', true);
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

class InputError extends Exception
{
}

$uid = user_id();
if ($uid === null) {
    json_out(['ok' => false, 'error' => 'Sesi berakhir. Silakan masuk lagi.'], 401);
}

function teks($nilai, $min, $maks, $label)
{
    $v = trim((string)$nilai);
    $panjang = function_exists('mb_strlen') ? mb_strlen($v, 'UTF-8') : strlen($v);
    if ($panjang < $min) {
        throw new InputError($label . ' wajib diisi.');
    }
    if ($panjang > $maks) {
        throw new InputError($label . ' maksimal ' . $maks . ' karakter.');
    }
    return $v;
}

function tanggal($s)
{
    if (!is_string($s) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s, $m)
        || !checkdate((int)$m[2], (int)$m[3], (int)$m[1])) {
        throw new InputError('Format deadline tidak valid.');
    }
    return $s;
}

function matkul_milik(PDO $pdo, $uid, $id)
{
    if ($id === null || $id === '' || (int)$id === 0) {
        return null;
    }
    $st = $pdo->prepare('SELECT id FROM matkul WHERE id = ? AND user_id = ?');
    $st->execute([(int)$id, $uid]);
    if (!$st->fetch()) {
        throw new InputError('Mata kuliah tidak ditemukan.', 404);
    }
    return (int)$id;
}

function tugas_milik(PDO $pdo, $uid, $id)
{
    $st = $pdo->prepare('SELECT id FROM tasks WHERE id = ? AND user_id = ?');
    $st->execute([(int)$id, $uid]);
    if (!$st->fetch()) {
        throw new InputError('Tugas tidak ditemukan.', 404);
    }
    return (int)$id;
}

function data_awal(PDO $pdo, $uid)
{
    $st = $pdo->prepare('SELECT id, matkul_id, judul, deskripsi, deadline, prioritas, status, deleted_at
                         FROM tasks WHERE user_id = ? ORDER BY id');
    $st->execute([$uid]);
    $tugas = [];
    foreach ($st->fetchAll() as $r) {
        $tugas[] = [
            'id'        => (int)$r['id'],
            'judul'     => $r['judul'],
            'mk'        => $r['matkul_id'] !== null ? (int)$r['matkul_id'] : null,
            'deskripsi' => (string)$r['deskripsi'],
            'deadline'  => $r['deadline'],
            'prioritas' => $r['prioritas'],
            'selesai'   => $r['status'] === 'Selesai',
            'dihapus'   => $r['deleted_at'] !== null,
        ];
    }

    $st = $pdo->prepare('SELECT id, nama, warna FROM matkul WHERE user_id = ? ORDER BY nama');
    $st->execute([$uid]);
    $matkul = [];
    foreach ($st->fetchAll() as $r) {
        $matkul[] = ['id' => (int)$r['id'], 'nama' => $r['nama'], 'warna' => $r['warna']];
    }

    $pengaturan = ['aktif' => true, 'hari' => [0, 1, 3]];
    $st = $pdo->prepare('SELECT pengingat_aktif, pengingat_hari FROM pengaturan WHERE user_id = ?');
    $st->execute([$uid]);
    if ($r = $st->fetch()) {
        $pengaturan['aktif'] = (int)$r['pengingat_aktif'] === 1;
        $pengaturan['hari']  = $r['pengingat_hari'] === '' ? [] : array_map('intval', explode(',', $r['pengingat_hari']));
    }

    return ['ok' => true, 'tugas' => $tugas, 'matkul' => $matkul, 'pengaturan' => $pengaturan];
}

try {
    $pdo = db();
    $aksi = $_GET['action'] ?? '';

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        if ($aksi === 'init') {
            json_out(data_awal($pdo, $uid));
        }
        json_out(['ok' => false, 'error' => 'Aksi tidak dikenal.'], 404);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        json_out(['ok' => false, 'error' => 'Metode tidak diizinkan.'], 405);
    }
    if (!csrf_valid($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
        json_out(['ok' => false, 'error' => 'Token keamanan tidak valid. Muat ulang halaman.'], 403);
    }

    $in = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($in)) {
        $in = [];
    }

    switch ($aksi) {

        // ===== TUGAS: tambah & ubah =====
        case 'task_save':
            $judul     = teks($in['judul'] ?? '', 1, 60, 'Judul');
            $deskripsi = teks($in['deskripsi'] ?? '', 0, 1000, 'Deskripsi');
            $deadline  = tanggal($in['deadline'] ?? null);
            $prioritas = $in['prioritas'] ?? '';
            if (!in_array($prioritas, ['Low', 'Medium', 'High'], true)) {
                throw new InputError('Prioritas tidak valid.');
            }
            $matkulId = matkul_milik($pdo, $uid, $in['matkul_id'] ?? null);

            $id = (int)($in['id'] ?? 0);
            if ($id > 0) {
                tugas_milik($pdo, $uid, $id);
                $st = $pdo->prepare('UPDATE tasks SET judul = ?, matkul_id = ?, deskripsi = ?, deadline = ?, prioritas = ?
                                     WHERE id = ? AND user_id = ?');
                $st->execute([$judul, $matkulId, $deskripsi, $deadline, $prioritas, $id, $uid]);
            } else {
                $st = $pdo->prepare('INSERT INTO tasks (user_id, matkul_id, judul, deskripsi, deadline, prioritas)
                                     VALUES (?, ?, ?, ?, ?, ?)');
                $st->execute([$uid, $matkulId, $judul, $deskripsi, $deadline, $prioritas]);
                $id = (int)$pdo->lastInsertId();
            }
            json_out(['ok' => true, 'id' => $id]);
            break;

        case 'task_toggle':
            $id = tugas_milik($pdo, $uid, $in['id'] ?? 0);
            $st = $pdo->prepare("UPDATE tasks SET status = IF(status = 'Selesai', 'Pending', 'Selesai')
                                 WHERE id = ? AND user_id = ? AND deleted_at IS NULL");
            $st->execute([$id, $uid]);
            json_out(['ok' => true]);
            break;

        case 'task_trash':
            $id = tugas_milik($pdo, $uid, $in['id'] ?? 0);
            $st = $pdo->prepare('UPDATE tasks SET deleted_at = NOW() WHERE id = ? AND user_id = ? AND deleted_at IS NULL');
            $st->execute([$id, $uid]);
            json_out(['ok' => true]);
            break;

        case 'task_restore':
            $id = tugas_milik($pdo, $uid, $in['id'] ?? 0);
            $st = $pdo->prepare('UPDATE tasks SET deleted_at = NULL WHERE id = ? AND user_id = ?');
            $st->execute([$id, $uid]);
            json_out(['ok' => true]);
            break;

        case 'task_delete':
            $id = tugas_milik($pdo, $uid, $in['id'] ?? 0);
            $st = $pdo->prepare('DELETE FROM tasks WHERE id = ? AND user_id = ? AND deleted_at IS NOT NULL');
            $st->execute([$id, $uid]);
            json_out(['ok' => true]);
            break;

        case 'task_empty':
            $st = $pdo->prepare('DELETE FROM tasks WHERE user_id = ? AND deleted_at IS NOT NULL');
            $st->execute([$uid]);
            json_out(['ok' => true]);
            break;

        case 'mk_save':
            $nama  = teks($in['nama'] ?? '', 1, 40, 'Nama mata kuliah');
            $warna = (string)($in['warna'] ?? '');
            if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $warna)) {
                throw new InputError('Warna tidak valid.');
            }
            $id = (int)($in['id'] ?? 0);
            try {
                if ($id > 0) {
                    matkul_milik($pdo, $uid, $id);
                    $st = $pdo->prepare('UPDATE matkul SET nama = ?, warna = ? WHERE id = ? AND user_id = ?');
                    $st->execute([$nama, $warna, $id, $uid]);
                } else {
                    $st = $pdo->prepare('INSERT INTO matkul (user_id, nama, warna) VALUES (?, ?, ?)');
                    $st->execute([$uid, $nama, $warna]);
                    $id = (int)$pdo->lastInsertId();
                }
            } catch (PDOException $ex) {
                if ((int)($ex->errorInfo[1] ?? 0) === 1062) {
                    throw new InputError('Nama mata kuliah sudah ada.');
                }
                throw $ex;
            }
            json_out(['ok' => true, 'id' => $id]);
            break;

        case 'mk_delete':
            $id = matkul_milik($pdo, $uid, $in['id'] ?? 0);
            if ($id === null) {
                throw new InputError('Mata kuliah tidak ditemukan.', 404);
            }
            $st = $pdo->prepare('SELECT COUNT(*) FROM tasks WHERE matkul_id = ? AND user_id = ?');
            $st->execute([$id, $uid]);
            $dipakai = (int)$st->fetchColumn();
            if ($dipakai > 0) {
                throw new InputError('Tidak bisa dihapus: masih dipakai ' . $dipakai . ' tugas.');
            }
            $st = $pdo->prepare('DELETE FROM matkul WHERE id = ? AND user_id = ?');
            $st->execute([$id, $uid]);
            json_out(['ok' => true]);
            break;

        case 'settings_save':
            $aktifFlag = !empty($in['aktif']) ? 1 : 0;
            $hari = [];
            if (isset($in['hari']) && is_array($in['hari'])) {
                foreach ($in['hari'] as $h) {
                    $h = (int)$h;
                    if ($h >= 0 && $h <= 30) {
                        $hari[$h] = $h;
                    }
                }
            }
            ksort($hari);
            $teksHari = implode(',', array_values($hari));
            $st = $pdo->prepare('INSERT INTO pengaturan (user_id, pengingat_aktif, pengingat_hari) VALUES (?, ?, ?)
                                 ON DUPLICATE KEY UPDATE pengingat_aktif = ?, pengingat_hari = ?');
            $st->execute([$uid, $aktifFlag, $teksHari, $aktifFlag, $teksHari]);
            json_out(['ok' => true]);
            break;

        default:
            json_out(['ok' => false, 'error' => 'Aksi tidak dikenal.'], 404);
    }
} catch (InputError $ex) {
    json_out(['ok' => false, 'error' => $ex->getMessage()], $ex->getCode() ?: 422);
} catch (Throwable $ex) {
    error_log('API error: ' . $ex->getMessage());
    json_out(['ok' => false, 'error' => 'Terjadi kesalahan pada server.'], 500);
}
