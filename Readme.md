# Website Pengelolaan Lomba & Kegiatan Kampus

Kelompok 6 · Topik 5 · Sistem Basis Data 1
Backend: PHP Native (PDO) + MySQL · Frontend: HTML, CSS, JavaScript biasa

Fungsi: mengelola kegiatan/lomba ormawa, pendaftaran peserta, jadwal sesi, dan pembayaran pendaftaran (CRUD + pencarian), dengan login berbasis peran (admin, ormawa, peserta).

## Struktur folder

```
KELOMPOK6/
├── database/
│   ├── db_lomba.sql           # skema MySQL + trigger + data awal (dari tim)
│   └── 02_demo_users.sql      # 3 akun demo untuk tabel users
├── backend/                   # API PHP Native (JSON)
│   ├── config.php             # koneksi, CORS, kirim(), errorDb(), token login, crud(), ambil()
│   ├── login.php  daftar.php  # login -> token; daftar akun peserta
│   ├── kegiatan.php  ormawa.php  kategori_lomba.php
│   ├── peserta.php  pendaftaran.php
│   ├── jadwal.php  pembayaran.php
│   ├── upload.php  uploads/   # unggah bukti bayar (JPG/PNG/PDF, maks 2 MB)
│   ├── statistik.php          # jumlah pendaftar per kegiatan
│   └── test_api.php           # self-check backend (61 skenario)
├── frontend/                  # index.html (aplikasi), login.html, daftar.html, css/, js/
└── docs/erd/                  # gambar ERD (belum ada jadwal/pembayaran/users)
```

## Cara menjalankan (XAMPP)

1. Jalankan **Apache** dan **MySQL** dari XAMPP Control Panel.
2. Di phpMyAdmin buat database kosong bernama **`db_lomba`**, lalu import berurutan ke database itu:
   1. `database/db_lomba.sql`
   2. `database/02_demo_users.sql`

   Hanya ada **satu database**. Dua file itu diimport ke database yang sama.
3. Taruh folder `KELOMPOK6` di `C:\xampp\htdocs\`.
4. Cek backend: buka `http://localhost/KELOMPOK6/backend/login.php`. Respon `{"error":"Gunakan metode POST"}` berarti backend hidup.
5. Buka aplikasi: `http://localhost/KELOMPOK6/frontend/` lalu login.

Kalau MySQL memakai password, ubah di `backend/config.php` (baris `new PDO`).
Di luar kelas, isi rahasia token lewat environment `APP_SECRET` (bawaan di `config.php` hanya untuk belajar).

### Akun demo (dari `02_demo_users.sql`)

| Username | Password | Peran |
|---|---|---|
| `admin` | `admin123` | admin |
| `bem` | `bem123` | ormawa (BEM) |
| `2024001` | `andi123` | peserta (Andi) |

Ganti atau hapus akun ini sebelum dipakai di luar demo. Peserta baru bisa membuat akun sendiri lewat halaman **Daftar akun** (username = NIM).

### Menjalankan tes backend

Di folder `backend`: `php -S localhost:8000`, lalu di terminal lain `php test_api.php`. Hasil yang diharapkan: `SEMUA TES LULUS`. Tes memakai data sementara dan menghapusnya lagi.

## Peran dan hak akses

Semua endpoint wajib login, kecuali `login.php` dan `daftar.php`.

| Peran | Boleh |
|---|---|
| **admin** | Semuanya, termasuk master `ormawa` dan `kategori_lomba` |
| **ormawa** | Kelola kegiatan, jadwal, pendaftaran, pembayaran, peserta. Tidak bisa mengubah master ormawa/kategori |
| **peserta** | Melihat kegiatan, jadwal, dan **pendaftarannya sendiri**. Mendaftar lomba dan membayar pendaftarannya sendiri. Tidak bisa melihat data peserta lain atau daftar pembayaran |

## Alur pendaftaran dan pembayaran

1. Peserta membuat akun (`daftar.html`) lalu login.
2. Peserta mendaftar ke kegiatan. Status pendaftaran otomatis **Menunggu**.
3. Peserta transfer sesuai `biaya_daftar` (di luar aplikasi), lalu di halaman Pendaftaran menekan **Bayar**: mengisi jumlah dan mengunggah bukti. Status pembayaran otomatis **Menunggu**.
4. Admin/ormawa membuka menu **Pembayaran**, memeriksa bukti, lalu mengubah status ke **Diverifikasi** atau **Ditolak**.
5. Admin/ormawa mengubah status pendaftaran menjadi **Terverifikasi**. Hanya yang terverifikasi yang dihitung ke kuota oleh trigger.

Status pembayaran dan status pendaftaran diubah terpisah (belum otomatis saling mengubah).

## Kontrak API

Semua respon berupa JSON. Format: tanggal `YYYY-MM-DD`, waktu `YYYY-MM-DD HH:MM:SS`, jam `HH:MM`.
Token dikirim di setiap request lewat header `Authorization: Bearer <token>` (berlaku 8 jam).
`<t>` = `kegiatan`, `ormawa`, `kategori_lomba`, `peserta`, `pendaftaran`, `jadwal`, `pembayaran`.

### Akun

| Method | URL | Fungsi |
|---|---|---|
| POST | `login.php` `{username, password}` | Login → `{token, pengguna:{id, username, nama, peran, id_peserta, id_ormawa}}`. `peran` = `admin` / `ormawa` / `peserta` |
| POST | `daftar.php` `{nim, nama, prodi, no_hp, password}` | Daftar akun peserta (username = NIM, password minimal 6). `201` + token seperti login |

