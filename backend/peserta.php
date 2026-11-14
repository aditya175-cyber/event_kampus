<?php
require 'config.php';
// Data peserta (nama, NIM, no. HP) bersifat pribadi: hanya admin/ormawa. Peserta baru dibuat lewat daftar.php.
crud('peserta', 'id_peserta', ['nama', 'nim', 'prodi', 'no_hp'], ['nama', 'nim'], [], ['baca' => ['admin', 'ormawa']]);