<?php
// Self-check. Jalankan server dulu:  php -S localhost:8000   (di folder backend)
// lalu di terminal lain:             php test_api.php
// Butuh: database/db_lomba.sql + database/02_demo_users.sql sudah diimport (akun demo admin, bem, 2024001).
// Data uji dibuat lalu dihapus otomatis.
const BASE = 'http://localhost:8000/';

// $token: string = pakai token itu; null = pakai token bawaan ($GLOBALS['tok']); false = tanpa login
function api($metode, $url, $body = null, $token = null) {
    if ($token === null) $token = $GLOBALS['tok'] ?? null;
    $header = "Content-Type: application/json\r\n" . ($token ? "Authorization: Bearer $token\r\n" : '');
    $ctx = stream_context_create(['http' => ['method' => $metode, 'ignore_errors' => true,
        'header' => $header, 'content' => $body !== null ? json_encode($body) : '']]);
    $res = file_get_contents(BASE . $url, false, $ctx);
    preg_match('/ (\d{3})/', $http_response_header[0], $m);
    return [(int)$m[1], json_decode($res, true)];
}
function cek($ok, $nama) { echo ($ok ? "LULUS  " : "GAGAL  ") . "$nama\n"; if (!$ok) $GLOBALS['gagal'] = true; }

$gagal = false; $id = []; $admin = null;
try {
    // ---- login & peran ----
    [$s] = api('POST', 'login.php', ['username' => 'admin', 'password' => 'salah']);
    cek($s == 401, 'Login dengan password salah ditolak');
    [$s, $r] = api('POST', 'login.php', ['username' => 'admin', 'password' => 'admin123']);
    cek($s == 200 && !empty($r['token']) && $r['pengguna']['peran'] == 'admin', 'Login admin berhasil, token + peran diterima');
    $admin = $GLOBALS['tok'] = $r['token'];   // token bawaan: semua GET wajib login
    [, $r] = api('POST', 'login.php', ['username' => 'bem', 'password' => 'bem123']);
    $ormawa = $r['token'];  // peran ormawa
    cek($r['pengguna']['peran'] == 'ormawa' && $r['pengguna']['id_ormawa'] == 1, 'Login ormawa: peran + id_ormawa benar');
    [, $r] = api('POST', 'login.php', ['username' => '2024001', 'password' => 'andi123']);
    $peserta = $r['token'];
    [$s] = api('POST', 'kegiatan.php', ['nama_kegiatan' => 'X'], $peserta);
    cek($s == 403, 'Peserta tidak boleh menambah kegiatan (403)');

    foreach (['kegiatan.php', 'peserta.php', 'pendaftaran.php', 'jadwal.php', 'statistik.php'] as $f) {
        [$s] = api('GET', $f, null, false);
        if ($s != 401) { cek(false, "GET $f tanpa login harus 401"); }
    }
    cek(true, 'Semua GET tanpa login ditolak (401)');
    [$s] = api('GET', 'peserta.php', null, $peserta);
    cek($s == 403, 'Peserta tidak boleh melihat daftar peserta lain (403)');
    [$s] = api('PUT', 'peserta.php?id=1', ['nama' => 'X'], false);
    cek($s == 401, 'Ubah data tanpa login ditolak (401)');
    [$s] = api('DELETE', 'peserta.php?id=1', null, substr($admin, 0, -3) . 'abc');
    cek($s == 401, 'Token yang dipalsukan ditolak (401)');
    [$s] = api('DELETE', 'ormawa.php?id=999999', null, $ormawa);
    cek($s == 403, 'Ormawa tidak boleh mengelola data ormawa (403)');
    [$s] = api('POST', 'kegiatan.php', ['nama_kegiatan' => 'X'], false);
    cek($s == 401, 'Tambah kegiatan tanpa login ditolak (401)');

    // ---- data uji ----
    $kg = ['nama_kegiatan' => 'TES', 'tgl_mulai' => '2099-01-10', 'tgl_selesai' => '2099-01-11', 'kuota' => 1, 'id_ormawa' => 1, 'id_kategori' => 1];
    [, $r] = api('POST', 'kegiatan.php', $kg, $ormawa);  $id['kegiatan'] = $r['id'];
    [$s] = api('POST', 'peserta.php', ['nama' => 'A', 'nim' => 'TES-1'], false);
    cek($s == 401, 'Tambah peserta tanpa login ditolak');
    [$s, $r] = api('POST', 'peserta.php', ['nama' => 'A', 'nim' => 'TES-1'], $ormawa);
    cek($s == 201, 'Staf boleh menambah peserta');  $id['a'] = $r['id'];
    [, $r] = api('POST', 'peserta.php', ['nama' => 'B', 'nim' => 'TES-2'], $ormawa);  $id['b'] = $r['id'];

    // ---- 4 validasi trigger ----
    [$s] = api('POST', 'peserta.php', ['nama' => 'X', 'nim' => 'TES-1'], $ormawa);
    cek($s == 409, 'NIM duplikat ditolak');
    [$s] = api('POST', 'pendaftaran.php', ['id_peserta' => $id['a'], 'id_kegiatan' => $id['kegiatan'], 'tgl_daftar' => '2099-01-11']);
    cek($s == 422, 'Tanggal daftar melewati tanggal mulai ditolak');
    [$s, $r] = api('POST', 'pendaftaran.php', ['id_peserta' => $id['a'], 'id_kegiatan' => $id['kegiatan'], 'tgl_daftar' => '2099-01-01']);
    cek($s == 201, 'Pendaftaran valid berhasil');  $id['d1'] = $r['id'] ?? 0;
    [$s] = api('POST', 'pendaftaran.php', ['id_peserta' => $id['a'], 'id_kegiatan' => $id['kegiatan'], 'tgl_daftar' => '2099-01-02']);
    cek($s == 409, 'Daftar dua kali di kegiatan sama ditolak');
    // peserta (Andi, id 1) mendaftar: id_peserta orang lain, status, dan tgl_daftar yang dikirim harus diabaikan
    [$s, $r] = api('POST', 'pendaftaran.php', ['id_peserta' => $id['b'], 'id_kegiatan' => $id['kegiatan'], 'status' => 'Terverifikasi', 'tgl_daftar' => '2000-01-01 00:00:00'], $peserta);
    cek($s == 201, 'Peserta bisa mendaftar lomba');  $id['d2'] = $r['id'] ?? 0;
    [, $r] = api('GET', "pendaftaran.php?id={$id['d2']}");
    cek(($r['id_peserta'] ?? 0) == 1, 'id_peserta dipaksa milik peserta yang login');
    [, $r] = api('GET', 'pendaftaran.php', null, $peserta);
    cek(count($r) >= 1 && count(array_unique(array_column($r, 'id_peserta'))) == 1, 'Peserta hanya melihat pendaftaran miliknya');
    [, $r] = api('GET', "pendaftaran.php?id={$id['d2']}");
    cek(($r['status'] ?? '') == 'Menunggu' && substr($r['tgl_daftar'] ?? '', 0, 10) == date('Y-m-d') && strlen($r['tgl_daftar']) == 19, "Status paksa 'Terverifikasi' diabaikan, tgl_daftar terisi otomatis lengkap dengan jam");
    [$s] = api('PUT', "pendaftaran.php?id={$id['d1']}", ['status' => 'Terverifikasi'], $ormawa);
    cek($s == 200, 'Panitia memverifikasi (kuota 1 terisi)');
    [$s, $r] = api('PUT', "pendaftaran.php?id={$id['d2']}", ['status' => 'Terverifikasi'], $ormawa);
    cek($s == 422 && strpos($r['error'], 'Kuota') !== false, 'Verifikasi kedua ditolak: kuota penuh');
    [$s] = api('DELETE', "kegiatan.php?id={$id['kegiatan']}", null, $admin);
    cek($s == 409, 'Kegiatan yang masih dipakai tidak bisa dihapus');

    // ---- GET by id ----
    [$s, $r] = api('GET', "kegiatan.php?id={$id['kegiatan']}");
    cek($s == 200 && ($r['nama_kegiatan'] ?? '') == 'TES' && !isset($r[0]), 'GET ?id= mengembalikan satu objek (bukan array)');
    [$s] = api('GET', 'kegiatan.php?id=999999');
    cek($s == 404, 'GET ?id= yang tidak ada -> 404');
    [$s, $r] = api('GET', "pendaftaran.php?id={$id['d1']}");
    cek($s == 200 && ($r['nama'] ?? '') == 'A' && !empty($r['nama_kegiatan']), 'GET pendaftaran ?id= membawa nama peserta & kegiatan');

    // ---- jadwal ----
    $jd = ['id_kegiatan' => $id['kegiatan'], 'nama_sesi' => 'Sesi TES', 'tanggal' => '2099-01-10', 'jam_mulai' => '09:00', 'jam_selesai' => '10:00'];
    [$s] = api('POST', 'jadwal.php', $jd, false);
    cek($s == 401, 'Tambah jadwal tanpa login ditolak');
    [$s, $r] = api('POST', 'jadwal.php', ['jam_selesai' => '08:00'] + $jd, $ormawa);
    cek($s == 422, 'Jam selesai sebelum jam mulai ditolak');
    [$s, $r] = api('POST', 'jadwal.php', $jd, $ormawa);
    cek($s == 201, 'Jadwal valid berhasil');  $id['jadwal'] = $r['id'] ?? 0;
    [, $r] = api('GET', "jadwal.php?id_kegiatan={$id['kegiatan']}");
    cek(count($r) == 1 && $r[0]['nama_kegiatan'] == 'TES', 'GET jadwal per kegiatan (publik) berfungsi');

    // ---- pembayaran ----
    [$s] = api('GET', 'pembayaran.php', null, false);
    cek($s == 401, 'Lihat pembayaran tanpa login ditolak');
    [$s] = api('GET', 'pembayaran.php', null, $peserta);
    cek($s == 403, 'Peserta tidak boleh melihat daftar pembayaran');
    [$s] = api('POST', 'pembayaran.php', ['id_pendaftaran' => $id['d1'], 'jumlah_bayar' => 0], $ormawa);
    cek($s == 422, 'Jumlah bayar 0 ditolak');
    [$s, $r] = api('POST', 'pembayaran.php', ['id_pendaftaran' => $id['d1'], 'jumlah_bayar' => 25000], $ormawa);
    cek($s == 201, 'Pembayaran valid berhasil');  $id['bayar'] = $r['id'] ?? 0;
    [$s] = api('POST', 'pembayaran.php', ['id_pendaftaran' => $id['d1'], 'jumlah_bayar' => 25000], $ormawa);
    cek($s == 409, 'Satu pendaftaran tidak boleh punya dua pembayaran');
    // peserta (Andi) membayar: hanya pendaftarannya sendiri, tanpa bukti ditolak, status dipaksa 'Menunggu'
    [$s] = api('POST', 'pembayaran.php', ['id_pendaftaran' => $id['d1'], 'jumlah_bayar' => 25000, 'bukti_pembayaran' => 'x.png'], $peserta);
    cek($s == 403, 'Peserta tidak boleh membayar pendaftaran orang lain (403)');
    [$s] = api('POST', 'pembayaran.php', ['id_pendaftaran' => $id['d2'], 'jumlah_bayar' => 25000], $peserta);
    cek($s == 422, 'Peserta wajib melampirkan bukti (422)');
    [$s, $r] = api('POST', 'pembayaran.php', ['id_pendaftaran' => $id['d2'], 'jumlah_bayar' => 25000, 'bukti_pembayaran' => 'x.png', 'status_pembayaran' => 'Diverifikasi'], $peserta);
    cek($s == 201, 'Peserta berhasil membayar pendaftarannya');  $id['bayar2'] = $r['id'] ?? 0;
    [, $r] = api('GET', "pembayaran.php?id={$id['bayar2']}", null, $ormawa);
    cek(($r['status_pembayaran'] ?? '') == 'Menunggu', "Status yang dikirim peserta diabaikan (tetap 'Menunggu')");
    [$s] = api('PUT', "pembayaran.php?id={$id['bayar2']}", ['status_pembayaran' => 'Diverifikasi'], $peserta);
    cek($s == 403, 'Peserta tidak boleh memverifikasi pembayaran (403)');
    [$s] = api('PUT', "pembayaran.php?id={$id['bayar']}", ['status_pembayaran' => 'Diverifikasi'], $ormawa);
    cek($s == 200, 'Verifikasi pembayaran berhasil');
    [, $r] = api('GET', "pendaftaran.php?id={$id['d1']}");
    cek(($r['status_pembayaran'] ?? '') == 'Diverifikasi', 'Pendaftaran membawa status pembayaran');

    // ---- upload bukti ----
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    $up = function ($isi, $nama, $token) {
        $b = 'xx' . bin2hex(random_bytes(4));
        $badan = "--$b\r\nContent-Disposition: form-data; name=\"file\"; filename=\"$nama\"\r\nContent-Type: application/octet-stream\r\n\r\n$isi\r\n--$b--\r\n";
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'ignore_errors' => true, 'content' => $badan,
            'header' => "Content-Type: multipart/form-data; boundary=$b\r\n" . ($token ? "Authorization: Bearer $token\r\n" : '')]]);
        $res = file_get_contents(BASE . 'upload.php', false, $ctx);
        preg_match('/ (\d{3})/', $http_response_header[0], $m);
        return [(int)$m[1], json_decode($res, true)];
    };
    [$s] = $up($png, 'a.png', null);
    cek($s == 401, 'Upload tanpa login ditolak');
    [$s] = $up('<?php echo 1;', 'x.png', $peserta);
    cek($s == 422, 'File non-gambar (walau berekstensi .png) ditolak');
    [$s, $r] = $up($png, 'bukti.png', $peserta);
    cek($s == 201 && preg_match('/^bukti_[0-9a-f]{16}\.png$/', $r['nama'] ?? ''), 'Upload PNG valid -> nama acak');
    if (!empty($r['nama'])) @unlink(__DIR__ . '/uploads/' . $r['nama']);

    // ---- validasi kegiatan ----
    [$s, $r] = api('POST', 'kegiatan.php', ['tgl_selesai' => '2099-01-01'] + $kg, $admin);
    cek($s == 422 && strpos($r['error'], 'Tanggal selesai') !== false, 'tgl_selesai < tgl_mulai ditolak');
    [$s] = api('POST', 'kegiatan.php', ['kuota' => 0] + $kg, $admin);
    cek($s == 422, 'kuota 0 ditolak');
    [$s] = api('POST', 'kegiatan.php', ['kuota' => 'abc'] + $kg, $admin);
    cek($s == 422, 'kuota bukan angka ditolak');
    [$s] = api('POST', 'kegiatan.php', ['biaya_daftar' => -1] + $kg, $admin);
    cek($s == 422, 'biaya negatif ditolak');
    [$s] = api('PUT', "kegiatan.php?id={$id['kegiatan']}", ['tgl_selesai' => '2099-01-05'], $admin);
    cek($s == 422, 'Ubah hanya tgl_selesai ke sebelum tgl_mulai lama tetap ditolak');
    [$s] = api('PUT', "kegiatan.php?id={$id['kegiatan']}", ['biaya_daftar' => 0, 'kuota' => 5], $admin);
    cek($s == 200, 'Ubah kegiatan yang valid berhasil');

    // ---- pencarian & statistik ----
    [, $r] = api('GET', 'statistik.php');
    cek(in_array(2, array_column(array_filter($r, fn($x) => $x['id_kegiatan'] == $id['kegiatan']), 'jumlah_pendaftar')), 'Statistik jumlah pendaftar benar');
    [, $r] = api('GET', 'pendaftaran.php?id_kategori=1&q=A');
    cek(count($r) >= 1, 'Filter cabang lomba + cari berfungsi');
    [, $r] = api('GET', 'peserta.php?q=TES-2');
    cek(count($r) == 1 && $r[0]['nim'] == 'TES-2', 'Pencarian peserta berfungsi');
    [$s] = api('POST', 'pendaftaran.php', [], $ormawa);
    cek($s == 400, 'Pendaftaran tanpa id ditolak');

    // ---- daftar akun ----
    $nimBaru = 'TES-' . random_int(1000, 9999);
    [$s] = api('POST', 'daftar.php', ['nim' => $nimBaru, 'nama' => 'Uji', 'password' => '123'], false);
    cek($s == 422, 'Daftar: password kurang dari 6 karakter ditolak');
    [$s, $r] = api('POST', 'daftar.php', ['nim' => $nimBaru, 'nama' => 'Uji', 'prodi' => 'IF', 'password' => 'rahasia1'], false);
    cek($s == 201 && $r['pengguna']['peran'] == 'peserta' && !empty($r['token']), 'Daftar akun berhasil, langsung dapat token peserta');
    $id['akun'] = $r['pengguna']['id_peserta'] ?? 0;
    [$s] = api('POST', 'daftar.php', ['nim' => $nimBaru, 'nama' => 'Lagi', 'password' => 'rahasia1'], false);
    cek($s == 409, 'Daftar: NIM yang sama ditolak (409)');
    [$s] = api('POST', 'login.php', ['username' => $nimBaru, 'password' => 'rahasia1'], false);
    cek($s == 200, 'Akun baru bisa login dengan NIM + password');
    [, $r] = api('GET', 'peserta.php?q=' . $nimBaru);
    cek(count($r) == 1, 'Peserta baru tercatat di tabel peserta');
} finally {
    if (!empty($id['akun'])) {  // akun uji dihapus lewat SQL karena tidak ada endpoint hapus users
    $p = new PDO('mysql:host=localhost;dbname=db_lomba;charset=utf8mb4', 'root', '');
    $p->prepare('DELETE FROM users WHERE id_peserta = ?')->execute([$id['akun']]);
    api('DELETE', "peserta.php?id={$id['akun']}", null, $admin);
}
    foreach (['bayar2' => 'pembayaran', 'bayar' => 'pembayaran', 'jadwal' => 'jadwal', 'd1' => 'pendaftaran', 'd2' => 'pendaftaran', 'a' => 'peserta', 'b' => 'peserta', 'kegiatan' => 'kegiatan'] as $k => $t)
        if (!empty($id[$k])) api('DELETE', "$t.php?id={$id[$k]}", null, $admin);
}
echo $gagal ? "\nADA YANG GAGAL\n" : "\nSEMUA TES LULUS\n";