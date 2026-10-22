@php
    use App\Models\Product;
@endphp

<div class="product-header">
    <div>
        <h3 class="product-title">
            <a href="{{ route('products.show', $product) }}" style="color: inherit;">
                {{ $product->name }}
            </a>
        </h3>
        <div class="badges-row">
            @if ($product->barcode !== null && $product->barcode !== '')
                <span class="badge badge-barcode" title="Barcode Barang">
                    Barcode: {{ $product->barcode }}
                </span>
            @else
                <span class="badge badge-muted">Tanpa Barcode</span>
            @endif

            @if ($product->category)
                <span class="badge badge-category">{{ $product->category }}</span>
            @endif
        </div>
    </div>
</div>

<div class="price-grid price-grid-4">
    <div class="price-box price-box-retail">
        <div class="price-label">Harga Jual Satuan / Eceran</div>
        @if ($product->retail_price !== null)
            <div class="price-value">{{ Product::formatRupiah($product->retail_price) }}</div>
        @else
            <div class="price-value price-empty">Belum diisi</div>
        @endif
    </div>

    <div class="price-box">
        <div class="price-label">Harga Olahan / Masak / Seduh</div>
        @if ($product->processed_price !== null)
            <div class="price-value">{{ Product::formatRupiah($product->processed_price) }}</div>
        @else
            <div class="price-value price-empty">Belum diisi</div>
        @endif
    </div>

    <div class="price-box">
        <div class="price-label">Harga Jual Supplier</div>
        @if ($product->supplier_price !== null)
            <div class="price-value">{{ Product::formatRupiah($product->supplier_price) }}</div>
        @else
            <div class="price-value price-empty">Belum diisi</div>
        @endif
    </div>

    <div class="price-box">
        <div class="price-label">Harga Modal</div>
        @if ($product->cost_price !== null)
            <div class="price-value">{{ Product::formatRupiah($product->cost_price) }}</div>
        @else
            <div class="price-value price-empty">Belum diisi</div>
        @endif
    </div>
</div>

@if ($product->additionalPrices && $product->additionalPrices->isNotEmpty())
    <div class="additional-prices-section">
        <h4 class="additional-prices-title">Harga Tambahan ({{ $product->additionalPrices->count() }})</h4>
        <div class="additional-price-list">
            @foreach ($product->additionalPrices as $additional)
                <div class="additional-price-item">
                    <span class="additional-price-label">{{ $additional->label }}</span>
                    <span class="additional-price-val">{{ $additional->formattedPrice() }}</span>
                </div>
            @endforeach
        </div>
    </div>
@endif

@if ($product->notes)
    <div style="font-size: 0.9rem; color: var(--text-secondary); background-color: var(--bg-subtle); padding: 8px 12px; border-radius: var(--radius-sm);">
        <strong>Catatan:</strong> {{ $product->notes }}
    </div>
@endif

@if (! empty($showActions))
    <div class="card-actions">
        <a href="{{ route('products.show', $product) }}" class="btn btn-outline btn-sm">
            Detail
        </a>
        <a href="{{ route('products.edit', $product) }}" class="btn btn-outline btn-sm">
            Edit Harga
        </a>
        <form action="{{ route('products.destroy', $product) }}" method="POST" style="display: inline; flex: 1 1 auto;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus barang \x22{{ addslashes($product->name) }}\x22 beserta seluruh data harganya?');">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger-outline btn-sm btn-block">
                Hapus
            </button>
        </form>
    </div>
@endif
