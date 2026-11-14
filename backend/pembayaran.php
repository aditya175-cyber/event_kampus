<?php
require 'config.php';
const STAF = ['admin', 'ormawa'];

function cekPembayaran($b) {
    if (!is_numeric($b['jumlah_bayar'] ?? null) || $b['jumlah_bayar'] <= 0) return 'Jumlah bayar harus lebih dari 0';
    return null;
}

// GET: pembayaran + nama peserta & kegiatan. Hanya admin/ormawa (bukti dan jumlah bayar bersifat pribadi).
// Satu data: ?id=3   Filter status: ?status=Menunggu   Cari: ?q=andi
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    wajibPeran(STAF);
    $sql = "SELECT pb.*, p.nama, p.nim, k.nama_kegiatan
            FROM pembayaran pb
            JOIN pendaftaran d ON d.id_pendaftaran = pb.id_pendaftaran
            JOIN peserta p ON p.id_peserta = d.id_peserta
            JOIN kegiatan k ON k.id_kegiatan = d.id_kegiatan
            WHERE CONCAT_WS(' ', p.nama, p.nim, k.nama_kegiatan) LIKE ?";
    $args = ['%' . ($_GET['q'] ?? '') . '%'];
    if (!empty($_GET['status'])) {
        $sql .= " AND pb.status_pembayaran = ?";
        $args[] = $_GET['status'];
    }
    ambil($sql, $args, 'pb.id_pembayaran', 'pb.id_pembayaran DESC');
}

// Peserta boleh mengirim pembayaran, tapi hanya untuk pendaftarannya sendiri. Status selalu 'Menunggu'
// dan waktu bayar diisi server: verifikasi hanya hak admin/ormawa.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = wajibLogin();
    if ($u['peran'] === 'peserta') {
        $in = json_decode(file_get_contents('php://input'), true) ?: [];
        $st = $pdo->prepare('SELECT id_peserta FROM pendaftaran WHERE id_pendaftaran = ?');
        $st->execute([$in['id_pendaftaran'] ?? 0]);
        $milik = $st->fetchColumn();
        if ($milik === false) kirim(['error' => 'Pendaftaran tidak ditemukan'], 404);
        if ($milik != $u['id_peserta']) kirim(['error' => 'Itu bukan pendaftaran Anda'], 403);
        if (empty($in['bukti_pembayaran'])) kirim(['error' => 'Bukti pembayaran wajib diunggah'], 422);
    }
}

// bukti_pembayaran = nama file hasil upload.php. Satu pendaftaran hanya boleh punya satu pembayaran (unique key).
crud('pembayaran', 'id_pembayaran',
    ['id_pendaftaran', 'bukti_pembayaran', 'jumlah_bayar', 'tgl_pembayaran', 'status_pembayaran'],
    ['id_pendaftaran', 'jumlah_bayar'], ['tgl_pembayaran' => date('Y-m-d H:i:s')],
    ['peran' => STAF, 'cek' => 'cekPembayaran', 'bukaPost' => true, 'kolomStaf' => ['status_pembayaran', 'tgl_pembayaran']]);