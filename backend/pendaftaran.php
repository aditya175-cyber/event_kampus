<?php
require 'config.php';

// GET: pendaftaran + nama peserta/kegiatan/kategori + status pembayaran (JOIN)
// Satu data: ?id=3   Filter cabang lomba: ?id_kategori=1   Cari nama/NIM: ?q=andi
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = "SELECT d.*, p.nama, p.nim, k.nama_kegiatan, kl.nama_kategori, pb.status_pembayaran
            FROM pendaftaran d
            JOIN peserta p ON p.id_peserta = d.id_peserta
            JOIN kegiatan k ON k.id_kegiatan = d.id_kegiatan
            LEFT JOIN kategori_lomba kl ON kl.id_kategori = k.id_kategori
            LEFT JOIN pembayaran pb ON pb.id_pendaftaran = d.id_pendaftaran
            WHERE CONCAT_WS(' ', p.nama, p.nim) LIKE ?";
    $args = ['%' . ($_GET['q'] ?? '') . '%'];
    $u = wajibLogin();
    if ($u['peran'] === 'peserta') { $sql .= " AND d.id_peserta = ?"; $args[] = $u['id_peserta']; } // peserta hanya melihat miliknya
    if (!empty($_GET['id_kategori'])) {
        $sql .= " AND k.id_kategori = ?";
        $args[] = $_GET['id_kategori'];
    }
    ambil($sql, $args, 'd.id_pendaftaran', 'd.id_pendaftaran DESC');
}

// POST/PUT/DELETE: id_peserta & id_kegiatan wajib; tgl_daftar (DATETIME) kosong diisi sekarang
// (tanggal NULL akan lolos trigger tanggal, jadi harus diisi)
// POST boleh untuk peserta (id_peserta dipaksa = dirinya; status & tgl_daftar diabaikan: selalu 'Menunggu' + waktu sekarang)
crud('pendaftaran', 'id_pendaftaran', ['tgl_daftar', 'status', 'id_peserta', 'id_kegiatan'],
    ['id_peserta', 'id_kegiatan'], ['tgl_daftar' => date('Y-m-d H:i:s')],
    ['bukaPost' => true, 'kolomStaf' => ['status', 'tgl_daftar'], 'milik' => 'id_peserta']);