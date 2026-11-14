<?php
require 'config.php';
// POST multipart (field "file") -> {"nama": "bukti_xxxx.jpg"}; nama itu dikirim ke pembayaran.php (bukti_pembayaran).
// Syarat: sudah login, JPG/PNG/PDF, maksimal 2 MB.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') kirim(['error' => 'Gunakan metode POST'], 405);
wajibPeran(['admin', 'ormawa', 'peserta']);

$f = $_FILES['file'] ?? null;
if (!$f || $f['error'] !== UPLOAD_ERR_OK) kirim(['error' => 'File belum dipilih atau gagal diunggah'], 400);
if ($f['size'] > 2 * 1024 * 1024) kirim(['error' => 'Ukuran file maksimal 2 MB'], 422);

// jenis file dibaca dari isinya, bukan dari nama/ekstensi yang dikirim pengguna
$tipe = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
$ekstensi = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'application/pdf' => 'pdf'][$tipe] ?? null;
if (!$ekstensi) kirim(['error' => 'File harus berformat JPG, PNG, atau PDF'], 422);

$nama = 'bukti_' . bin2hex(random_bytes(8)) . '.' . $ekstensi; // nama acak, tidak memakai nama asli
if (!is_dir(__DIR__ . '/uploads')) mkdir(__DIR__ . '/uploads', 0755, true);
if (!move_uploaded_file($f['tmp_name'], __DIR__ . "/uploads/$nama")) kirim(['error' => 'Gagal menyimpan file'], 500);
kirim(['nama' => $nama], 201);