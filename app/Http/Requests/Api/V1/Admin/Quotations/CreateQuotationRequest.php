<?php

namespace App\Http\Requests\Api\V1\Admin\Quotations;

use App\Models\Quotation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Quotation::class) ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'validity_hours' => ['sometimes', 'integer', 'min:1', 'max:720'],
            'deposit_percent' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'terms' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ];
    }
}
