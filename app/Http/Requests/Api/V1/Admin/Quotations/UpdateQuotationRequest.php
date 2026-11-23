<?php

namespace App\Http\Requests\Api\V1\Admin\Quotations;

use App\Models\Quotation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quotation = $this->route('quotation');

        return $quotation instanceof Quotation && ($this->user()?->can('update', $quotation) ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'handling_centavos' => ['required', 'integer', 'min:0', 'max:999999999'],
            'markup_percent' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:1000'],
            'delivery_centavos' => ['required', 'integer', 'min:0', 'max:999999999'],
            'deposit_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'validity_hours' => ['required', 'integer', 'min:1', 'max:720'],
            'terms' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1', 'max:20'],
            'items.*' => ['required', 'array:id,quantity,specs,is_available,unit_cost_centavos'],
            'items.*.id' => ['required', 'integer', 'min:1'],
            'items.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:9999999.999'],
            'items.*.specs' => ['nullable', 'string', 'max:500'],
            'items.*.is_available' => ['required', 'boolean'],
            'items.*.unit_cost_centavos' => ['required', 'integer', 'min:0', 'max:999999999'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('terms'))) {
            $this->merge(['terms' => trim($this->input('terms'))]);
        }

        $items = $this->input('items');

        if (is_array($items)) {
            foreach ($items as &$item) {
                if (is_array($item) && is_string($item['specs'] ?? null)) {
                    $item['specs'] = trim($item['specs']);
                }
            }

            unset($item);
            $this->merge(['items' => $items]);
        }
    }
}
