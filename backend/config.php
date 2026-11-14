<?php
// ===== config.php: koneksi database + fungsi bantu, dipakai semua file API =====

// 1. Semua jawaban berupa JSON, dan frontend di port lain boleh memanggil (CORS)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Authorization = token login
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit; // permintaan "izin" dari browser, cukup dijawab kosong

// 2. Koneksi ke MySQL (setelan bawaan XAMPP: user root, tanpa password)
$pdo = new PDO('mysql:host=localhost;dbname=db_lomba;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,        // error SQL jadi exception
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,   // hasil berupa array ['kolom' => nilai]
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_FOUND_ROWS => true,                 // UPDATE data yang sama tidak dianggap "tidak ketemu"
]);

// 3. Kirim jawaban JSON lalu berhenti
function kirim($data, $kode = 200) {
    http_response_code($kode);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 4. Ubah error MySQL (trigger / unique / foreign key) jadi pesan yang mudah dibaca
function errorDb(PDOException $e) {
    $kode = $e->errorInfo[1] ?? 0;
    $pesan = $e->errorInfo[2] ?? $e->getMessage();
    if ($kode == 1062) { // data kembar
        if (strpos($pesan, 'unik_nim') !== false) kirim(['error' => 'NIM sudah terdaftar'], 409);
        if (strpos($pesan, 'unik_pendaftaran') !== false) kirim(['error' => 'Peserta sudah terdaftar di kegiatan ini'], 409);
        if (strpos($pesan, 'unik_pembayaran') !== false) kirim(['error' => 'Pendaftaran ini sudah punya data pembayaran'], 409);
        kirim(['error' => 'Data duplikat'], 409);
    }
    if ($kode == 1451) kirim(['error' => 'Data masih dipakai di tabel lain'], 409);
    if ($kode == 1644) kirim(['error' => $pesan], 422); // pesan dari trigger: tanggal lewat / kuota penuh
    if ($kode == 3819 || $kode == 4025) kirim(['error' => 'Data tidak memenuhi aturan basis data (CHECK)'], 422); // MySQL / MariaDB
    kirim(['error' => $pesan], 400);
}

// 5. Login: token bertanda tangan (HMAC), dikirim frontend lewat header "Authorization: Bearer <token>"
//    shortcut: rahasia bawaan hanya untuk belajar; di luar kelas isi lewat environment APP_SECRET.
define('RAHASIA', getenv('APP_SECRET') ?: 'ganti-teks-rahasia-ini');
function b64($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }

function buatToken($u) { // $u = data pengguna (id, nama, peran, id_peserta, id_ormawa); berlaku 8 jam
    $isi = b64(json_encode($u + ['exp' => time() + 8 * 3600]));
    return $isi . '.' . b64(hash_hmac('sha256', $isi, RAHASIA, true));
}

function penggunaLogin() { // return data pengguna dari token, atau null kalau tidak login / token salah / kedaluwarsa
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? (function_exists('getallheaders') ? (getallheaders()['Authorization'] ?? '') : '');
    if (!preg_match('/^Bearer ([\w-]+)\.([\w-]+)$/', $h, $m)) return null;
    if (!hash_equals(b64(hash_hmac('sha256', $m[1], RAHASIA, true)), $m[2])) return null; // tanda tangan harus cocok
    $u = json_decode(base64_decode(strtr($m[1], '-_', '+/')), true);
    return ($u && $u['exp'] > time()) ? $u : null;
}

function wajibPeran($peran) { // hentikan request kalau belum login (401) atau perannya tidak boleh (403)
    $u = penggunaLogin();
    if (!$u) kirim(['error' => 'Login diperlukan'], 401);
    if (!in_array($u['peran'], $peran)) kirim(['error' => 'Anda tidak punya izin untuk aksi ini'], 403);
    return $u;
}

// Semua data wajib login (peran apa pun)
function wajibLogin() { return wajibPeran(['admin', 'ormawa', 'peserta']); }

// 6. SELECT khusus (JOIN): ?id= -> satu objek, selain itu daftar.
//    $sql harus diakhiri klausa WHERE (supaya " AND kolom = ?" bisa ditambahkan).
function ambil($sql, $args, $kolomId, $urut) {
    global $pdo;
    wajibLogin();
    try {
        if (!empty($_GET['id'])) {
            $st = $pdo->prepare("$sql AND $kolomId = ?");
            $st->execute([...$args, $_GET['id']]);
            $baris = $st->fetch();
            if (!$baris) kirim(['error' => 'Data tidak ditemukan'], 404);
            kirim($baris);
        }
        $st = $pdo->prepare("$sql ORDER BY $urut");
        $st->execute($args);
        kirim($st->fetchAll());
    } catch (PDOException $e) {
        errorDb($e);
    }
}

// 7. CRUD generik: GET (lihat/cari/satu data), POST (tambah), PUT (ubah), DELETE (hapus)
//    $kolom = kolom yang boleh diisi, $wajib = kolom yang harus ada saat POST, $default = nilai bawaan saat POST
//    $opsi  = 'peran'     peran yang boleh menulis (bawaan admin & ormawa)
//             'bukaPost'  true = POST boleh untuk peran apa pun yang login (mis. peserta mendaftar lomba)
//             'kolomStaf' kolom yang hanya boleh diisi staf saat POST (mis. status)
//             'milik'     kolom yang dipaksa berisi id_peserta pengguna kalau yang mengirim peserta
//             'baca'      peran yang boleh GET (bawaan semua yang login)
//             'cek'       fungsi validasi isi data; return pesan error atau null
function crud($tabel, $pk, $kolom, $wajib = [], $default = [], $opsi = []) {
    global $pdo;
    $metode = $_SERVER['REQUEST_METHOD'];
    $id = $_GET['id'] ?? null;
    $peran = $opsi['peran'] ?? ['admin', 'ormawa'];
    $cek = $opsi['cek'] ?? null;

    // semua akses butuh login; menulis hanya untuk $peran, kecuali POST yang dibuka untuk semua peran
    $staf = null; $u = null;
    if ($metode === 'GET') {
        wajibPeran($opsi['baca'] ?? ['admin', 'ormawa', 'peserta']);
    } else {
        if ($metode === 'POST' && !empty($opsi['bukaPost'])) {
            $u = wajibLogin();
            $staf = in_array($u['peran'], $peran);
        } else {
            wajibPeran($peran);
            $staf = true;
        }
    }

    // data dikirim frontend sebagai JSON; kolom yang tidak dikenal dibuang
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $data = array_intersect_key($data, array_flip($kolom));
    if ($metode === 'POST' && !$staf) {
        $data = array_diff_key($data, array_flip($opsi['kolomStaf'] ?? []));
        if (!empty($opsi['milik'])) $data[$opsi['milik']] = $u['id_peserta']; // peserta hanya boleh untuk dirinya sendiri
    }

    try {
        if ($metode === 'GET') {
            // hanya kolom yang terdaftar yang dikirim (kolom lain, mis. password_hash, tidak pernah bocor)
            $pilih = "$pk, " . implode(', ', $kolom);
            if ($id) { // ?id=3 -> satu objek
                $st = $pdo->prepare("SELECT $pilih FROM $tabel WHERE $pk = ?");
                $st->execute([$id]);
                $baris = $st->fetch();
                if (!$baris) kirim(['error' => 'Data tidak ditemukan'], 404);
                kirim($baris);
            }
            $st = $pdo->prepare("SELECT $pilih FROM $tabel WHERE CONCAT_WS(' ', " . implode(', ', $kolom) . ") LIKE ? ORDER BY $pk DESC");
            $st->execute(['%' . ($_GET['q'] ?? '') . '%']);
            kirim($st->fetchAll());
        }
        if ($metode === 'POST') {
            $data = $data + $default; // isi nilai bawaan untuk kolom yang tidak dikirim
            foreach ($wajib as $w) {
                if (!isset($data[$w]) || $data[$w] === '') kirim(['error' => "$w wajib diisi"], 400); // angka 0 dianggap terisi
            }
            if (!$data) kirim(['error' => 'Data kosong'], 400);
            if ($cek && ($salah = $cek($data))) kirim(['error' => $salah], 422);
            $st = $pdo->prepare("INSERT INTO $tabel (" . implode(', ', array_keys($data)) . ") VALUES (" . implode(', ', array_fill(0, count($data), '?')) . ")");
            $st->execute(array_values($data));
            kirim(['id' => (int)$pdo->lastInsertId()], 201);
        }
        if ($metode === 'PUT' || $metode === 'DELETE') { // /peserta.php?id=3
            if (!$id) kirim(['error' => 'id wajib ada di URL (?id=...)'], 400);
            if ($metode === 'PUT') {
                if (!$data) kirim(['error' => 'Data kosong'], 400);
                if ($cek) { // validasi memakai gabungan data lama + perubahan (mis. hanya tgl_selesai yang diubah)
                    $lama = $pdo->prepare("SELECT * FROM $tabel WHERE $pk = ?");
                    $lama->execute([$id]);
                    $lama = $lama->fetch();
                    if (!$lama) kirim(['error' => 'Data tidak ditemukan'], 404);
                    if ($salah = $cek($data + $lama)) kirim(['error' => $salah], 422);
                }
                $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($data)));
                $st = $pdo->prepare("UPDATE $tabel SET $set WHERE $pk = ?");
                $st->execute([...array_values($data), $id]);
            } else {
                $st = $pdo->prepare("DELETE FROM $tabel WHERE $pk = ?");
                $st->execute([$id]);
            }
            if ($st->rowCount() === 0) kirim(['error' => 'Data tidak ditemukan'], 404);
            kirim(['ok' => true]);
        }
        kirim(['error' => 'Metode tidak didukung'], 405);
    } catch (PDOException $e) {
        errorDb($e);
    }
}