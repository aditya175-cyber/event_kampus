<?php
require 'config.php';

// Validasi isi kegiatan. Return pesan error, atau null kalau semua benar.
function cekKegiatan($b) {
    $mulai = DateTime::createFromFormat('Y-m-d', (string)($b['tgl_mulai'] ?? ''));
    $selesai = DateTime::createFromFormat('Y-m-d', (string)($b['tgl_selesai'] ?? ''));
    if (!$mulai || !$selesai) return 'Format tanggal harus YYYY-MM-DD';
    if ($selesai < $mulai) return 'Tanggal selesai tidak boleh sebelum tanggal mulai';
    if (filter_var($b['kuota'] ?? null, FILTER_VALIDATE_INT) === false || $b['kuota'] < 1) return 'Kuota harus angka bulat lebih dari 0';
    if (($b['biaya_daftar'] ?? null) !== null && (!is_numeric($b['biaya_daftar']) || $b['biaya_daftar'] < 0)) return 'Biaya pendaftaran tidak boleh negatif';
    return null;
}

crud('kegiatan', 'id_kegiatan',
    ['nama_kegiatan', 'deskripsi', 'tgl_mulai', 'tgl_selesai', 'lokasi', 'kuota', 'biaya_daftar', 'id_ormawa', 'id_kategori'],
    ['nama_kegiatan', 'tgl_mulai', 'tgl_selesai', 'kuota', 'id_ormawa', 'id_kategori'],
    [], ['cek' => 'cekKegiatan']);