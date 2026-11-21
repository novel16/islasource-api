<?php

namespace App\Http\Requests\Api\V1\PublicCatalog;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGuestRfqRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:254'],
            'phone' => ['required', 'string', 'max:25', 'regex:/\A[+0-9() .-]{7,25}\z/'],
            'location' => ['required', 'string', 'max:200'],
            'requested_delivery_date' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'terms_accepted' => ['accepted'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*' => ['required', 'array:product_id,name,quantity,unit,notes'],
            'items.*.product_id' => [
                'present',
                'nullable',
                'integer',
                Rule::exists('products', 'id')->where('status', 'Published')->whereNull('deleted_at'),
            ],
            'items.*.name' => ['required', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:9999999.999'],
            'items.*.unit' => ['required', 'string', Rule::in(Product::UNITS)],
            'items.*.notes' => ['nullable', 'string', 'max:300'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => strtolower(trim($this->input('email')))]);
        }

        foreach (['customer_name', 'phone', 'location', 'notes'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field))]);
            }
        }

        $items = $this->input('items');
        if (is_array($items)) {
            foreach ($items as &$item) {
                if (! is_array($item)) {
                    continue;
                }

                foreach (['name', 'unit', 'notes'] as $field) {
                    if (is_string($item[$field] ?? null)) {
                        $item[$field] = trim($item[$field]);
                    }
                }
            }

            unset($item);
            $this->merge(['items' => $items]);
        }
    }
}
