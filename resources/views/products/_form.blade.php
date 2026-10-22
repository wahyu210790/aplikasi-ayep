@php
    use App\Models\Product;
    use App\Http\Requests\ProductRequest;

    $oldAdditional = old('additional_prices');
    if (is_array($oldAdditional)) {
        $additionalRows = $oldAdditional;
    } elseif ($product->exists && $product->relationLoaded('additionalPrices')) {
        $additionalRows = $product->additionalPrices->map(fn ($item) => [
            'label' => $item->label,
            'price' => Product::formatInputPrice($item->price),
        ])->toArray();
    } else {
        $additionalRows = [];
    }
@endphp

<div class="form-group">
    <label for="name" class="form-label">Nama Barang <span style="color: var(--danger);">*</span></label>
    <input
        type="text"
        id="name"
        name="name"
        value="{{ old('name', $product->name) }}"
        class="form-control"
        placeholder="Contoh: Indomie Goreng Spesial 85g"
        required
        maxlength="255"
    >
</div>

<div class="form-group">
    <label for="barcode" class="form-label">Barcode Barang (Opsional)</label>
    <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <input
            type="text"
            id="barcode"
            name="barcode"
            value="{{ old('barcode', $product->barcode) }}"
            class="form-control"
            style="flex: 1 1 200px; font-family: monospace;"
            placeholder="Kosongkan jika barang tidak memiliki barcode"
            maxlength="100"
            autocomplete="off"
        >
        <button type="button" id="btn-form-scan-barcode" class="btn btn-outline">
            📷 Scan Barcode
        </button>
    </div>
    <span class="form-hint">
        Angka nol di depan (mis. <code>089686...</code>) tetap tersimpan utuh. Jika barcode sudah terdaftar, aplikasi akan menampilkan barang yang sudah ada.
    </span>
</div>

{{-- Panel Kamera Scanner di dalam Form --}}
<div id="form-scanner-panel" class="scanner-panel" style="display: none;">
    <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px;">
        <strong>Scan Barcode untuk Form Ini</strong>
        <button type="button" id="btn-form-close-scanner" class="btn btn-danger btn-sm">
            Tutup Kamera
        </button>
    </div>
    <div id="form-scanner-reader" class="scanner-viewport"></div>
    <div id="form-scanner-status" class="scanner-status">Menyiapkan kamera...</div>
</div>

<div class="form-group">
    <label for="category" class="form-label">Kategori (Opsional)</label>
    <input
        type="text"
        id="category"
        name="category"
        value="{{ old('category', $product->category) }}"
        class="form-control"
        list="existing-categories-list"
        placeholder="Contoh: Sembako, Minuman, Rokok, Olahan..."
        maxlength="100"
    >
    @if (isset($categories) && $categories->isNotEmpty())
        <datalist id="existing-categories-list">
            @foreach ($categories as $cat)
                <option value="{{ $cat }}"></option>
            @endforeach
        </datalist>
    @endif
</div>

<hr style="border: none; border-top: 1px solid var(--border-light); margin: 20px 0;">

<h3 class="card-title">Daftar Harga Utama (Rupiah)</h3>
<p class="form-hint" style="margin-top: -8px; margin-bottom: 14px;">
    Kosongkan kolom jika harga tersebut belum digunakan (akan tampil <em>"Belum diisi"</em>). Isi dengan <code>0</code> jika harga memang Rp 0.
</p>

