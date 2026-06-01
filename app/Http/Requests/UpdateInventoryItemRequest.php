<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inventory_category_id' => ['sometimes', 'exists:inventory_categories,id'],
            'asset_tag' => ['nullable', 'string', 'max:255', Rule::unique('inventory_items')->ignore($this->route('inventory_item'))],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'unit' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
            'purchased_at' => ['nullable', 'date'],
            'warranty_expires_at' => ['nullable', 'date', 'after:purchased_at'],
            'serial_numbers' => ['nullable', 'array'],
            'serial_numbers.*.serial_number' => ['required', 'string', 'max:255'],
        ];
    }
}
