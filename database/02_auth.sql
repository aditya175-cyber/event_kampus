-- Akun DEMO untuk tabel users (db_lomba.sql tidak berisi akun sama sekali).
-- Jalankan SETELAH db_lomba.sql, pada database db_lomba.
-- Password disimpan sebagai hash (password_hash PHP). GANTI sebelum dipakai di luar kelas/demo.
--   admin    / admin123  -> role Admin
--   bem      / bem123    -> role Ormawa (ormawa id 1 = BEM)
--   2024001  / andi123   -> role Peserta (peserta id 1 = Andi Saputra, username = NIM)
INSERT INTO `users` (`username`, `password_hash`, `role`, `id_peserta`, `id_ormawa`) VALUES
('admin',   '$2y$10$un.Be9pisnNiSOz7fBj.9ukGS1CxofXxiccgMwCGSdSTrqsrawAcS', 'Admin',   NULL, NULL),
('bem',     '$2y$10$iFvy/sHqyFXMhD9asHN.rOC7NjLP0eMK6WhgwIbfXCpbGoDFP8avW', 'Ormawa',  NULL, 1),
('2024001', '$2y$10$hJcMKpqrNdwxjosnkp.j5eqFz0cijOR2an.Qz3eAkDCM/VUVawE06', 'Peserta', 1,    NULL);