### Data

| Method | URL | Fungsi | Siapa |
|---|---|---|---|
| GET | `<t>.php?q=kata` | Daftar + pencarian (array) | semua login (`peserta.php`: admin/ormawa; `pembayaran.php`: admin/ormawa) |
| GET | `<t>.php?id=3` | Satu data (objek), `404` kalau tidak ada | sama dengan di atas |
| GET | `pendaftaran.php?id_kategori=1&q=andi` | Pendaftaran + nama peserta, kegiatan, kategori, status pembayaran. Peserta hanya melihat miliknya | semua login |
| GET | `jadwal.php?id_kegiatan=1&q=` | Rundown sesi + nama kegiatan, urut tanggal & jam | semua login |
| GET | `pembayaran.php?status=Menunggu&q=` | Pembayaran + nama peserta & kegiatan | admin, ormawa |
| GET | `statistik.php` | Jumlah pendaftar per kegiatan | semua login |
| POST | `pendaftaran.php` | Daftar lomba → `201 {"id": n}`. Dari peserta: `id_peserta` otomatis dirinya; `status` dan `tgl_daftar` diabaikan | semua login |
| POST | `pembayaran.php` | Catat pembayaran → `201`. Dari peserta: hanya untuk pendaftarannya sendiri, `bukti_pembayaran` wajib, status selalu `Menunggu` | semua login |
| POST | `kegiatan.php`, `jadwal.php`, `peserta.php` | Tambah data | admin, ormawa |
| POST | `ormawa.php`, `kategori_lomba.php` | Tambah master | admin |
| PUT | `<t>.php?id=3` (kirim kolom yang berubah saja) | Ubah | admin, ormawa (`ormawa`, `kategori_lomba`: admin saja) |
| DELETE | `<t>.php?id=3` | Hapus | sama dengan PUT |
| POST | `upload.php` (multipart, field `file`) | Unggah bukti → `201 {"nama": "bukti_xxx.jpg"}`. Nama itu dikirim ke `pembayaran.bukti_pembayaran`. File dibuka di `backend/uploads/<nama>` | semua login |

### Error

Selalu berbentuk `{"error": "pesan"}` dan pesannya bisa langsung ditampilkan ke pengguna.

| Kode | Arti |
|---|---|
| 400 | Kolom wajib kosong, id referensi tidak ada, nilai tidak valid |
| 401 | Belum login, atau token salah/kedaluwarsa |
| 403 | Peran tidak boleh melakukan aksi itu |
| 404 | Data tidak ditemukan |
| 405 | Metode tidak didukung |
| 409 | NIM sudah terdaftar, sudah daftar di kegiatan itu, pendaftaran sudah punya pembayaran, atau data masih dipakai tabel lain |
| 422 | Validasi gagal (kegiatan, jadwal, pembayaran, file upload), CHECK database, atau trigger (tanggal lewat, kuota penuh) |

## Aturan data

- **Kegiatan:** `tgl_selesai >= tgl_mulai`, `kuota` bilangan bulat > 0, `biaya_daftar >= 0`. Saat PUT, aturan dicek terhadap gabungan data lama + perubahan.
- **Jadwal:** `jam_selesai` harus setelah `jam_mulai`.
- **Pembayaran:** `jumlah_bayar` > 0, satu pendaftaran hanya boleh punya satu pembayaran.
- **Upload:** JPG/PNG/PDF, maksimal 2 MB. Jenis file dibaca dari isinya (bukan ekstensi), nama file diacak, dan folder `uploads` tidak menjalankan PHP.
- **Trigger database:** tanggal daftar tidak boleh melewati tanggal mulai kegiatan, dan pendaftar terverifikasi tidak boleh melebihi kuota.
- **Unique key:** NIM unik, satu peserta hanya sekali per kegiatan, satu pembayaran per pendaftaran.
- Password disimpan sebagai hash (`password_hash`). Semua query memakai prepared statement.

## Untuk yang mengerjakan frontend

- Frontend bebas, selama mengikuti kontrak API di atas. CORS sudah diizinkan, jadi frontend boleh di domain atau port lain.
- Alur wajib: panggil `login.php` → simpan `token` → kirim di header `Authorization` pada setiap request.
- Sembunyikan tombol yang tidak boleh dipakai peran itu. Kalau lupa, backend tetap menolak dengan `403`.
- Frontend bawaan di folder `frontend/` bisa dipakai sebagai contoh (`js/api.js` membungkus semua pemanggilan API).

## Catatan dan batasan

- Peserta belum bisa mengulang pembayaran yang **Ditolak** (admin harus menghapus catatan pembayaran itu dulu).
- Ormawa mana pun bisa mengubah kegiatan ormawa lain (kepemilikan belum diperiksa).
- Trigger membandingkan `tgl_daftar` (DATETIME) dengan `tgl_mulai` (DATE), sehingga mendaftar di hari mulai itu sendiri ditolak. Perbaikan: pakai `DATE(NEW.tgl_daftar)` di trigger.
- Belum ada pembatasan percobaan login dan fitur lupa password.
- phpMyAdmin tidak menyertakan CHECK constraint di hasil export. Cek di database asli dengan `SHOW CREATE TABLE kegiatan;`.
- Gambar ERD di `docs/erd` belum mencerminkan tabel `jadwal`, `pembayaran`, dan `users`.
