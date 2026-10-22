# Panduan Penggunaan, Instalasi, dan Deployment
## Aplikasi Database Harga Barang dengan Barcode Scanner (`aplikasi-ayep`)

Aplikasi ini adalah alat pengecekan harga barang berbasis web yang dirancang khusus agar cepat, ringan, dan nyaman digunakan melalui browser HP maupun komputer.

---

## 1. Cara Menjalankan Aplikasi Secara Lokal

1. Pastikan layanan **MySQL** di Laragon atau XAMPP sudah berjalan (aktif di port `3306`).
2. Buka terminal (PowerShell / Command Prompt) dan masuk ke folder proyek:
   ```powershell
   cd C:\laragon\www\aplikasi-ayep
   ```
3. Jalankan server pengembangan Laravel:
   ```powershell
   php artisan serve
   ```
4. Buka browser dan kunjungi alamat:
   - **`http://localhost:8000`** (atau `http://127.0.0.1:8000`)

---

## 2. Cara Menyiapkan Database MySQL & Menjalankan Migration dengan Aman

Pada lingkungan pengembangan lokal ini, database **`aplikasi_ayep`** telah dibuat dan dimigrasikan secara aman. Jika Anda memasang aplikasi ini di komputer baru atau server baru:

1. Buat database MySQL kosong baru bernama `aplikasi_ayep` (dengan karakter set `utf8mb4`).
2. Salin file `.env.example` menjadi `.env` jika belum ada:
   ```powershell
   copy .env.example .env
   php artisan key:generate
   ```
3. Sesuaikan pengaturan database di dalam file `.env`:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=aplikasi_ayep
   DB_USERNAME=nama_user_database_anda
   DB_PASSWORD=password_database_anda
   ```
4. Periksa koneksi database terlebih dahulu sebelum menjalankan migrasi:
   ```powershell
   php artisan db:show
   ```
5. Jalankan migrasi standar (yang hanya membuat tabel yang belum ada tanpa menghapus data lama):
   ```powershell
   php artisan migrate
   ```
   > **Peringatan Keamanan Data:** Jangan pernah menjalankan perintah `migrate:fresh`, `migrate:reset`, atau `db:wipe` pada database yang sudah berisi data harga barang karena perintah tersebut akan menghapus seluruh tabel dan isinya.

---

## 3. Cara Menggunakan Pencarian Harga Barang

1. Buka halaman utama aplikasi.
2. Ketik **nama barang** (misalnya: `Indomie`, `Kopi`, `Beras`) atau **nomor barcode** pada kolom pencarian.
3. Tekan tombol **Cari** (atau tekan Enter di keyboard HP).
4. Anda juga dapat memfilter barang berdasarkan **Kategori** jika sudah ada kategori yang tersimpan.
5. Untuk kembali melihat seluruh daftar barang, tekan tombol **Reset Pencarian**.

---

## 4. Cara Memindai (Scan) Barcode dengan Kamera

1. Pada halaman utama (atau di dalam form Tambah/Edit Barang), tekan tombol biru besar **"📷 Scan Barcode dengan Kamera"**.
2. Browser HP akan meminta izin akses kamera. Tekan **"Izinkan" (Allow)**.
3. Arahkan kamera belakang HP ke garis-garis barcode pada kemasan barang dengan pencahayaan yang cukup.
4. Begitu barcode terbaca:
   - Kamera akan **otomatis tertutup** dan mengunci hasil bacaan agar tidak terjadi pemindaian ganda.
   - **Jika barang sudah terdaftar:** Aplikasi langsung membuka halaman detail barang beserta seluruh daftar harganya.
   - **Jika barang belum terdaftar:** Aplikasi menampilkan pesan **"Barang tidak ditemukan"** beserta tombol **"+ Tambah Barang dengan Barcode Ini"** yang otomatis mengisi nomor barcode tersebut ke dalam formulir barang baru.
5. Jika Anda batal memindai, tekan tombol merah **"Tutup Kamera"**.

---

## 5. Cara Menambahkan dan Mengedit Barang

### Menambahkan Barang Baru:
1. Tekan tombol **"+ Tambah Barang"** di pojok kanan atas (atau dari tombol hasil scan barcode yang belum ditemukan).
2. Isi **Nama Barang** (wajib).
3. **Barcode** bersifat opsional:
   - Boleh dikosongkan untuk barang curah/olahan tanpa kemasan.
   - Jika diisi, angka nol di depan (contoh: `0089686010015`) akan disimpan secara utuh sebagai teks.
   - Jika barcode yang Anda masukkan ternyata sudah terdaftar pada barang lain, aplikasi tidak akan membuat data ganda, melainkan langsung menampilkan barang yang sudah ada.
4. Isi **Kategori** bila diperlukan (opsional).
5. Isi **Daftar Harga Utama** dalam Rupiah:
   - **Harga Jual Satuan / Eceran**
   - **Harga Setelah Diolah / Dimasak / Diseduh** (diinput manual, tidak dihitung otomatis)
   - **Harga Jual Supplier**
   - **Harga Modal**
   - *Catatan Penting:* Kosongkan kolom harga jika jenis harga tersebut belum digunakan (akan tampil sebagai *"Belum diisi"*). Isi dengan angka `0` jika harga memang bernilai `Rp 0`.
6. Isi **Catatan** bila diperlukan, lalu tekan **"Simpan Barang Baru"**.

### Mengedit atau Menghapus Barang:
- Tekan tombol **"Edit Harga"** pada kartu barang atau halaman detail untuk mengubah nama, barcode, kategori, maupun harga.
- Tekan tombol **"Hapus"** untuk menghapus barang. Aplikasi akan menampilkan kotak dialog konfirmasi terlebih dahulu agar barang tidak terhapus karena ketidaksengajaan sentuhan di layar HP.

---

## 6. Cara Mengelola Harga Tambahan

Setiap barang dapat memiliki banyak harga tambahan dengan nama/label yang bebas Anda tentukan sendiri (contoh: *Harga Renceng*, *Harga 1 Dus*, *Harga Warung*, *Harga Cup Besar*):

1. Pada halaman **Tambah Barang** atau **Edit Barang**, gulir ke bagian **"Harga Tambahan (Nama Bebas)"**.
2. Tekan tombol **"+ Tambah Harga Lain"**.
3. Isi **Nama Harga** (misalnya: `Harga Renceng (10 pcs)`) dan **Nilai Harga (Rp)** (misalnya: `18500`).
4. Anda dapat menambahkan hingga maksimal **20 baris** harga tambahan untuk satu barang.
5. Untuk menghapus salah satu harga tambahan, tekan tombol **"Hapus Baris"** pada baris tersebut, lalu tekan **"Simpan Perubahan"**.

---

## 7. Cara Mengatasi Masalah Kamera Saat Scan Barcode

1. **Muncul pesan akses kamera diblokir karena bukan HTTPS:**
   - Browser modern di HP (Chrome Android & Safari iPhone) mewajibkan koneksi aman **`https://`** untuk mengaktifkan kamera (kecuali jika dibuka langsung di komputer lokal melalui `http://localhost`).
   - Pastikan saat aplikasi di-hosting, domain Anda sudah menggunakan `https://`.
