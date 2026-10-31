# Ruang Baca - Perpustakaan PHP & MySQL

Kelanjutan materi perpustakaan buku dengan fitur edit dan delete yang tersimpan di MySQL. Tampilan katalog responsif, tanpa kebutuhan CDN atau internet.

## Menjalankan di XAMPP
1. Aktifkan Apache dan MySQL.
2. Salin folder proyek ke `C:/xampp/htdocs/perpustakaan`.
3. Impor `database/schema.sql` melalui phpMyAdmin. Jika tabel dari materi awal sudah ada, skema ini tidak menghapus data.
4. Opsional, impor `database/sample.sql` untuk tiga buku contoh pada database baru (cukup sekali).
5. Buka http://localhost/perpustakaan/ .

Kebutuhan: PHP 8+, ekstensi PDO MySQL dan mbstring, MySQL/MariaDB. `config.php` memakai konfigurasi lokal XAMPP: host 127.0.0.1, database perpustakaan, pengguna root, kata sandi kosong. Variabel lingkungan DB_HOST, DB_NAME, DB_USER, DB_PASSWORD dapat menggantinya.

## Fitur
- Tambah buku beserta judul, penulis, penerbit, tahun terbit, dan stok.
- Edit mengisi formulir dengan data lama; Simpan perubahan memperbarui baris berdasarkan ID.
- Hapus membuka halaman konfirmasi. Hanya tombol Ya, hapus buku dengan metode POST yang menghapus data. Batal mempertahankan data.
- Pencarian judul, penulis, penerbit; ringkasan koleksi dan tampilan ponsel.
- Prepared statements, output escaping, token CSRF, validasi server dan browser.

## Struktur
- index.php: daftar buku, formulir, validasi, INSERT, UPDATE dan DELETE.
- config.php: koneksi PDO.
- assets/style.css: tampilan Ruang Baca.
- database/schema.sql: struktur tabel sesuai materi asli, termasuk kolom `Tahun Terbit`.
- database/sample.sql: contoh data bersih, bukan ekspor data pribadi.
- docs/Laporan-Edit-Delete.pdf: screenshot fungsi yang benar-benar dijalankan.
- docs/hasil-pengujian.md: catatan pengujian.

## Pengujian 31 Oktober 2026
Tambah, edit, batal hapus, hapus, pencarian, validasi server, ID tidak valid, token CSRF, output escaping, dan lebar ponsel 390px telah diuji pada PHP/MySQL XAMPP lokal. Hasil edit juga diperiksa langsung lewat query MySQL: ID 5 memiliki stok 8, sedangkan buku uji ID 8 sudah tidak ada. ID pada instalasi baru dapat berbeda.

Aplikasi ini adalah tugas CRUD lokal dan belum menyediakan login atau hak akses pengguna. Jalankan melalui XAMPP lokal.
