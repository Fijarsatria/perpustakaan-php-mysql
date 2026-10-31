# Hasil pengujian

Tanggal: 31 Oktober 2026. Browser: Microsoft Edge, otomatisasi Playwright. Backend: XAMPP PHP dan MariaDB.

- PASS tambah buku melalui formulir browser
- PASS edit penerbit dan stok 5 menjadi 8, ID 5
- PASS batal hapus mempertahankan data
- PASS hapus buku uji ID 8 dan pencarian kosong
- PASS ID tidak valid 400 dan buku tidak ada 404
- PASS validasi server 422 serta CSRF tambah/hapus 403
- PASS isi skrip lama dirender sebagai teks
- PASS tampilan seluler 390px tanpa overflow halaman

Konfirmasi SQL: SELECT untuk ID 5 dan 8 hanya mengembalikan ID 5, penerbit Bentang Pustaka - Edisi Revisi, stok 8. Data lama tidak dihapus. Tiga buku contoh ditambahkan melalui formulir.
