# MaggotTrack
Monitor Growth. Manage Waste. Maximize Harvest.

Stack: HTML5, CSS3, JavaScript Vanilla, PHP Native (PDO), Python 3 (library standar), MySQL, Chart.js, Lucide Icons.

## Instalasi di Windows 11 (XAMPP)
1. Instal XAMPP (PHP 8.1+). Buka XAMPP Control Panel, jalankan **Apache** dan **MySQL**.
2. Salin folder `MaggotTrack` ke `C:\xampp\htdocs\MaggotTrack`.
3. Buka `http://localhost/phpmyadmin` > tab **Import** > pilih `database/maggottrack.sql` > **Go**.
   (Atau: `C:\xampp\mysql\bin\mysql.exe -u root < database\maggottrack.sql`)
4. Cek `config/config.php`: user `root`, password kosong (default XAMPP). Ubah bila berbeda.
5. Buka `http://localhost/MaggotTrack/`. Klik **Daftar**: akun pertama otomatis menjadi **admin**.
   Setelah akun dibuat, set `'allow_register' => false` di `config/config.php` bila tidak ingin pendaftaran terbuka.
6. Pastikan ekstensi PHP `pdo_mysql`, `fileinfo` aktif (default XAMPP sudah aktif). Untuk upload foto, `upload_max_filesize` minimal 2M.

## Python (opsional)
- Instal Python 3 dari python.org (centang "Add python.exe to PATH" tidak wajib).
- Aplikasi mencari python.exe di `C:\Python3xx`, `%LOCALAPPDATA%\Programs\Python\Python3*`, atau jalur `python_path` di `config/config.php`.
- Python dijalankan tanpa shell, input lewat file sementara, timeout 8 detik. Jika Python tidak ada/gagal, aplikasi tetap berjalan dan panel analisis menampilkan pesan.

## Catatan penting
- Chart.js, Lucide, dan font memakai CDN, jadi butuh internet. Untuk offline, unduh file tersebut ke `assets/vendor/` dan ubah tag di `includes/layout.php`.
- **Umur** maggot dihitung otomatis dari tanggal penetasan batch. Isi tanggal penetasan sebelum mencatat pertumbuhan.
- **Berat sampel**: gunakan ukuran sampel yang konsisten (mis. selalu 10 ekor) agar analisis tren valid.
- **Estimasi populasi** di dashboard = jumlah populasi awal batch aktif (aplikasi belum mencatat mortalitas).
- **Jadwal panen** memakai target tanggal panen yang Anda isi. Tidak ada prediksi panen otomatis; Python hanya memberi estimasi berat sampel (regresi linear) bila data cukup (>=4 pengukuran, R2>=0.6).
- **Export Excel** berupa file .xls (tabel HTML), Excel mungkin menampilkan peringatan format saat dibuka, pilih Yes. **Export PDF** memakai Print browser > "Simpan sebagai PDF".

## Struktur
```
MaggotTrack/
  index.php login.php register.php logout.php
  dashboard.php module.php (CRUD batch, pertumbuhan, pakan, lingkungan, panen)
  schedule.php reports.php export_excel.php
  config/config.php  includes/ (bootstrap, layout, modules, crud, python, report_data)
  python/growth_analysis.py  database/maggottrack.sql
  assets/css/style.css  assets/js/app.js  uploads/
```
Keamanan: session auth + regenerate ID, CSRF token di semua POST, PDO prepared statements, escape output (XSS), password_hash, pembatasan akses per pemilik batch, validasi upload (MIME, getimagesize, nama acak, eksekusi PHP diblokir di uploads/), config/includes/python/database diblokir `.htaccess`.
