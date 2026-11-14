<?php
require 'config.php';
// ormawa & kategori hanya boleh diubah admin
crud('ormawa', 'id_ormawa', ['nama_ormawa', 'jenis_ormawa', 'kontak'], ['nama_ormawa'], [], ['peran' => ['admin']]);