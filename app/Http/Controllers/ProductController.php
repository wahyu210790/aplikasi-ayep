<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Product;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    /**
     * Halaman utama dashboard pencarian & daftar barang.
     */
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $barcodeParam = trim((string) $request->query('barcode', ''));
        $categoryParam = trim((string) $request->query('category', ''));

        $exactBarcodeMatch = null;
        $notFoundBarcode = null;

        // Jika pencarian dilakukan spesifik melalui scan/parameter barcode
        if ($barcodeParam !== '') {
            $exactBarcodeMatch = Product::with('additionalPrices')
                ->where('barcode', $barcodeParam)
                ->first();

            if (! $exactBarcodeMatch) {
                $notFoundBarcode = $barcodeParam;
            }
        } elseif ($search !== '') {
            // Jika pengguna mengetik sesuatu di kolom pencarian yang persis sama dengan barcode barang
            $exactBySearch = Product::with('additionalPrices')
                ->where('barcode', $search)
                ->first();
            if ($exactBySearch) {
                $exactBarcodeMatch = $exactBySearch;
            }
        }

        $query = Product::with('additionalPrices');

        if ($barcodeParam !== '') {
            $query->where('barcode', $barcodeParam);
        } elseif ($search !== '') {
            $query->search($search);
        }

        if ($categoryParam !== '') {
            $query->where('category', $categoryParam);
        }

        $products = $query
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        // Jika pencarian kata kunci menyerupai barcode (hanya angka/huruf tanpa spasi) dan hasil 0
        if ($notFoundBarcode === null && $search !== '' && $products->total() === 0 && preg_match('/^[A-Za-z0-9\-_.\/]{3,100}$/', $search)) {
            $notFoundBarcode = $search;
        }

        $categories = Product::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $totalAllProducts = Product::count();

        return view('products.index', [
            'products' => $products,
            'search' => $search,
            'barcodeParam' => $barcodeParam,
            'categoryParam' => $categoryParam,
            'categories' => $categories,
            'exactBarcodeMatch' => $exactBarcodeMatch,
            'notFoundBarcode' => $notFoundBarcode,
            'totalAllProducts' => $totalAllProducts,
        ]);
    }

    /**
     * Endpoint JSON untuk pencarian cepat via scanner kamera atau AJAX.
     */
    public function lookup(Request $request): JsonResponse
    {
        $barcode = trim((string) $request->query('barcode', ''));
        $queryText = trim((string) $request->query('q', ''));

        if ($barcode !== '') {
            $product = Product::with('additionalPrices')
                ->where('barcode', $barcode)
                ->first();

            if ($product) {
                return response()->json([
                    'found' => true,
                    'mode' => 'barcode',
                    'barcode' => $barcode,
                    'product' => $this->serializeProduct($product),
                    'redirect_url' => route('products.show', $product),
                ]);
            }

            return response()->json([
                'found' => false,
                'mode' => 'barcode',
                'barcode' => $barcode,
                'message' => 'Barang tidak ditemukan',
                'create_url' => route('products.create', ['barcode' => $barcode]),
            ]);
        }

        if ($queryText !== '') {
            $items = Product::with('additionalPrices')
                ->search($queryText)
                ->orderBy('name')
                ->limit(20)
                ->get();

            return response()->json([
                'found' => $items->isNotEmpty(),
                'mode' => 'search',
                'query' => $queryText,
                'count' => $items->count(),
                'products' => $items->map(fn (Product $p) => $this->serializeProduct($p))->values(),
                'create_url' => route('products.create', preg_match('/^[0-9]{4,100}$/', $queryText) ? ['barcode' => $queryText] : ['name' => $queryText]),
            ]);
        }

        return response()->json([
            'found' => false,
            'message' => 'Masukkan nama barang atau barcode untuk mencari.',
        ], 422);
    }

    /**
     * Form tambah barang baru.
     */
    public function create(Request $request): View|RedirectResponse
    {
        $prefillBarcode = trim((string) $request->query('barcode', ''));
        $prefillName = trim((string) $request->query('name', ''));

        // Jika barcode yang dikirim lewat URL ternyata sudah terdaftar, langsung tampilkan barang yang sudah ada
        if ($prefillBarcode !== '') {
            $existing = Product::where('barcode', $prefillBarcode)->first();
            if ($existing) {
                return redirect()
                    ->route('products.show', $existing)
                    ->with('warning', "Barcode {$prefillBarcode} sudah terdaftar pada barang \"{$existing->name}\". Menampilkan barang yang sudah ada.");
            }
        }

        $categories = $this->getExistingCategories();

        return view('products.create', [
            'product' => new Product([
                'barcode' => $prefillBarcode !== '' ? $prefillBarcode : null,
                'name' => $prefillName !== '' ? $prefillName : '',
            ]),
            'categories' => $categories,
        ]);
    }

    /**
     * Simpan barang baru ke database.
     * Jika barcode sudah terdaftar (termasuk saat request berdekatan/race condition),
     * jangan buat duplikat dan tampilkan barang yang sudah ada.
     */
    public function store(ProductRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $barcode = $validated['barcode'] ?? null;

        if ($barcode !== null && $barcode !== '') {
            $existing = Product::where('barcode', $barcode)->first();
            if ($existing) {
                return redirect()
                    ->route('products.show', $existing)
                    ->with('warning', "Barcode {$barcode} sudah terdaftar pada barang \"{$existing->name}\". Data duplikat tidak dibuat.");
            }
        }

        try {
            $product = DB::transaction(function () use ($validated) {
                $product = Product::create([
                    'name' => $validated['name'],
                    'barcode' => $validated['barcode'] ?? null,
                    'category' => $validated['category'] ?? null,
                    'cost_price' => $validated['cost_price'] ?? null,
                    'supplier_price' => $validated['supplier_price'] ?? null,
                    'retail_price' => $validated['retail_price'] ?? null,
                    'processed_price' => $validated['processed_price'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ]);

                $this->syncAdditionalPrices($product, $validated['additional_prices'] ?? []);

                return $product;
            });
        } catch (QueryException $e) {
            // Tangani race condition pada unique constraint barcode di level database
            if ($barcode !== null && $this->isDuplicateKeyException($e)) {
                $existing = Product::where('barcode', $barcode)->first();
                if ($existing) {
                    return redirect()
                        ->route('products.show', $existing)
                        ->with('warning', "Barcode {$barcode} sudah terdaftar pada barang \"{$existing->name}\". Data duplikat tidak dibuat.");
                }
            }

            throw $e;
        }

        return redirect()
            ->route('products.show', $product)
            ->with('success', "Barang \"{$product->name}\" berhasil disimpan.");
    }

    /**
     * Tampilkan detail lengkap suatu barang dan seluruh harganya.
     */
    public function show(Product $product): View
    {
        $product->load('additionalPrices');

        return view('products.show', [
            'product' => $product,
        ]);
    }

    /**
     * Form edit barang.
     */
    public function edit(Product $product): View
    {
        $product->load('additionalPrices');
        $categories = $this->getExistingCategories();

        return view('products.edit', [
            'product' => $product,
            'categories' => $categories,
        ]);
    }

    /**
     * Perbarui data barang dan daftar harga tambahannya.
     */
    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $validated = $request->validated();
        $barcode = $validated['barcode'] ?? null;

        try {
            DB::transaction(function () use ($product, $validated) {
                $product->update([
                    'name' => $validated['name'],
                    'barcode' => $validated['barcode'] ?? null,
                    'category' => $validated['category'] ?? null,
                    'cost_price' => $validated['cost_price'] ?? null,
                    'supplier_price' => $validated['supplier_price'] ?? null,
                    'retail_price' => $validated['retail_price'] ?? null,
                    'processed_price' => $validated['processed_price'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                ]);

                $this->syncAdditionalPrices($product, $validated['additional_prices'] ?? []);
            });
        } catch (QueryException $e) {
            if ($barcode !== null && $this->isDuplicateKeyException($e)) {
                return back()
                    ->withInput()
                    ->withErrors(['barcode' => 'Barcode ini sudah terdaftar pada barang lain.']);
            }

            throw $e;
        }

        return redirect()
            ->route('products.show', $product)
            ->with('success', "Perubahan pada barang \"{$product->name}\" berhasil disimpan.");
    }

    /**
     * Hapus barang beserta seluruh harga tambahannya.
     */
    public function destroy(Product $product): RedirectResponse
    {
        $name = $product->name;
        $product->delete();

        return redirect()
            ->route('products.index')
            ->with('success', "Barang \"{$name}\" berhasil dihapus.");
    }

    /**
     * Sinkronisasi baris harga tambahan untuk sebuah barang.
     */
    private function syncAdditionalPrices(Product $product, array $rows): void
    {
        $product->additionalPrices()->delete();

        $order = 1;
        foreach ($rows as $row) {
            $product->additionalPrices()->create([
                'label' => $row['label'],
                'price' => $row['price'],
                'sort_order' => $order++,
            ]);
        }
    }

    /**
     * Ambil daftar kategori unik yang sudah ada.
     */
    private function getExistingCategories()
    {
        return Product::query()
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
    }

    /**
     * Format data barang untuk respons JSON.
     */
    private function serializeProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'barcode' => $product->barcode,
            'category' => $product->category,
            'cost_price' => $product->cost_price,
            'cost_price_formatted' => Product::formatRupiah($product->cost_price),
            'supplier_price' => $product->supplier_price,
            'supplier_price_formatted' => Product::formatRupiah($product->supplier_price),
            'retail_price' => $product->retail_price,
            'retail_price_formatted' => Product::formatRupiah($product->retail_price),
            'processed_price' => $product->processed_price,
            'processed_price_formatted' => Product::formatRupiah($product->processed_price),
            'notes' => $product->notes,
            'additional_prices' => $product->additionalPrices->map(fn ($ap) => [
                'id' => $ap->id,
                'label' => $ap->label,
                'price' => $ap->price,
                'price_formatted' => $ap->formattedPrice(),
            ])->values()->all(),
            'show_url' => route('products.show', $product),
            'edit_url' => route('products.edit', $product),
        ];
    }

    /**
     * Deteksi error constraint unik MySQL / SQLite.
     */
    private function isDuplicateKeyException(QueryException $e): bool
    {
        $sqlState = $e->errorInfo[0] ?? '';
        $driverCode = $e->errorInfo[1] ?? 0;

        return $sqlState === '23000' || $driverCode === 1062 || $driverCode === 19 || str_contains(strtolower($e->getMessage()), 'unique');
    }
}
