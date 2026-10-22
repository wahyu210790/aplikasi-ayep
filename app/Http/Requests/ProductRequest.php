<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public const MAX_ADDITIONAL_PRICES = 20;

    public const MAX_PRICE_VALUE = 9999999999999.99;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $barcode = $this->input('barcode');
        if (is_string($barcode)) {
            $barcode = trim($barcode);
        }
        $normalizedBarcode = ($barcode === '' || $barcode === null) ? null : (string) $barcode;

        $category = $this->input('category');
        if (is_string($category)) {
            $category = trim($category);
        }
        $normalizedCategory = ($category === '' || $category === null) ? null : (string) $category;

        $notes = $this->input('notes');
        if (is_string($notes)) {
            $notes = trim($notes);
        }
        $normalizedNotes = ($notes === '' || $notes === null) ? null : (string) $notes;

        $rawAdditional = $this->input('additional_prices', []);
        $cleanedAdditional = [];
        if (is_array($rawAdditional)) {
            foreach ($rawAdditional as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $label = isset($row['label']) && is_string($row['label']) ? trim($row['label']) : '';
                $price = isset($row['price']) ? $this->normalizePriceValue($row['price']) : null;

                // Abaikan baris yang benar-benar kosong (baik label maupun harga kosong)
                if ($label === '' && ($price === null || $price === '')) {
                    continue;
                }

                $cleanedAdditional[] = [
                    'label' => $label,
                    'price' => $price,
                ];
            }
        }

        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'barcode' => $normalizedBarcode,
            'category' => $normalizedCategory,
            'cost_price' => $this->normalizePriceValue($this->input('cost_price')),
            'supplier_price' => $this->normalizePriceValue($this->input('supplier_price')),
            'retail_price' => $this->normalizePriceValue($this->input('retail_price')),
            'processed_price' => $this->normalizePriceValue($this->input('processed_price')),
            'notes' => $normalizedNotes,
            'additional_prices' => $cleanedAdditional,
        ]);
    }

    private function normalizePriceValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }

            return $trimmed;
        }

        return $value;
    }

    public function rules(): array
    {
        $product = $this->route('product');
        $productId = is_object($product) ? $product->id : $product;

        $barcodeRules = [
            'nullable',
            'string',
            'max:100',
            'regex:/^[A-Za-z0-9\-_.\/]+$/',
        ];

        // Pada update, periksa keunikan di form validation dengan mengabaikan ID barang saat ini.
        // Pada store, controller menangani pengecekan duplikat untuk menampilkan barang yang sudah ada,
        // namun tetap kita sertakan aturan unique jika berada di mode update.
        if ($productId !== null) {
            $barcodeRules[] = Rule::unique('products', 'barcode')->ignore($productId);
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'barcode' => $barcodeRules,
            'category' => ['nullable', 'string', 'max:100'],
            'cost_price' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_PRICE_VALUE],
            'supplier_price' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_PRICE_VALUE],
            'retail_price' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_PRICE_VALUE],
            'processed_price' => ['nullable', 'numeric', 'min:0', 'max:'.self::MAX_PRICE_VALUE],
            'notes' => ['nullable', 'string', 'max:2000'],
            'additional_prices' => ['nullable', 'array', 'max:'.self::MAX_ADDITIONAL_PRICES],
            'additional_prices.*.label' => ['required', 'string', 'max:100'],
            'additional_prices.*.price' => ['required', 'numeric', 'min:0', 'max:'.self::MAX_PRICE_VALUE],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama barang wajib diisi.',
            'name.max' => 'Nama barang maksimal 255 karakter.',
            'barcode.max' => 'Barcode maksimal 100 karakter.',
            'barcode.regex' => 'Format barcode hanya boleh berisi huruf, angka, tanda hubung (-), titik (.), garis miring (/), atau garis bawah (_).',
            'barcode.unique' => 'Barcode ini sudah terdaftar pada barang lain.',
            'category.max' => 'Kategori maksimal 100 karakter.',
            'cost_price.numeric' => 'Harga modal harus berupa angka yang valid.',
            'cost_price.min' => 'Harga modal tidak boleh bernilai negatif.',
            'cost_price.max' => 'Harga modal melebihi batas maksimum.',
            'supplier_price.numeric' => 'Harga jual supplier harus berupa angka yang valid.',
            'supplier_price.min' => 'Harga jual supplier tidak boleh bernilai negatif.',
            'supplier_price.max' => 'Harga jual supplier melebihi batas maksimum.',
            'retail_price.numeric' => 'Harga jual eceran/satuan harus berupa angka yang valid.',
            'retail_price.min' => 'Harga jual eceran/satuan tidak boleh bernilai negatif.',
            'retail_price.max' => 'Harga jual eceran/satuan melebihi batas maksimum.',
            'processed_price.numeric' => 'Harga setelah diolah/dimasak/diseduh harus berupa angka yang valid.',
            'processed_price.min' => 'Harga setelah diolah/dimasak/diseduh tidak boleh bernilai negatif.',
            'processed_price.max' => 'Harga setelah diolah/dimasak/diseduh melebihi batas maksimum.',
            'notes.max' => 'Catatan maksimal 2000 karakter.',
            'additional_prices.array' => 'Format daftar harga tambahan tidak valid.',
            'additional_prices.max' => 'Jumlah harga tambahan maksimal '.self::MAX_ADDITIONAL_PRICES.' baris per barang.',
            'additional_prices.*.label.required' => 'Nama harga tambahan wajib diisi jika nilai harga diisi.',
            'additional_prices.*.label.max' => 'Nama harga tambahan maksimal 100 karakter.',
            'additional_prices.*.price.required' => 'Nilai harga tambahan wajib diisi jika nama harga diisi.',
            'additional_prices.*.price.numeric' => 'Nilai harga tambahan harus berupa angka yang valid.',
            'additional_prices.*.price.min' => 'Nilai harga tambahan tidak boleh bernilai negatif.',
            'additional_prices.*.price.max' => 'Nilai harga tambahan melebihi batas maksimum.',
        ];
    }
}