<div class="price-grid price-grid-4">
    <div class="form-group">
        <label for="retail_price" class="form-label">Harga Jual Satuan / Eceran</label>
        <div class="input-prefix-group">
            <span class="input-prefix">Rp</span>
            <input
                type="number"
                step="0.01"
                min="0"
                inputmode="decimal"
                id="retail_price"
                name="retail_price"
                value="{{ old('retail_price', Product::formatInputPrice($product->retail_price)) }}"
                class="form-control"
                placeholder="Kosongkan jika belum ada"
            >
        </div>
    </div>

    <div class="form-group">
        <label for="processed_price" class="form-label">Harga Setelah Diolah / Dimasak / Diseduh</label>
        <div class="input-prefix-group">
            <span class="input-prefix">Rp</span>
            <input
                type="number"
                step="0.01"
                min="0"
                inputmode="decimal"
                id="processed_price"
                name="processed_price"
                value="{{ old('processed_price', Product::formatInputPrice($product->processed_price)) }}"
                class="form-control"
                placeholder="Input manual harga matang/seduh"
            >
        </div>
    </div>

    <div class="form-group">
        <label for="supplier_price" class="form-label">Harga Jual Supplier</label>
        <div class="input-prefix-group">
            <span class="input-prefix">Rp</span>
            <input
                type="number"
                step="0.01"
                min="0"
                inputmode="decimal"
                id="supplier_price"
                name="supplier_price"
                value="{{ old('supplier_price', Product::formatInputPrice($product->supplier_price)) }}"
                class="form-control"
                placeholder="Kosongkan jika belum ada"
            >
        </div>
    </div>

    <div class="form-group">
        <label for="cost_price" class="form-label">Harga Modal</label>
        <div class="input-prefix-group">
            <span class="input-prefix">Rp</span>
            <input
                type="number"
                step="0.01"
                min="0"
                inputmode="decimal"
                id="cost_price"
                name="cost_price"
                value="{{ old('cost_price', Product::formatInputPrice($product->cost_price)) }}"
                class="form-control"
                placeholder="Kosongkan jika belum ada"
            >
        </div>
    </div>
</div>

<hr style="border: none; border-top: 1px solid var(--border-light); margin: 20px 0;">

<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 10px;">
    <div>
        <h3 class="card-title" style="margin-bottom: 2px;">Harga Tambahan (Nama Bebas)</h3>
        <span class="form-hint" style="margin-top: 0;">
            Tambahkan jenis harga lain sesuai kebutuhan (misal: Harga Renceng, Harga Dus, Harga Warung). Maksimal {{ ProductRequest::MAX_ADDITIONAL_PRICES }} baris.
        </span>
    </div>
    <button type="button" id="btn-add-price-row" class="btn btn-outline btn-sm">
        + Tambah Harga Lain
    </button>
</div>

<div id="additional-prices-container">
    @foreach ($additionalRows as $idx => $row)
        <div class="additional-form-row" data-row-index="{{ $idx }}">
            <div>
                <label class="form-label" style="font-size: 0.82rem;">Nama Harga</label>
                <input
                    type="text"
                    name="additional_prices[{{ $idx }}][label]"
                    value="{{ $row['label'] ?? '' }}"
                    class="form-control"
                    placeholder="Contoh: Harga Renceng / Grosir"
                    maxlength="100"
                >
            </div>
            <div>
                <label class="form-label" style="font-size: 0.82rem;">Nilai Harga (Rp)</label>
                <div class="input-prefix-group">
                    <span class="input-prefix">Rp</span>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        inputmode="decimal"
                        name="additional_prices[{{ $idx }}][price]"
                        value="{{ $row['price'] ?? '' }}"
                        class="form-control"
                        placeholder="0"
                    >
                </div>
            </div>
            <div style="padding-top: 18px;">
                <button type="button" class="btn btn-danger-outline btn-sm btn-block btn-remove-price-row">
                    Hapus Baris
                </button>
            </div>
        </div>
    @endforeach
</div>

<hr style="border: none; border-top: 1px solid var(--border-light); margin: 20px 0;">

<div class="form-group">
    <label for="notes" class="form-label">Catatan (Opsional)</label>
    <textarea
        id="notes"
        name="notes"
        class="form-control"
        placeholder="Catatan tambahan (misal: isi 1 dus = 40 bungkus, nama sales, dll.)"
        maxlength="2000"
    >{{ old('notes', $product->notes) }}</textarea>
</div>

