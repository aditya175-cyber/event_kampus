<?php
require 'config.php';
crud('kategori_lomba', 'id_kategori', ['nama_kategori', 'deskripsi'], ['nama_kategori'], [], ['peran' => ['admin']]);