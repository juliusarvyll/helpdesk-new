<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inventory_category_id' => ['required', 'exists:inventory_categories,id'],
            'asset_tag' => ['nullable', 'string', 'max:255', 'unique:inventory_items,asset_tag'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['available', 'assigned', 'in_repair', 'retired', 'lost', 'disposed'])],
            'quantity' => ['required', 'integer', 'min:0'],
            'unit' => ['nullable', 'string', 'max:255'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'assigned_to_user_id' => ['nullable', 'exists:users,id'],
            'department_id' => ['nullable', 'exists:department,id'],
            'metadata' => ['nullable', 'array'],
            'purchased_at' => ['nullable', 'date'],
            'warranty_expires_at' => ['nullable', 'date', 'after:purchased_at'],
            'serial_numbers' => ['nullable', 'array'],
            'serial_numbers.*.serial_number' => ['required', 'string', 'max:255', 'unique:inventory_item_serial_numbers,serial_number'],
        ];
    }
}
