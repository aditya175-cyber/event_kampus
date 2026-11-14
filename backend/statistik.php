<?php
require 'config.php';
wajibLogin();
// Jumlah pendaftar tiap kegiatan (COUNT + GROUP BY)
$st = $pdo->query("SELECT k.id_kegiatan, k.nama_kegiatan, k.kuota, COUNT(d.id_pendaftaran) AS jumlah_pendaftar
                   FROM kegiatan k LEFT JOIN pendaftaran d ON d.id_kegiatan = k.id_kegiatan
                   GROUP BY k.id_kegiatan, k.nama_kegiatan, k.kuota
                   ORDER BY jumlah_pendaftar DESC");
kirim($st->fetchAll());