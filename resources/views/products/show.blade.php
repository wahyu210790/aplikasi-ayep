@extends('layouts.app')

@section('title', 'Detail Harga: ' . $product->name)

@section('content')
    <div style="margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center; gap: 8px; flex-wrap: wrap;">
        <a href="{{ route('products.index') }}" class="btn btn-outline btn-sm">
            &larr; Kembali ke Pencarian
        </a>
        <div style="display: flex; gap: 8px;">
            <a href="{{ route('products.edit', $product) }}" class="btn btn-primary btn-sm">
                Edit Barang / Harga
            </a>
        </div>
    </div>

    <article class="card">
        @include('products._price_card_body', ['product' => $product, 'showActions' => false])

        <div style="margin-top: 16px; padding-top: 12px; border-top: 1px solid var(--border-light); font-size: 0.82rem; color: var(--text-secondary); display: flex; flex-wrap: wrap; justify-content: space-between; gap: 6px;">
            <span>Dibuat: {{ $product->created_at?->format('d/m/Y H:i') ?? '-' }}</span>
            <span>Terakhir diperbarui: {{ $product->updated_at?->format('d/m/Y H:i') ?? '-' }}</span>
        </div>

        <div class="card-actions" style="margin-top: 16px;">
            <a href="{{ route('products.edit', $product) }}" class="btn btn-primary">
                Edit Data &amp; Harga
            </a>
            <form action="{{ route('products.destroy', $product) }}" method="POST" style="flex: 1 1 auto;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus barang \x22{{ addslashes($product->name) }}\x22 secara permanen?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger btn-block">
                    Hapus Barang
                </button>
            </form>
        </div>
    </article>
@endsection
