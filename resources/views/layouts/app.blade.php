<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=5">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Database Harga Barang')</title>
    <link rel="stylesheet" href="{{ asset('css/app-mobile.css') }}">
</head>
<body>
    <header class="app-header">
        <div class="header-inner">
            <a href="{{ route('products.index') }}" class="brand-link">
                <span class="brand-icon" aria-hidden="true">Rp</span>
                <span>Cek Harga Barang</span>
            </a>
            <div style="display: flex; gap: 8px; align-items: center;">
                <a href="{{ route('products.create') }}" class="btn btn-primary btn-sm">
                    + Tambah Barang
                </a>
            </div>
        </div>
    </header>

    <main class="container">
        @if (session('success'))
            <div class="alert alert-success" role="status">
                <strong>Berhasil:</strong> {{ session('success') }}
            </div>
        @endif

        @if (session('warning'))
            <div class="alert alert-warning" role="alert">
                <strong>Perhatian:</strong> {{ session('warning') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger" role="alert">
                <strong>Kesalahan:</strong> {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <strong>Mohon periksa kembali data yang diisi:</strong>
                <ul style="margin: 6px 0 0 0; padding-left: 20px;">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>

    <script src="{{ asset('js/html5-qrcode.min.js') }}"></script>
    <script src="{{ asset('js/scanner.js') }}"></script>
    @stack('scripts')
</body>
</html>
