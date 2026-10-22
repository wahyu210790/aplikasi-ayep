<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductAdditionalPrice;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class RestoreProductsCommand extends Command
{
    protected $signature = 'restore:products
        {file : Nama file di folder backup atau path lengkap ke file JSON backup}
        {--force : Jalankan restore langsung tanpa konfirmasi interaktif}';

    protected $description = 'Pulihkan data products dan product_additional_prices dari file backup JSON';

    public function handle(): int
    {
        $fileArgument = (string) $this->argument('file');
        $resolvedPath = $this->resolveBackupPath($fileArgument);

        if ($resolvedPath === null || ! File::isFile($resolvedPath)) {
            $this->error("File backup tidak ditemukan: {$fileArgument}");

            return self::FAILURE;
        }

        $rawContent = File::get($resolvedPath);
        $decoded = json_decode($rawContent, true);

        $validationError = $this->validateBackupPayload($decoded);
        if ($validationError !== null) {
            $this->error("File backup tidak valid: {$validationError}");

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('PERINGATAN: Proses restore akan mengganti seluruh data barang saat ini dengan data dari file backup. Lanjutkan?', false)) {
            $this->warn('Proses restore dibatalkan oleh pengguna.');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($decoded) {
                ProductAdditionalPrice::query()->delete();
                Product::query()->delete();

                foreach ($decoded['products'] as $item) {
                    $product = new Product([
                        'name' => (string) $item['name'],
                        'barcode' => isset($item['barcode']) && $item['barcode'] !== '' ? (string) $item['barcode'] : null,
                        'category' => isset($item['category']) && $item['category'] !== '' ? (string) $item['category'] : null,
                        'cost_price' => $item['cost_price'] ?? null,
                        'supplier_price' => $item['supplier_price'] ?? null,
                        'retail_price' => $item['retail_price'] ?? null,
                        'processed_price' => $item['processed_price'] ?? null,
                        'notes' => isset($item['notes']) && $item['notes'] !== '' ? (string) $item['notes'] : null,
                    ]);

                    if (isset($item['id']) && is_numeric($item['id'])) {
                        $product->id = (int) $item['id'];
                    }

                    $hasCustomTimestamps = false;
                    if (! empty($item['created_at'])) {
                        $product->created_at = Carbon::parse($item['created_at']);
                        $hasCustomTimestamps = true;
                    }
                    if (! empty($item['updated_at'])) {
                        $product->updated_at = Carbon::parse($item['updated_at']);
                        $hasCustomTimestamps = true;
                    }
                    if ($hasCustomTimestamps) {
                        $product->timestamps = false;
                    }

                    $product->save();

                    $additionalRows = $item['additional_prices'] ?? [];
                    foreach ($additionalRows as $idx => $addRow) {
                        $additional = new ProductAdditionalPrice([
                            'label' => (string) $addRow['label'],
                            'price' => $addRow['price'],
                            'sort_order' => isset($addRow['sort_order']) ? (int) $addRow['sort_order'] : ($idx + 1),
                        ]);
                        $additional->product_id = $product->id;

                        if (isset($addRow['id']) && is_numeric($addRow['id'])) {
                            $additional->id = (int) $addRow['id'];
                        }

                        $hasAdditionalTimestamps = false;
                        if (! empty($addRow['created_at'])) {
                            $additional->created_at = Carbon::parse($addRow['created_at']);
                            $hasAdditionalTimestamps = true;
                        }
                        if (! empty($addRow['updated_at'])) {
                            $additional->updated_at = Carbon::parse($addRow['updated_at']);
                            $hasAdditionalTimestamps = true;
                        }
                        if ($hasAdditionalTimestamps) {
                            $additional->timestamps = false;
                        }

                        $additional->save();
                    }
                }
            });
        } catch (Throwable $e) {
            $this->error('Gagal memulihkan data backup (transaksi dibatalkan, data lama tetap aman): '.$e->getMessage());

            return self::FAILURE;
        }

        $count = count($decoded['products']);
        $this->info("Berhasil memulihkan {$count} barang dari file backup: {$resolvedPath}");

        return self::SUCCESS;
    }

    private function resolveBackupPath(string $input): ?string
    {
        if (File::isFile($input)) {
            return $input;
        }

        $backupDir = (string) config('backup.path', storage_path('app/private/backups'));
        $inConfiguredBackups = rtrim($backupDir, '/\\').DIRECTORY_SEPARATOR.ltrim($input, '/\\');
        if (File::isFile($inConfiguredBackups)) {
            return $inConfiguredBackups;
        }

        $inPrivateBackups = storage_path('app/private/backups/'.ltrim($input, '/\\'));
        if (File::isFile($inPrivateBackups)) {
            return $inPrivateBackups;
        }

        $inStorageBase = base_path($input);
        if (File::isFile($inStorageBase)) {
            return $inStorageBase;
        }

        return null;
    }

    private function validateBackupPayload(mixed $decoded): ?string
    {
        if (! is_array($decoded)) {
            return 'Isi file bukan format JSON objek yang valid.';
        }

        if (($decoded['format'] ?? null) !== 'aplikasi-ayep-products-backup') {
            return 'Identitas format backup tidak dikenali.';
        }

        if (! isset($decoded['products']) || ! is_array($decoded['products'])) {
            return 'Daftar "products" tidak ditemukan atau bukan berupa array.';
        }

        $seenBarcodes = [];

        foreach ($decoded['products'] as $index => $item) {
            if (! is_array($item)) {
                return "Data barang pada indeks #{$index} tidak valid.";
            }

            if (! isset($item['name']) || ! is_string($item['name']) || trim($item['name']) === '') {
                return "Nama barang pada indeks #{$index} kosong atau tidak valid.";
            }

            if (array_key_exists('barcode', $item) && $item['barcode'] !== null && $item['barcode'] !== '') {
                if (! is_string($item['barcode']) && ! is_numeric($item['barcode'])) {
                    return "Barcode pada indeks #{$index} tidak valid.";
                }
                $bc = (string) $item['barcode'];
                if (isset($seenBarcodes[$bc])) {
                    return "Terdapat barcode duplikat ({$bc}) di dalam file backup.";
                }
                $seenBarcodes[$bc] = true;
            }

            foreach (['cost_price', 'supplier_price', 'retail_price', 'processed_price'] as $priceField) {
                if (isset($item[$priceField]) && $item[$priceField] !== null && $item[$priceField] !== '') {
                    if (! is_numeric($item[$priceField]) || (float) $item[$priceField] < 0) {
                        return "Nilai {$priceField} pada indeks #{$index} tidak valid.";
                    }
                }
            }

            if (isset($item['additional_prices'])) {
                if (! is_array($item['additional_prices'])) {
                    return "Daftar additional_prices pada indeks #{$index} bukan array.";
                }

                foreach ($item['additional_prices'] as $addIdx => $addRow) {
                    if (! is_array($addRow)) {
                        return "Baris additional_prices #{$addIdx} pada barang indeks #{$index} tidak valid.";
                    }
                    if (! isset($addRow['label']) || ! is_string($addRow['label']) || trim($addRow['label']) === '') {
                        return "Label harga tambahan #{$addIdx} pada barang indeks #{$index} kosong.";
                    }
                    if (! isset($addRow['price']) || ! is_numeric($addRow['price']) || (float) $addRow['price'] < 0) {
                        return "Nilai harga tambahan #{$addIdx} pada barang indeks #{$index} tidak valid.";
                    }
                }
            }
        }

        return null;
    }
}
