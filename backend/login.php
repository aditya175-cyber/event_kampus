<?php
require 'config.php';
// POST {"username": "...", "password": "..."} -> {"token": "...", "pengguna": {...}}
// Akun ada di tabel users (peserta: username = NIM). Token dikirim di request berikutnya:
//   header  Authorization: Bearer <token>
if ($_SERVER['REQUEST_METHOD'] !== 'POST') kirim(['error' => 'Gunakan metode POST'], 405);
$d = json_decode(file_get_contents('php://input'), true) ?: [];

try {
    // nama tampilan: nama peserta, atau nama ormawa, atau username (admin)
    $st = $pdo->prepare('SELECT u.*, COALESCE(p.nama, o.nama_ormawa, u.username) AS nama
                         FROM users u
                         LEFT JOIN peserta p ON p.id_peserta = u.id_peserta
                         LEFT JOIN ormawa o ON o.id_ormawa = u.id_ormawa
                         WHERE u.username = ?');
    $st->execute([$d['username'] ?? '']);
    $u = $st->fetch();
} catch (PDOException $e) {
    errorDb($e);
}
// pesan sengaja sama untuk username salah maupun password salah
if (!$u || !password_verify($d['password'] ?? '', $u['password_hash'])) kirim(['error' => 'Username atau password salah'], 401);

$pengguna = [
    'id' => (int)$u['id_user'], 'username' => $u['username'], 'nama' => $u['nama'],
    'peran' => strtolower($u['role']),            // admin | ormawa | peserta
    'id_peserta' => $u['id_peserta'] === null ? null : (int)$u['id_peserta'],
    'id_ormawa' => $u['id_ormawa'] === null ? null : (int)$u['id_ormawa'],
];
kirim(['token' => buatToken($pengguna), 'pengguna' => $pengguna]);