<div class="card-actions" style="margin-top: 20px;">
    <button type="submit" class="btn btn-primary">
        {{ $submitLabel ?? 'Simpan Barang' }}
    </button>
    <a href="{{ $cancelUrl ?? route('products.index') }}" class="btn btn-outline">
        Batal
    </a>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const maxRows = {{ ProductRequest::MAX_ADDITIONAL_PRICES }};
    const container = document.getElementById('additional-prices-container');
    const btnAddRow = document.getElementById('btn-add-price-row');
    let nextRowIndex = {{ count($additionalRows) }};

    function bindRemoveButtons() {
        if (!container) return;
        const buttons = container.querySelectorAll('.btn-remove-price-row');
        buttons.forEach(function (btn) {
            btn.onclick = function () {
                const row = btn.closest('.additional-form-row');
                if (row) {
                    row.remove();
                }
            };
        });
    }

    bindRemoveButtons();

    if (btnAddRow && container) {
        btnAddRow.addEventListener('click', function () {
            const currentCount = container.querySelectorAll('.additional-form-row').length;
            if (currentCount >= maxRows) {
                alert('Maksimal ' + maxRows + ' baris harga tambahan per barang.');
                return;
            }

            const idx = nextRowIndex++;
            const rowEl = document.createElement('div');
            rowEl.className = 'additional-form-row';
            rowEl.setAttribute('data-row-index', String(idx));

            const colLabel = document.createElement('div');
            const lbl1 = document.createElement('label');
            lbl1.className = 'form-label';
            lbl1.style.fontSize = '0.82rem';
            lbl1.textContent = 'Nama Harga';
            const inputLabel = document.createElement('input');
            inputLabel.type = 'text';
            inputLabel.name = 'additional_prices[' + idx + '][label]';
            inputLabel.className = 'form-control';
            inputLabel.placeholder = 'Contoh: Harga Renceng / Grosir';
            inputLabel.maxLength = 100;
            colLabel.appendChild(lbl1);
            colLabel.appendChild(inputLabel);

            const colPrice = document.createElement('div');
            const lbl2 = document.createElement('label');
            lbl2.className = 'form-label';
            lbl2.style.fontSize = '0.82rem';
            lbl2.textContent = 'Nilai Harga (Rp)';
            const prefixGroup = document.createElement('div');
            prefixGroup.className = 'input-prefix-group';
            const prefixSpan = document.createElement('span');
            prefixSpan.className = 'input-prefix';
            prefixSpan.textContent = 'Rp';
            const inputPrice = document.createElement('input');
            inputPrice.type = 'number';
            inputPrice.step = '0.01';
            inputPrice.min = '0';
            inputPrice.setAttribute('inputmode', 'decimal');
            inputPrice.name = 'additional_prices[' + idx + '][price]';
            inputPrice.className = 'form-control';
            inputPrice.placeholder = '0';
            prefixGroup.appendChild(prefixSpan);
            prefixGroup.appendChild(inputPrice);
            colPrice.appendChild(lbl2);
            colPrice.appendChild(prefixGroup);

            const colAction = document.createElement('div');
            colAction.style.paddingTop = '18px';
            const btnRemove = document.createElement('button');
            btnRemove.type = 'button';
            btnRemove.className = 'btn btn-danger-outline btn-sm btn-block btn-remove-price-row';
            btnRemove.textContent = 'Hapus Baris';
            colAction.appendChild(btnRemove);

            rowEl.appendChild(colLabel);
            rowEl.appendChild(colPrice);
            rowEl.appendChild(colAction);
            container.appendChild(rowEl);

            bindRemoveButtons();
            inputLabel.focus();
        });
    }

    // Integrasi Scanner Kamera pada Form Input Barcode
    const btnFormScan = document.getElementById('btn-form-scan-barcode');
    const btnFormClose = document.getElementById('btn-form-close-scanner');
    const barcodeInput = document.getElementById('barcode');
    const currentProductId = @json($product->exists ? $product->id : null);

    if (btnFormScan && barcodeInput) {
        btnFormScan.addEventListener('click', function () {
            if (!window.AyepBarcodeScanner) return;
            window.AyepBarcodeScanner.start({
                containerId: 'form-scanner-reader',
                panelId: 'form-scanner-panel',
                statusId: 'form-scanner-status',
                onDetected: function (scannedCode) {
                    barcodeInput.value = scannedCode;
                    // Periksa ke server apakah barcode ini sudah terdaftar pada barang lain
                    fetch(@json(route('products.lookup')) + '?barcode=' + encodeURIComponent(scannedCode), {
                        headers: { 'Accept': 'application/json' }
                    })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.found && data.product && data.product.id !== currentProductId) {
                            window.location.href = data.redirect_url;
                        }
                    })
                    .catch(function () {});
                }
            });
        });
    }

    if (btnFormClose) {
        btnFormClose.addEventListener('click', function () {
            if (window.AyepBarcodeScanner) {
                window.AyepBarcodeScanner.stop('form-scanner-panel');
            }
        });
    }
});
</script>
@endpush