2. **Izin kamera tidak sengaja ditolak (Blocked/Denied):**
   - Ketuk ikon gembok / pengaturan situs di sebelah kiri alamat URL pada browser HP Anda $\rightarrow$ ubah izin **Kamera (Camera)** menjadi **Izinkan (Allow)** $\rightarrow$ muat ulang halaman.
3. **Kamera HP buram atau barcode sulit terbaca:**
   - Pastikan cahaya ruangan cukup terang dan hindari pantulan lampu tepat di atas plastik kemasan.
   - Jaga jarak kamera sekitar 10–15 cm dari barcode agar kamera HP dapat melakukan *autofocus*.
   - Jika kemasan rusak/tidak terbaca kamera, Anda selalu dapat mengetik angka yang tertera di bawah garis barcode secara manual pada kolom pencarian.

---

## 8. Panduan Deployment ke Server Hosting (cPanel / VPS) & Konfigurasi HTTPS

1. **Syarat Server Hosting:**
   - PHP versi **8.2** atau lebih baru (dengan ekstensi standar `pdo_mysql`, `mbstring`, `openssl`, `ctype`, `json`).
   - Database **MySQL / MariaDB**.
   - Sertifikat **SSL / HTTPS aktif** (wajib agar fitur kamera scanner berfungsi di HP).
2. **Tidak Perlu Build Node.js di Hosting:**
   - Seluruh aset tampilan (`public/css/app-mobile.css`) dan pustaka scanner kamera (`public/js/html5-qrcode.min.js` versi `2.3.8`, lisensi Apache-2.0) sudah disimpan secara lokal di dalam folder `public/`.
3. **Langkah Upload dan Konfigurasi Produksi:**
   - Upload seluruh file proyek ke server hosting.
   - Arahkan *Document Root* domain/subdomain ke folder **`public`** milik proyek.
   - Buat database MySQL dan pengguna database baru di panel hosting Anda.
   - Buat file `.env` di server (jangan pernah meng-upload file `.env` ke repositori publik) dengan pengaturan produksi yang aman:
     ```env
     APP_NAME="Cek Harga Barang"
     APP_ENV=production
     APP_DEBUG=false
     APP_URL=https://domain-anda.com
     ```
   - Jalankan perintah berikut di terminal hosting:
     ```bash
     php artisan key:generate
     php artisan migrate --force
     php artisan config:cache
     php artisan route:cache
     php artisan view:cache
     ```

