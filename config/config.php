<?php
// Konfigurasi MaggotTrack. Folder ini dilindungi .htaccess (tidak dapat diakses dari browser).
return [
    'db_host' => '127.0.0.1',
    'db_name' => 'maggottrack',
    'db_user' => 'root',
    'db_pass' => '',            // XAMPP default: kosong

    'allow_register' => true,   // set false setelah akun dibuat
    'timezone' => 'Asia/Jakarta',

    // Isi jalur python.exe jika tidak terdeteksi otomatis, contoh:
    // 'C:\\Users\\NAMA\\AppData\\Local\\Programs\\Python\\Python312\\python.exe'
    'python_path' => '',
    'python_timeout' => 8,      // detik
];
