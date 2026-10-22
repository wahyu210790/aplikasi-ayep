@extends('layouts.app')

@section('title', 'Edit Barang: ' . $product->name)

@section('content')
    <div style="margin-bottom: 12px;">
        <a href="{{ route('products.show', $product) }}" class="btn btn-outline btn-sm">
            &larr; Kembali ke Detail Barang
        </a>
    </div>

    <section class="card">
        <h1 class="card-title">Edit Data &amp; Harga Barang</h1>
        <form action="{{ route('products.update', $product) }}" method="POST">
            @csrf
            @method('PUT')
            @include('products._form', [
                'product' => $product,
                'categories' => $categories,
                'submitLabel' => 'Simpan Perubahan',
                'cancelUrl' => route('products.show', $product),
            ])
        </form>
    </section>
@endsection
