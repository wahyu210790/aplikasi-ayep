<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'barcode',
        'category',
        'cost_price',
        'supplier_price',
        'retail_price',
        'processed_price',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'supplier_price' => 'decimal:2',
            'retail_price' => 'decimal:2',
            'processed_price' => 'decimal:2',
        ];
    }

    /**
     * Daftar harga tambahan untuk barang ini.
     */
    public function additionalPrices(): HasMany
    {
        return $this->hasMany(ProductAdditionalPrice::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Format angka desimal menjadi format Rupiah atau "Belum diisi" jika NULL.
     */
    public static function formatRupiah(mixed $value, string $emptyText = 'Belum diisi'): string
    {
        if ($value === null || $value === '') {
            return $emptyText;
        }

        $numeric = (float) $value;
        $decimals = (floor($numeric) != $numeric) ? 2 : 0;

        return 'Rp '.number_format($numeric, $decimals, ',', '.');
    }

    /**
     * Format angka untuk ditampilkan kembali di dalam form input (tanpa .00 yang tidak perlu).
     */
    public static function formatInputPrice(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $numeric = (float) $value;
        if (floor($numeric) == $numeric) {
            return (string) (int) $numeric;
        }

        return number_format($numeric, 2, '.', '');
    }

    /**
     * Scope pencarian berdasarkan nama atau barcode.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $clean = trim((string) $term);
        if ($clean === '') {
            return $query;
        }

        $escaped = addcslashes($clean, '%_\\');

        return $query->where(function (Builder $q) use ($clean, $escaped) {
            $q->where('barcode', $clean)
                ->orWhere('barcode', 'like', "%{$escaped}%")
                ->orWhere('name', 'like', "%{$escaped}%");
        });
    }
}
