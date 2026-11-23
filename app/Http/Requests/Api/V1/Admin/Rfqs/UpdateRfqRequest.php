<?php

namespace App\Http\Requests\Api\V1\Admin\Rfqs;

use App\Models\Rfq;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRfqRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $rfq = $this->route('rfq');

        return $rfq instanceof Rfq && ($this->user()?->can('update', $rfq) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(Rfq::STATUSES)],
        ];
    }
}
