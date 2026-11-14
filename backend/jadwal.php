<?php
require 'config.php';

// Jam selesai harus setelah jam mulai. Jam bisa "HH:MM" (dari form) atau "HH:MM:SS" (dari database).
function cekJadwal($b) {
    $mulai = DateTime::createFromFormat('H:i', substr((string)($b['jam_mulai'] ?? ''), 0, 5));
    $selesai = DateTime::createFromFormat('H:i', substr((string)($b['jam_selesai'] ?? ''), 0, 5));
    if (!$mulai || !$selesai) return 'Format jam harus HH:MM';
    if ($selesai <= $mulai) return 'Jam selesai harus setelah jam mulai';
    return null;
}

// GET (umum): rundown per sesi + nama kegiatan. Satu data: ?id=3   Per kegiatan: ?id_kegiatan=1   Cari: ?q=
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $sql = "SELECT j.*, k.nama_kegiatan
            FROM jadwal j JOIN kegiatan k ON k.id_kegiatan = j.id_kegiatan
            WHERE CONCAT_WS(' ', j.nama_sesi, k.nama_kegiatan, j.lokasi) LIKE ?";
    $args = ['%' . ($_GET['q'] ?? '') . '%'];
    if (!empty($_GET['id_kegiatan'])) {
        $sql .= " AND j.id_kegiatan = ?";
        $args[] = $_GET['id_kegiatan'];
    }
    ambil($sql, $args, 'j.id_jadwal', 'j.tanggal, j.jam_mulai');
}

crud('jadwal', 'id_jadwal', ['id_kegiatan', 'nama_sesi', 'tanggal', 'jam_mulai', 'jam_selesai', 'lokasi', 'deskripsi'],
    ['id_kegiatan', 'nama_sesi', 'tanggal', 'jam_mulai', 'jam_selesai'], [], ['cek' => 'cekJadwal']);