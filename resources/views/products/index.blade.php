@extends('layouts.app')

@section('title', 'Cek Harga & Scan Barcode Barang')

@section('content')
    {{-- Panel Utama Scan & Pencarian --}}
    <section class="card" aria-label="Pencarian dan Scan Barcode">
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <button type="button" id="btn-open-scanner" class="btn btn-scan btn-block">
                <span aria-hidden="true">📷</span>
                <span>Scan Barcode dengan Kamera</span>
            </button>

            <form id="search-form" action="{{ route('products.index') }}" method="GET" class="search-bar-stack">
                <div class="search-input-row">
                    <input
                        type="search"
                        id="search-input"
                        name="q"
                        value="{{ $search !== '' ? $search : $barcodeParam }}"
                        class="form-control"
                        placeholder="Ketik nama barang atau nomor barcode..."
                        autocomplete="off"
                        aria-label="Ketik nama barang atau nomor barcode"
                    >
                    <button type="submit" class="btn btn-primary">
                        Cari
                    </button>
                </div>

                @if ($categories->isNotEmpty())
                    <div style="display: flex; gap: 8px; align-items: center;">
                        <select name="category" id="category-filter" class="form-control" aria-label="Filter Kategori" onchange="this.form.submit()">
                            <option value="">Semua Kategori</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat }}" @selected($categoryParam === $cat)>{{ $cat }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                @if ($search !== '' || $barcodeParam !== '' || $categoryParam !== '')
                    <a href="{{ route('products.index') }}" class="btn btn-outline btn-sm">
                        Reset Pencarian
                    </a>
                @endif
            </form>
        </div>
    </section>

    {{-- Panel Kamera Scanner (tersembunyi secara default sampai tombol Scan ditekan) --}}
    <section id="scanner-panel" class="scanner-panel" style="display: none;" aria-live="polite">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px;">
            <strong style="font-size: 1rem;">Pemindai Barcode Kamera</strong>
            <button type="button" id="btn-close-scanner" class="btn btn-danger btn-sm">
                Tutup Kamera
            </button>
        </div>

        <div id="scanner-reader" class="scanner-viewport"></div>

        <div id="scanner-status" class="scanner-status">
            Menyiapkan kamera...
        </div>
    </section>

    {{-- Indikator Loading saat mencari --}}
    <div id="lookup-loading" class="loading-indicator" role="status" aria-live="polite">
        <span>Sedang mencari data barang...</span>
    </div>

    {{-- Error State Jaringan / Server --}}
    <div id="lookup-error" class="alert alert-danger" style="display: none;" role="alert"></div>

    {{-- Hasil Pencarian Cepat dari Scan Kamera (Tidak Ditemukan) --}}
    <section id="scan-not-found-box" class="card" style="display: {{ $notFoundBarcode ? 'block' : 'none' }}; border: 2px solid var(--warning-border); background-color: var(--warning-bg);">
        <h2 class="card-title" style="color: var(--warning);">Barang tidak ditemukan</h2>
        <p style="margin: 0 0 12px 0; color: var(--text-main);">
            Kode / Barcode <strong id="not-found-barcode-text" style="font-family: monospace;">{{ $notFoundBarcode }}</strong> belum terdaftar di dalam database harga.
        </p>
        <div class="card-actions">
            <a id="btn-add-missing-barcode" href="{{ route('products.create', ['barcode' => $notFoundBarcode]) }}" class="btn btn-primary">
                + Tambah Barang dengan Barcode Ini
            </a>
            <a href="{{ route('products.index') }}" class="btn btn-outline">
                Lihat Semua Barang
            </a>
        </div>
    </section>

    {{-- Jika ditemukan kecocokan Barcode yang persis --}}
    @if ($exactBarcodeMatch)
        <section class="card product-card-highlight" aria-label="Hasil Pencarian Barcode Tepat">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                <span class="badge badge-category">Hasil Barcode Ditemukan</span>
                <a href="{{ route('products.show', $exactBarcodeMatch) }}" class="btn btn-outline btn-sm">Buka Halaman Detail</a>
            </div>
            @include('products._price_card_body', ['product' => $exactBarcodeMatch, 'showActions' => true])
        </section>
    @endif

    {{-- Daftar Barang --}}
    @if ($totalAllProducts === 0)
        {{-- Empty State: Database masih kosong --}}
        <section class="card empty-state">
            <div class="empty-state-icon" aria-hidden="true">📦</div>
            <h2 class="card-title">Database Barang Masih Kosong</h2>
            <p style="color: var(--text-secondary); margin: 0 auto 16px auto; max-width: 460px;">
                Belum ada data harga barang yang tersimpan. Anda dapat mulai menambahkan barang pertama secara manual atau melalui tombol Scan Barcode.
            </p>
            <a href="{{ route('products.create') }}" class="btn btn-primary">
                + Tambah Barang Pertama
            </a>
        </section>
    @elseif ($products->isEmpty())
        {{-- Pencarian tidak menemukan hasil --}}
        @if (! $notFoundBarcode)
            <section class="card empty-state">
                <h2 class="card-title">Barang tidak ditemukan</h2>
                <p style="color: var(--text-secondary); margin: 0 0 16px 0;">
                    Tidak ada barang yang cocok dengan pencarian <strong>"{{ $search !== '' ? $search : $categoryParam }}"</strong>.
                </p>
                <div class="card-actions" style="justify-content: center;">
                    <a href="{{ route('products.create', $search !== '' ? ['name' => $search] : []) }}" class="btn btn-primary">
                        + Tambah Barang Baru
                    </a>
                    <a href="{{ route('products.index') }}" class="btn btn-outline">
                        Tampilkan Semua Barang
                    </a>
                </div>
            </section>
        @endif
    @else
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
            <span style="font-size: 0.9rem; color: var(--text-secondary); font-weight: 600;">
                Menampilkan {{ $products->count() }} dari {{ $products->total() }} barang
            </span>
        </div>

        <div class="product-list">
            @foreach ($products as $product)
                <article class="product-card">
                    @include('products._price_card_body', ['product' => $product, 'showActions' => true])
                </article>
            @endforeach
        </div>

        @if ($products->hasPages())
            <div class="pagination-wrapper">
                {{ $products->links() }}
            </div>
        @endif
    @endif
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const btnOpenScanner = document.getElementById('btn-open-scanner');
    const btnCloseScanner = document.getElementById('btn-close-scanner');
    const searchForm = document.getElementById('search-form');
    const loadingEl = document.getElementById('lookup-loading');
    const errorEl = document.getElementById('lookup-error');
    const notFoundBox = document.getElementById('scan-not-found-box');
    const notFoundText = document.getElementById('not-found-barcode-text');
    const btnAddMissing = document.getElementById('btn-add-missing-barcode');

    if (searchForm && loadingEl) {
        searchForm.addEventListener('submit', function () {
            loadingEl.classList.add('active');
        });
    }

    function lookupScannedBarcode(barcode) {
        if (errorEl) {
            errorEl.style.display = 'none';
        }
        if (loadingEl) {
            loadingEl.classList.add('active');
        }

        const lookupUrl = @json(route('products.lookup')) + '?barcode=' + encodeURIComponent(barcode);

        fetch(lookupUrl, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(function (response) {
            if (!response.ok) {
                throw new Error('Server merespons dengan status ' + response.status);
            }
            return response.json();
        })
        .then(function (data) {
            if (loadingEl) {
                loadingEl.classList.remove('active');
            }

            if (data.found && data.redirect_url) {
                window.location.href = data.redirect_url;
                return;
            }

            // Barang tidak ditemukan -> tampilkan pesan dan tombol Tambah Barang dengan barcode terisi otomatis
            if (notFoundBox && notFoundText && btnAddMissing) {
                notFoundText.textContent = barcode;
                btnAddMissing.href = data.create_url || (@json(route('products.create')) + '?barcode=' + encodeURIComponent(barcode));
                notFoundBox.style.display = 'block';
                notFoundBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                window.location.href = @json(route('products.index')) + '?barcode=' + encodeURIComponent(barcode);
            }
        })
        .catch(function () {
            if (loadingEl) {
                loadingEl.classList.remove('active');
            }
            if (errorEl) {
                errorEl.textContent = 'Gagal menghubungi server saat mencari barcode. Memuat pencarian halaman...';
                errorEl.style.display = 'block';
            }
            window.location.href = @json(route('products.index')) + '?barcode=' + encodeURIComponent(barcode);
        });
    }

    if (btnOpenScanner) {
        btnOpenScanner.addEventListener('click', function () {
            if (window.AyepBarcodeScanner) {
                window.AyepBarcodeScanner.start({
                    containerId: 'scanner-reader',
                    panelId: 'scanner-panel',
                    statusId: 'scanner-status',
                    onDetected: function (barcode) {
                        lookupScannedBarcode(barcode);
                    }
                });
            }
        });
    }

    if (btnCloseScanner) {
        btnCloseScanner.addEventListener('click', function () {
            if (window.AyepBarcodeScanner) {
                window.AyepBarcodeScanner.stop('scanner-panel');
            }
        });
    }
});
</script>
@endpush
