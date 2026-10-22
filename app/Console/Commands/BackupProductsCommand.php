<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class BackupProductsCommand extends Command
{
    public const MAX_BACKUPS_TO_KEEP = 14;

    protected $signature = 'backup:products';

    protected $description = 'Ekspor seluruh data products dan product_additional_prices ke file JSON di storage/app/private/backups dan simpan maksimal 14 file terakhir';

    public function handle(): int
    {
        $backupDir = storage_path('app/private/backups');

        if (! File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $now = Carbon::now('Asia/Jakarta');
        $baseFilename = 'products-'.$now->format('Y-m-d_Hi').'.json';
        $filePath = $backupDir.DIRECTORY_SEPARATOR.$baseFilename;

        $products = Product::with('additionalPrices')
            ->orderBy('id')
            ->get()
            ->map(function (Product $product): array {
                return [
                    'id' => $product->id,
                    'name' => (string) $product->name,
                    'barcode' => $product->barcode !== null ? (string) $product->barcode : null,
                    'category' => $product->category !== null ? (string) $product->category : null,
                    'cost_price' => $product->cost_price !== null ? (string) $product->cost_price : null,
                    'supplier_price' => $product->supplier_price !== null ? (string) $product->supplier_price : null,
                    'retail_price' => $product->retail_price !== null ? (string) $product->retail_price : null,
                    'processed_price' => $product->processed_price !== null ? (string) $product->processed_price : null,
                    'notes' => $product->notes !== null ? (string) $product->notes : null,
                    'created_at' => $product->created_at?->toIso8601String(),
                    'updated_at' => $product->updated_at?->toIso8601String(),
                    'additional_prices' => $product->additionalPrices->map(function ($additional): array {
                        return [
                            'id' => $additional->id,
                            'label' => (string) $additional->label,
                            'price' => (string) $additional->price,
                            'sort_order' => (int) $additional->sort_order,
                            'created_at' => $additional->created_at?->toIso8601String(),
                            'updated_at' => $additional->updated_at?->toIso8601String(),
                        ];
                    })->values()->all(),
                ];
            })
            ->values()
            ->all();

        $payload = [
            'format' => 'aplikasi-ayep-products-backup',
            'version' => 1,
            'generated_at' => $now->toIso8601String(),
            'timezone' => 'Asia/Jakarta',
            'products_count' => count($products),
            'products' => $products,
        ];

        $jsonContent = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($jsonContent === false) {
            $this->error('Gagal membuat format JSON backup.');

            return self::FAILURE;
        }

        File::put($filePath, $jsonContent);

        $this->pruneOldBackups($backupDir);

        $this->info("Backup berhasil disimpan ke: {$filePath} (".count($products).' barang)');

        return self::SUCCESS;
    }

    /**
     * Simpan hanya 14 file backup terakhir di folder storage/app/private/backups.
     */
    private function pruneOldBackups(string $backupDir): void
    {
        $files = collect(File::files($backupDir))
            ->filter(fn ($file) => str_starts_with($file->getFilename(), 'products-') && str_ends_with($file->getFilename(), '.json'))
            ->sortByDesc(fn ($file) => $file->getFilename())
            ->values();

        if ($files->count() <= self::MAX_BACKUPS_TO_KEEP) {
            return;
        }

        $filesToDelete = $files->slice(self::MAX_BACKUPS_TO_KEEP);
        foreach ($filesToDelete as $oldFile) {
            File::delete($oldFile->getPathname());
        }
    }
}
