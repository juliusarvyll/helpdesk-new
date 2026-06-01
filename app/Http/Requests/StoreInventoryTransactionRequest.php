<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'inventory_item_id' => ['required', 'exists:inventory_items,id'],
            'ticket_id' => ['nullable', 'exists:tickets,id'],
            'assigned_to_user_id' => ['nullable', 'exists:users,id'],
            'type' => ['required', Rule::in(['created', 'assigned', 'returned', 'consumed', 'transferred', 'repaired', 'retired', 'adjusted'])],
            'quantity' => ['required', 'integer', 'min:1'],
            'from_status' => ['nullable', 'string', 'max:255'],
            'to_status' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
