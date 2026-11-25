<?php

namespace App\Http\Requests\Api\V1\Admin\Rfqs;

use App\Models\Rfq;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendRfqSupplyNoticeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rfq = $this->route('rfq');

        return $rfq instanceof Rfq && ($this->user()?->can('update', $rfq) ?? false);
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'string', Rule::in(['reschedule', 'unavailable'])],
            'proposed_available_date' => ['required_if:mode,reschedule', 'nullable', 'date_format:Y-m-d'],
            'proposed_delivery_date' => [
                'required_if:mode,reschedule',
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:proposed_available_date',
            ],
            'notice' => ['required', 'string', 'max:2000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('notice'))) {
            $this->merge(['notice' => trim($this->input('notice'))]);
        }
    }
}