---

## 9. Risiko Keamanan Akses Publik Tanpa Login (Penting untuk Diketahui)

Sesuai spesifikasi yang telah ditetapkan oleh pemilik proyek, aplikasi ini **sengaja tidak menggunakan fitur login/autentikasi** agar dapat langsung dibuka dan digunakan dengan cepat dari perangkat mana pun.

Harap perhatikan konsekuensi dan batasan keamanan berikut:
1. **Siapa pun yang mengetahui alamat URL aplikasi dapat mengaksesnya:** Pengunjung yang memiliki URL aplikasi dapat melihat seluruh data (termasuk **Harga Modal**), serta dapat menambah, mengubah, atau menghapus data barang.
2. **Mitigasi teknis yang telah dipasang (`CSRF`, validasi server, escaping anti-XSS, pembatasan ukuran input, dan *rate limiting*)** berfungsi melindungi server dari serangan injeksi kode dan spam bot otomatis, **namun tidak menggantikan kontrol akses berbasis identitas (login)**. Aplikasi ini tidak boleh diklaim tahan terhadap perubahan/penghapusan data oleh pihak luar selama URL pengelolaan terbuka untuk publik.
3. **File `robots.txt` dan meta tag `noindex, nofollow`** telah dipasang untuk meminta mesin pencari (seperti Google) tidak mengindeks halaman aplikasi, namun **`robots.txt` dan `noindex` bukanlah fitur keamanan/pengunci akses**.
4. **Rekomendasi Perlindungan Tambahan Sebelum Deployment ke Internet Publik:**
   - Gunakan nama subdomain yang tidak umum, atau aktifkan fitur **Password Protect Directories (HTTP Basic Auth)** bawaan cPanel/Cloudflare Access jika Anda ingin membatasi akses hanya untuk perangkat toko/keluarga tanpa perlu mengubah kode aplikasi.

---

## 10. Langkah Backup dan Pemulihan (Restore) Database

Karena aplikasi tidak menggunakan login dan data dapat diubah/dihapus sewaktu-waktu oleh pengguna yang mengakses URL, aplikasi ini menyediakan fitur **Backup & Restore Otomatis (JSON)** selain backup SQL biasa:

### A. Backup Otomatis Harian & Backup Manual via Artisan (`backup:products`)
Aplikasi telah dijadwalkan untuk mengekspor seluruh data tabel `products` dan `product_additional_prices` (termasuk menjaga angka `0` di depan barcode) setiap hari pukul **02:00 WIB (`Asia/Jakarta`)** ke folder privat `storage/app/private/backups/` dengan format nama `products-YYYY-MM-DD_HHMMSS.json`. Sistem otomatis hanya menyimpan **14 file backup terakhir** dan menghapus file backup yang lebih lama.

Untuk menjalankan backup secara manual kapan saja:
```bash
php artisan backup:products
```

### B. Cara Memulihkan Data dari File Backup (`restore:products`)
Untuk memulihkan data dari file backup di `storage/app/private/backups/` (perintah akan meminta konfirmasi terlebih dahulu, mengembalikan data beserta waktu `created_at` dan `updated_at` asli, serta berjalan di dalam transaksi database yang aman):
```bash
php artisan restore:products products-2026-10-23_020000.json

# Atau tambahkan --force jika ingin melewati pertanyaan konfirmasi:
php artisan restore:products products-2026-10-23_020000.json --force
```

### C. Cara Memasang Cron Job di Server Hosting agar Backup Harian Berjalan Otomatis
Tambahkan baris berikut pada menu **Cron Jobs** di cPanel / server hosting Anda (setiap menit):
```cron
* * * * * cd /path/ke/aplikasi && php artisan schedule:run >> /dev/null 2>&1
```

### D. Backup & Restore Manual melalui phpMyAdmin / MySQL CLI:
1. **Backup via phpMyAdmin:** Buka database **`aplikasi_ayep`** $\rightarrow$ tab **Export (Ekspor)** $\rightarrow$ metode **Quick (Cepat)** format **SQL** $\rightarrow$ klik **Go (Kirim)**.
2. **Backup via CLI:**
   ```bash
   mysqldump -u username_db -p aplikasi_ayep > backup_aplikasi_ayep_$(date +%F).sql
   ```
3. **Restore via phpMyAdmin:** Pilih database **`aplikasi_ayep`** $\rightarrow$ tab **Import (Impor)** $\rightarrow$ pilih file `.sql` $\rightarrow$ klik **Go (Kirim)**.
