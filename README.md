# Aplikasi Database Harga Barang dengan Barcode Scanner

Aplikasi web sederhana, cepat, dan *mobile-first* berbasis **Laravel 12 + Blade + MySQL** untuk menyimpan, mencari, dan mengecek harga barang berdasarkan nama barang atau pemindaian barcode kamera HP.

## Fitur Utama

- **Pencarian Cepat:** Cari barang berdasarkan nama barang atau nomor barcode, dilengkapi filter kategori.
- **Scan Barcode Kamera:** Menggunakan pustaka lokal `html5-qrcode` (`v2.3.8`, Apache-2.0) untuk memindai barcode melalui kamera belakang HP atau webcam tanpa ketergantungan CDN eksternal.
- **Penanganan Barcode Cerdas:**
  - Barcode bersifat opsional (barang curah/olahan tanpa barcode tetap dapat disimpan dan dicari).
  - Disimpan sebagai `VARCHAR` agar angka nol di depan (mis. `0089686010015`) tidak hilang.
  - Mencegah duplikasi barcode di level aplikasi maupun database (`UNIQUE`). Jika barcode sudah terdaftar, aplikasi langsung menampilkan barang yang sudah ada.
  - Jika barcode hasil scan belum terdaftar, aplikasi menampilkan pesan **"Barang tidak ditemukan"** dan tombol **Tambah Barang** dengan barcode terisi otomatis.
- **Manajemen Harga Lengkap (Rupiah / `DECIMAL(15, 2)`):**
  - Harga Jual Satuan / Eceran
  - Harga Setelah Diolah / Dimasak / Diseduh (input manual)
  - Harga Jual Supplier
  - Harga Modal
  - **Harga Tambahan Dinamis:** Tambah/edit/hapus hingga 20 jenis harga tambahan dengan nama bebas per barang (misal: *Harga Renceng*, *Harga Dus*, *Harga Warung*).
  - Membedakan secara akurat antara harga yang belum diisi (`NULL` $\rightarrow$ *"Belum diisi"*) dan harga bernilai nol (`0.00` $\rightarrow$ *"Rp 0"*).

## Dokumentasi Lengkap

Silakan baca panduan lengkap penggunaan, instalasi lokal, konfigurasi HTTPS kamera, catatan risiko keamanan akses publik tanpa login, serta prosedur backup database di:

👉 **[PANDUAN_APLIKASI.md](PANDUAN_APLIKASI.md)**

## Perintah Cepat Menjalankan Secara Lokal

```powershell
# Menjalankan server aplikasi
php artisan serve

# Menjalankan pengujian otomatis (PHPUnit)
php artisan test

# Memeriksa kerapian standar kode (Laravel Pint)
vendor\bin\pint --test
```
