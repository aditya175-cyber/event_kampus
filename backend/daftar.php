<?php
require 'config.php';
// POST {nim, nama, prodi, no_hp, password} -> buat peserta + akun (username = NIM), langsung login
// Hasil sama seperti login.php: {"token": "...", "pengguna": {...}}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') kirim(['error' => 'Gunakan metode POST'], 405);
$d = json_decode(file_get_contents('php://input'), true) ?: [];
$nim = trim($d['nim'] ?? ''); $nama = trim($d['nama'] ?? ''); $sandi = (string)($d['password'] ?? '');

if ($nim === '' || $nama === '') kirim(['error' => 'NIM dan nama wajib diisi'], 400);
if (strlen($sandi) < 6) kirim(['error' => 'Password minimal 6 karakter'], 422);

try {
    $pdo->beginTransaction();   // dua INSERT harus berhasil bersamaan, kalau tidak batal semua
    $pdo->prepare('INSERT INTO peserta (nama, nim, prodi, no_hp) VALUES (?, ?, ?, ?)')
        ->execute([$nama, $nim, ($d['prodi'] ?? '') ?: null, ($d['no_hp'] ?? '') ?: null]);
    $idPeserta = (int)$pdo->lastInsertId();
    $pdo->prepare("INSERT INTO users (username, password_hash, role, id_peserta) VALUES (?, ?, 'Peserta', ?)")
        ->execute([$nim, password_hash($sandi, PASSWORD_DEFAULT), $idPeserta]);
    $idUser = (int)$pdo->lastInsertId();
    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    errorDb($e);   // NIM kembar -> 409
}

$pengguna = ['id' => $idUser, 'username' => $nim, 'nama' => $nama, 'peran' => 'peserta', 'id_peserta' => $idPeserta, 'id_ormawa' => null];
kirim(['token' => buatToken($pengguna), 'pengguna' => $pengguna], 201);