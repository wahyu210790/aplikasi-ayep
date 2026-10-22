<?php

namespace Tests\Feature;

use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Models\ProductAdditionalPrice;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductPriceDatabaseTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 1. Halaman awal dan kondisi database kosong (Empty State).
     */
    public function test_initial_page_and_empty_database_state(): void
    {
        // Pastikan di dalam transaksi ini daftar barang kosong
        ProductAdditionalPrice::query()->delete();
        Product::query()->delete();

        $response = $this->get(route('products.index'));

        $response->assertStatus(200);
        $response->assertSee('Cek Harga Barang');
        $response->assertSee('Scan Barcode dengan Kamera');
        $response->assertSee('Database Barang Masih Kosong');
        $response->assertSee('Tambah Barang Pertama');
    }

    /**
     * 2. Tambah, tampilkan, edit, dan hapus barang secara lengkap.
     */
    public function test_create_show_edit_and_delete_product(): void
    {
        // Create
        $storeResponse = $this->post(route('products.store'), [
            'name' => 'Kopi Kapal Api Special 65g',
            'barcode' => '8991002101234',
            'category' => 'Minuman',
            'cost_price' => '6500',
            'supplier_price' => '7000',
            'retail_price' => '8000',
            'processed_price' => '4000',
            'notes' => 'Bisa dijual seduh per gelas',
            'additional_prices' => [
                ['label' => 'Harga Renceng (10 pcs)', 'price' => '75000'],
            ],
        ]);

        $product = Product::where('barcode', '8991002101234')->first();
        $this->assertNotNull($product);
        $storeResponse->assertRedirect(route('products.show', $product));

        // Show
        $showResponse = $this->get(route('products.show', $product));
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Kopi Kapal Api Special 65g');
        $showResponse->assertSee('8991002101234');
        $showResponse->assertSee('Minuman');
        $showResponse->assertSee('Rp 6.500');
        $showResponse->assertSee('Rp 7.000');
        $showResponse->assertSee('Rp 8.000');
        $showResponse->assertSee('Rp 4.000');
        $showResponse->assertSee('Harga Renceng (10 pcs)');
        $showResponse->assertSee('Rp 75.000');

        // Edit page
        $editPageResponse = $this->get(route('products.edit', $product));
        $editPageResponse->assertStatus(200);
        $editPageResponse->assertSee('Edit Data & Harga Barang');

        // Update
        $updateResponse = $this->put(route('products.update', $product), [
            'name' => 'Kopi Kapal Api Special 65g Baru',
            'barcode' => '8991002101234',
            'category' => 'Kopi',
            'cost_price' => '6800',
            'supplier_price' => '7200',
            'retail_price' => '8500',
            'processed_price' => '4500',
            'notes' => 'Harga naik per Oktober',
            'additional_prices' => [
                ['label' => 'Harga Renceng (10 pcs)', 'price' => '78000'],
            ],
        ]);

        $updateResponse->assertRedirect(route('products.show', $product));
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Kopi Kapal Api Special 65g Baru',
            'category' => 'Kopi',
            'retail_price' => '8500.00',
        ]);

        // Delete
        $deleteResponse = $this->delete(route('products.destroy', $product));
        $deleteResponse->assertRedirect(route('products.index'));
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('product_additional_prices', ['product_id' => $product->id]);
    }

    /**
     * 3. Barcode kosong (opsional): beberapa barang tanpa barcode tetap boleh ditambahkan dan dicari berdasarkan nama.
     */
    public function test_optional_empty_barcode_allows_multiple_products_without_unique_conflict(): void
    {
        $this->post(route('products.store'), [
            'name' => 'Telur Ayam Negeri Curah 1 Kg',
            'barcode' => '',
            'category' => 'Sembako',
            'retail_price' => '28000',
        ])->assertRedirect();

        $this->post(route('products.store'), [
            'name' => 'Beras Ramos Curah 1 Liter',
            'barcode' => '   ',
            'category' => 'Sembako',
            'retail_price' => '13500',
        ])->assertRedirect();

        $telur = Product::where('name', 'Telur Ayam Negeri Curah 1 Kg')->first();
        $beras = Product::where('name', 'Beras Ramos Curah 1 Liter')->first();

        $this->assertNotNull($telur);
        $this->assertNotNull($beras);
        $this->assertNull($telur->barcode);
        $this->assertNull($beras->barcode);

        // Dapat dicari berdasarkan nama dan menampilkan badge Tanpa Barcode
        $searchResp = $this->get(route('products.index', ['q' => 'Telur Ayam']));
        $searchResp->assertStatus(200);
        $searchResp->assertSee('Telur Ayam Negeri Curah 1 Kg');
        $searchResp->assertSee('Tanpa Barcode');
    }

    /**
     * 4. Barcode dengan angka nol di depan tidak hilang (disimpan sebagai string).
     */
    public function test_barcode_preserves_leading_zeros(): void
    {
        $leadingZeroBarcode = '0089686010015';

        $this->post(route('products.store'), [
            'name' => 'Indomie Goreng Spesial',
            'barcode' => $leadingZeroBarcode,
            'retail_price' => '3500',
        ])->assertRedirect();

        $product = Product::where('barcode', $leadingZeroBarcode)->first();
        $this->assertNotNull($product);
        $this->assertSame('0089686010015', $product->barcode);

        $showResp = $this->get(route('products.show', $product));
        $showResp->assertStatus(200);
        $showResp->assertSee('0089686010015');
    }

    /**
     * 5. Penolakan barcode duplikat & menampilkan barang yang sudah ada (termasuk dua permintaan berdekatan).
     */
    public function test_duplicate_barcode_does_not_create_duplicate_and_shows_existing_product(): void
    {
        $barcode = '0899276111222';

        // Permintaan pertama membuat barang
        $firstResponse = $this->post(route('products.store'), [
            'name' => 'Aqua Botol 600ml',
            'barcode' => $barcode,
            'retail_price' => '4000',
        ]);

        $existingProduct = Product::where('barcode', $barcode)->first();
        $this->assertNotNull($existingProduct);
        $firstResponse->assertRedirect(route('products.show', $existingProduct));

        // Permintaan kedua berdekatan dengan barcode yang sama
        $secondResponse = $this->post(route('products.store'), [
            'name' => 'Aqua Botol Duplikat',
            'barcode' => $barcode,
            'retail_price' => '5000',
        ]);

        // Tidak boleh membuat barang baru, harus mengarahkan ke barang yang sudah ada
        $this->assertSame(1, Product::where('barcode', $barcode)->count());
        $secondResponse->assertRedirect(route('products.show', $existingProduct));
        $secondResponse->assertSessionHas('warning');

        // Jika mencoba membuka halaman create dengan ?barcode= yang sudah terdaftar, langsung diarahkan ke barang yang ada
        $createPrefillResp = $this->get(route('products.create', ['barcode' => $barcode]));
        $createPrefillResp->assertRedirect(route('products.show', $existingProduct));

        // Jika mengedit barang B agar memakai barcode milik barang A, validasi harus menolak
        $otherProduct = Product::create([
            'name' => 'Le Minerale 600ml',
            'barcode' => '0899276999888',
            'retail_price' => '4000',
        ]);

        $updateDupResp = $this->from(route('products.edit', $otherProduct))
            ->put(route('products.update', $otherProduct), [
                'name' => 'Le Minerale 600ml',
                'barcode' => $barcode,
                'retail_price' => '4000',
            ]);

        $updateDupResp->assertRedirect(route('products.edit', $otherProduct));
        $updateDupResp->assertSessionHasErrors(['barcode']);
    }

    /**
     * 6. Pencarian berdasarkan nama dan pencarian berdasarkan barcode (halaman utama & API lookup).
     */
    public function test_search_by_name_and_barcode(): void
    {
        $p1 = Product::create([
            'name' => 'Susu Ultra Milk Coklat 250ml',
            'barcode' => '8998009010203',
            'category' => 'Minuman',
            'retail_price' => '7500',
        ]);

        $p2 = Product::create([
            'name' => 'Teh Pucuk Harum 350ml',
            'barcode' => '8999001112223',
            'category' => 'Minuman',
            'retail_price' => '4000',
        ]);

        // Cari berdasarkan sebagian nama
        $nameSearch = $this->get(route('products.index', ['q' => 'Ultra Milk']));
        $nameSearch->assertStatus(200);
        $nameSearch->assertSee('Susu Ultra Milk Coklat 250ml');
        $nameSearch->assertDontSee('Teh Pucuk Harum 350ml');

        // Cari berdasarkan barcode di halaman index
        $barcodeSearch = $this->get(route('products.index', ['barcode' => '8999001112223']));
        $barcodeSearch->assertStatus(200);
        $barcodeSearch->assertSee('Teh Pucuk Harum 350ml');
        $barcodeSearch->assertSee('Hasil Barcode Ditemukan');

        // Cari melalui endpoint JSON lookup scanner kamera
        $lookupJson = $this->getJson(route('products.lookup', ['barcode' => '8998009010203']));
        $lookupJson->assertStatus(200)
            ->assertJson([
                'found' => true,
                'barcode' => '8998009010203',
                'product' => [
                    'id' => $p1->id,
                    'name' => 'Susu Ultra Milk Coklat 250ml',
                    'retail_price_formatted' => 'Rp 7.500',
                ],
            ]);
    }

    /**
     * 7. Alur barcode tidak ditemukan menampilkan pesan "Barang tidak ditemukan" dan tombol tambah barang dengan barcode terisi otomatis.
     */
    public function test_barcode_not_found_flow_and_prefilled_create_button(): void
    {
        $missingBarcode = '0011223344556';

        // Via endpoint JSON lookup (yang dipanggil kamera scanner)
        $lookupResp = $this->getJson(route('products.lookup', ['barcode' => $missingBarcode]));
        $lookupResp->assertStatus(200)
            ->assertJson([
                'found' => false,
                'barcode' => $missingBarcode,
                'message' => 'Barang tidak ditemukan',
                'create_url' => route('products.create', ['barcode' => $missingBarcode]),
            ]);

        // Via halaman pencarian utama
        $indexResp = $this->get(route('products.index', ['barcode' => $missingBarcode]));
        $indexResp->assertStatus(200);
        $indexResp->assertSee('Barang tidak ditemukan');
        $indexResp->assertSee($missingBarcode);
        $indexResp->assertSee(route('products.create', ['barcode' => $missingBarcode]), false);

        // Buka form tambah barang dari tombol tersebut: kolom barcode harus terisi otomatis
        $createResp = $this->get(route('products.create', ['barcode' => $missingBarcode]));
        $createResp->assertStatus(200);
        $createResp->assertSee('value="'.$missingBarcode.'"', false);
    }

    /**
     * 8. Nilai harga NULL (Belum diisi), Rp0 (0.00), dan nilai positif dibedakan secara akurat.
     */
    public function test_price_null_zero_and_positive_values_are_distinguished(): void
    {
        $this->post(route('products.store'), [
            'name' => 'Air Es Isi Ulang',
            'barcode' => '',
            'cost_price' => '0',          // Diisi 0 -> Rp 0
            'supplier_price' => '',       // Kosong -> NULL -> Belum diisi
            'retail_price' => '2000',     // Positif -> Rp 2.000
            'processed_price' => null,    // Kosong -> NULL -> Belum diisi
        ])->assertRedirect();

        $product = Product::where('name', 'Air Es Isi Ulang')->firstOrFail();

        $this->assertSame('0.00', $product->cost_price);
        $this->assertNull($product->supplier_price);
        $this->assertSame('2000.00', $product->retail_price);
        $this->assertNull($product->processed_price);

        $this->assertSame('Rp 0', Product::formatRupiah($product->cost_price));
        $this->assertSame('Belum diisi', Product::formatRupiah($product->supplier_price));
        $this->assertSame('Rp 2.000', Product::formatRupiah($product->retail_price));

        $showResp = $this->get(route('products.show', $product));
        $showResp->assertStatus(200);
        $showResp->assertSee('Rp 0');
        $showResp->assertSee('Belum diisi');
        $showResp->assertSee('Rp 2.000');
    }

    /**
     * 9. Tambah, edit, dan hapus beberapa harga tambahan pada sebuah barang.
     */
    public function test_add_edit_and_remove_multiple_additional_prices(): void
    {
        $this->post(route('products.store'), [
            'name' => 'Mie Sedaap Goreng',
            'barcode' => '8998866200011',
            'retail_price' => '3500',
            'additional_prices' => [
                ['label' => 'Harga 5 Bungkus', 'price' => '16500'],
                ['label' => 'Harga 1 Dus (40 bks)', 'price' => '124000'],
                ['label' => 'Harga Warung Tetangga', 'price' => '3200'],
            ],
        ])->assertRedirect();

        $product = Product::where('barcode', '8998866200011')->with('additionalPrices')->firstOrFail();
        $this->assertCount(3, $product->additionalPrices);

        // Edit: ubah 1 harga tambahan, hapus 1, dan tambah 1 baru
        $this->put(route('products.update', $product), [
            'name' => 'Mie Sedaap Goreng',
            'barcode' => '8998866200011',
            'retail_price' => '3500',
            'additional_prices' => [
                ['label' => 'Harga 1 Dus (40 bks)', 'price' => '125000'],
                ['label' => 'Harga Matang + Telur', 'price' => '10000'],
            ],
        ])->assertRedirect(route('products.show', $product));

        $product->refresh()->load('additionalPrices');
        $this->assertCount(2, $product->additionalPrices);
        $this->assertSame('Harga 1 Dus (40 bks)', $product->additionalPrices[0]->label);
        $this->assertSame('125000.00', $product->additionalPrices[0]->price);
        $this->assertSame('Harga Matang + Telur', $product->additionalPrices[1]->label);
        $this->assertSame('10000.00', $product->additionalPrices[1]->price);

        // Hapus semua harga tambahan dengan mengosongkan array
        $this->put(route('products.update', $product), [
            'name' => 'Mie Sedaap Goreng',
            'barcode' => '8998866200011',
            'retail_price' => '3500',
            'additional_prices' => [],
        ])->assertRedirect(route('products.show', $product));

        $this->assertCount(0, $product->fresh()->additionalPrices);
    }

    /**
     * 10. Validasi input tidak valid (nama kosong, harga negatif, format salah, harga tambahan tanpa label).
     */
    public function test_invalid_inputs_are_rejected_by_server_validation(): void
    {
        $response = $this->post(route('products.store'), [
            'name' => '',
            'barcode' => 'INVALID BARCODE WITH SPACES AND <SCRIPT>',
            'cost_price' => '-5000',
            'retail_price' => 'bukan-angka',
            'additional_prices' => [
                ['label' => '', 'price' => '10000'],
                ['label' => 'Harga Grosir', 'price' => '-100'],
            ],
        ]);

        $response->assertSessionHasErrors([
            'name',
            'barcode',
            'cost_price',
            'retail_price',
            'additional_prices.0.label',
            'additional_prices.1.price',
        ]);
    }

    /**
     * 11. Batas jumlah harga tambahan per barang (maksimal 20 baris).
     */
    public function test_additional_prices_count_limit_is_enforced(): void
    {
        $tooManyRows = [];
        for ($i = 1; $i <= ProductRequest::MAX_ADDITIONAL_PRICES + 1; $i++) {
            $tooManyRows[] = [
                'label' => 'Harga Ke-'.$i,
                'price' => (string) ($i * 1000),
            ];
        }

        $response = $this->post(route('products.store'), [
            'name' => 'Barang Banyak Harga',
            'retail_price' => '5000',
            'additional_prices' => $tooManyRows,
        ]);

        $response->assertSessionHasErrors(['additional_prices']);
    }

    /**
     * 12. Penanganan barang yang tidak ditemukan (404 Not Found pada ID yang tidak ada).
     */
    public function test_non_existent_product_returns_404(): void
    {
        $this->get('/products/999999999')->assertStatus(404);
        $this->get('/products/999999999/edit')->assertStatus(404);
        $this->put('/products/999999999', ['name' => 'Tes'])->assertStatus(404);
        $this->delete('/products/999999999')->assertStatus(404);
    }

    /**
     * 13. Proteksi CSRF dan Rate Limiting diterapkan pada rute aplikasi.
     */
    public function test_csrf_protection_and_rate_limiting_are_applied(): void
    {
        $route = Route::getRoutes()->getByName('products.store');
        $this->assertNotNull($route);
        $middlewares = $route->gatherMiddleware();
        $this->assertContains('web', $middlewares);
        $this->assertContains('throttle:price-write', $middlewares);

        $readRoute = Route::getRoutes()->getByName('products.index');
        $this->assertContains('throttle:price-read', $readRoute->gatherMiddleware());

        // Secara default VerifyCsrfToken::runningUnitTests() melewati pengecekan di PHPUnit.
        // Di sini kita pasang subclass khusus testing yang menonaktifkan bypass tersebut
        // untuk membuktikan secara nyata bahwa POST tanpa token CSRF ditolak dengan HTTP 419,
        // sedangkan POST dengan token CSRF yang sah diterima.
        $this->app->bind(ValidateCsrfToken::class, function ($app) {
            return new class($app, $app->make(Encrypter::class)) extends ValidateCsrfToken
            {
                protected function runningUnitTests(): bool
                {
                    return false;
                }
            };
        });

        // 1) Permintaan POST tanpa token CSRF harus ditolak dengan status 419 (Page Expired / Token Mismatch)
        $csrfRejectedResponse = $this->post(route('products.store'), [
            'name' => 'Barang Tanpa Token CSRF',
            'retail_price' => '5000',
        ]);
        $csrfRejectedResponse->assertStatus(419);
        $this->assertDatabaseMissing('products', ['name' => 'Barang Tanpa Token CSRF']);

        // 2) Permintaan POST dengan token CSRF sesi yang sah harus berhasil (302 Redirect)
        $csrfValidResponse = $this->withSession(['_token' => 'valid-test-csrf-token'])
            ->post(route('products.store'), [
                '_token' => 'valid-test-csrf-token',
                'name' => 'Barang Dengan Token CSRF Sah',
                'retail_price' => '5000',
            ]);
        $csrfValidResponse->assertStatus(302);
        $this->assertDatabaseHas('products', ['name' => 'Barang Dengan Token CSRF Sah']);

        // Kembalikan binding default VerifyCsrfToken untuk menguji Rate Limiter secara terisolasi
        $this->app->offsetUnset(ValidateCsrfToken::class);
        RateLimiter::clear(sha1('price-write|127.0.0.1'));

        // 3) Uji rate limiter benar-benar membatasi setelah melewati ambang batas (40 per menit untuk write)
        $this->withoutMiddleware(ValidateCsrfToken::class);
        for ($i = 0; $i < 40; $i++) {
            $this->from('http://127.0.0.1')
                ->withServerVariables(['REMOTE_ADDR' => '10.10.10.99'])
                ->post(route('products.store'), ['name' => '']);
        }

        $throttledResponse = $this->withServerVariables(['REMOTE_ADDR' => '10.10.10.99'])
            ->post(route('products.store'), ['name' => 'Barang Ke-41']);
        $throttledResponse->assertStatus(429);
    }

    /**
     * 14. Tidak ada login/autentikasi sesuai keputusan produk (seluruh halaman utama dapat diakses langsung).
     */
    public function test_no_authentication_or_login_routes_exist(): void
    {
        $this->assertFalse(Route::has('login'));
        $this->assertFalse(Route::has('register'));
        $this->assertFalse(Schema::hasTable('users'));

        $this->get(route('products.index'))->assertStatus(200);
        $this->get(route('products.create'))->assertStatus(200);
    }

    /**
     * 15. Tidak ada fungsi di luar ruang lingkup (tidak ada stok, kasir, transaksi, import/export, akuntansi).
     */
    public function test_no_out_of_scope_features_or_columns_exist(): void
    {
        $columns = Schema::getColumnListing('products');

        $this->assertNotContains('stock', $columns);
        $this->assertNotContains('qty', $columns);
        $this->assertNotContains('profit', $columns);
        $this->assertNotContains('margin', $columns);

        $this->assertFalse(Schema::hasTable('transactions'));
        $this->assertFalse(Schema::hasTable('orders'));
        $this->assertFalse(Schema::hasTable('carts'));
        $this->assertFalse(Schema::hasTable('stock_movements'));
    }

    /**
     * 16. Backup dan Restore otomatis: membuat file JSON di storage/app/private/backups,
     * menjaga angka 0 di depan barcode ("0089686010015"), hanya menyimpan 14 file terakhir,
     * memulihkan data persis sama dengan konfirmasi, serta menolak file rusak tanpa mengubah data.
     */
    public function test_backup_and_restore_products_commands_and_schedule(): void
    {
        $backupDir = storage_path('app/private/backups');
        File::deleteDirectory($backupDir);

        try {
            // Siapkan data awal termasuk barcode dengan angka 0 di depan dan harga tambahan
            $p1 = Product::create([
                'name' => 'Indomie Goreng Spesial',
                'barcode' => '0089686010015',
                'category' => 'Mie Instan',
                'cost_price' => '3000.00',
                'supplier_price' => '3200.00',
                'retail_price' => '3500.00',
                'processed_price' => '7000.00',
                'notes' => 'Bisa dimasak pakai telur',
            ]);
            $p1->additionalPrices()->create([
                'label' => 'Harga 1 Dus (40 bks)',
                'price' => '124000.00',
                'sort_order' => 1,
            ]);

            $p2 = Product::create([
                'name' => 'Gula Pasir Curah 1 Kg',
                'barcode' => null,
                'category' => 'Sembako',
                'cost_price' => '0.00',
                'supplier_price' => null,
                'retail_price' => '17500.00',
                'processed_price' => null,
                'notes' => null,
            ]);

            // Uji rotasi 14 file backup: jalankan 16 kali pada waktu berbeda
            $baseTime = Carbon::create(2026, 10, 10, 2, 0, 0, 'Asia/Jakarta');
            for ($day = 1; $day <= 16; $day++) {
                Carbon::setTestNow($baseTime->copy()->addDays($day));
                $this->artisan('backup:products')->assertExitCode(0);
            }
            Carbon::setTestNow();

            $files = collect(File::files($backupDir))
                ->filter(fn ($f) => str_starts_with($f->getFilename(), 'products-') && str_ends_with($f->getFilename(), '.json'))
                ->sortBy(fn ($f) => $f->getFilename())
                ->values();

            // Hanya 14 file terakhir yang disimpan (hari ke-1 dan ke-2 terhapus otomatis)
            $this->assertCount(14, $files);
            $this->assertSame('products-2026-10-13_0200.json', $files->first()->getFilename());
            $latestBackupFile = $files->last();
            $this->assertSame('products-2026-10-26_0200.json', $latestBackupFile->getFilename());

            // Pastikan tidak ada file backup di folder public
            $this->assertDirectoryDoesNotExist(public_path('backups'));

            // Periksa isi file JSON dan keutuhan barcode "0089686010015"
            $rawJson = File::get($latestBackupFile->getPathname());
            $this->assertStringContainsString('"barcode": "0089686010015"', $rawJson);

            $decoded = json_decode($rawJson, true);
            $this->assertSame('aplikasi-ayep-products-backup', $decoded['format']);
            $this->assertSame(2, $decoded['products_count']);
            $this->assertSame('0089686010015', $decoded['products'][0]['barcode']);
            $this->assertSame('Harga 1 Dus (40 bks)', $decoded['products'][0]['additional_prices'][0]['label']);
            $this->assertSame('124000.00', $decoded['products'][0]['additional_prices'][0]['price']);

            // Ubah/hapus data di database, lalu uji penolakan file rusak terlebih dahulu
            ProductAdditionalPrice::query()->delete();
            Product::query()->delete();
            $tempExisting = Product::create([
                'name' => 'Data Sementara Sebelum Restore',
                'barcode' => '999000111',
                'retail_price' => '10000.00',
            ]);

            $corruptedPath = $backupDir.DIRECTORY_SEPARATOR.'corrupted.json';
            File::put($corruptedPath, '{broken-json-content');

            $this->artisan('restore:products', ['file' => 'corrupted.json'])
                ->assertExitCode(1);

            // Data lama tidak boleh berubah ketika file backup rusak
            $this->assertDatabaseHas('products', [
                'id' => $tempExisting->id,
                'name' => 'Data Sementara Sebelum Restore',
                'barcode' => '999000111',
            ]);

            // Uji jika struktur JSON salah (misal barcode negatif/harga tidak valid)
            $invalidPayloadPath = $backupDir.DIRECTORY_SEPARATOR.'invalid-structure.json';
            File::put($invalidPayloadPath, json_encode([
                'format' => 'aplikasi-ayep-products-backup',
                'products' => [
                    ['name' => 'Barang Rusak', 'retail_price' => '-5000'],
                ],
            ]));

            $this->artisan('restore:products', ['file' => $invalidPayloadPath])
                ->assertExitCode(1);

            $this->assertDatabaseHas('products', [
                'id' => $tempExisting->id,
                'name' => 'Data Sementara Sebelum Restore',
            ]);

            // Uji jika konfirmasi dijawab "no" -> dibatalkan dan data lama tidak berubah
            $this->artisan('restore:products', ['file' => $latestBackupFile->getFilename()])
                ->expectsConfirmation('PERINGATAN: Proses restore akan mengganti seluruh data barang saat ini dengan data dari file backup. Lanjutkan?', 'no')
                ->assertExitCode(1);

            $this->assertDatabaseHas('products', ['name' => 'Data Sementara Sebelum Restore']);

            // Sekarang uji restore dengan konfirmasi "yes" -> data kembali persis seperti semula
            $this->artisan('restore:products', ['file' => $latestBackupFile->getFilename()])
                ->expectsConfirmation('PERINGATAN: Proses restore akan mengganti seluruh data barang saat ini dengan data dari file backup. Lanjutkan?', 'yes')
                ->assertExitCode(0);

            $this->assertDatabaseMissing('products', ['name' => 'Data Sementara Sebelum Restore']);
            $this->assertSame(2, Product::count());

            $restoredP1 = Product::with('additionalPrices')->where('barcode', '0089686010015')->first();
            $this->assertNotNull($restoredP1);
            $this->assertSame('Indomie Goreng Spesial', $restoredP1->name);
            $this->assertSame('0089686010015', $restoredP1->barcode);
            $this->assertSame('3500.00', $restoredP1->retail_price);
            $this->assertCount(1, $restoredP1->additionalPrices);
            $this->assertSame('Harga 1 Dus (40 bks)', $restoredP1->additionalPrices->first()->label);
            $this->assertSame('124000.00', $restoredP1->additionalPrices->first()->price);

            $restoredP2 = Product::where('name', 'Gula Pasir Curah 1 Kg')->first();
            $this->assertNotNull($restoredP2);
            $this->assertNull($restoredP2->barcode);
            $this->assertSame('0.00', $restoredP2->cost_price);
            $this->assertNull($restoredP2->supplier_price);

            // Verifikasi jadwal scheduler harian jam 02:00 Asia/Jakarta
            $schedule = app(Schedule::class);
            $backupEvent = collect($schedule->events())->first(fn ($event) => str_contains((string) $event->command, 'backup:products'));
            $this->assertNotNull($backupEvent);
            $this->assertSame('0 2 * * *', $backupEvent->expression);
            $this->assertSame('Asia/Jakarta', (string) $backupEvent->timezone);
        } finally {
            Carbon::setTestNow();
            File::deleteDirectory($backupDir);
        }
    }
}
