@extends('layouts.app')

@section('title', 'Tambah Barang Baru')

@section('content')
    <div style="margin-bottom: 12px;">
        <a href="{{ route('products.index') }}" class="btn btn-outline btn-sm">
            &larr; Kembali ke Pencarian
        </a>
    </div>

    <section class="card">
        <h1 class="card-title">Tambah Barang Baru</h1>
        <form action="{{ route('products.store') }}" method="POST">
            @csrf
            @include('products._form', [
                'product' => $product,
                'categories' => $categories,
                'submitLabel' => 'Simpan Barang Baru',
                'cancelUrl' => route('products.index'),
            ])
        </form>
    </section>
@endsection
