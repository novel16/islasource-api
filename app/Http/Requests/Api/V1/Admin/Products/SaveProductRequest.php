<?php

namespace App\Http\Requests\Api\V1\Admin\Products;

use App\Models\Product;
use App\Rules\ProductImagePath;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $product instanceof Product
            ? ($this->user()?->can('update', $product) ?? false)
            : ($this->user()?->can('create', Product::class) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $product = $this->route('product');
        $required = $product instanceof Product ? ['sometimes', 'required'] : ['required'];
        $uniqueSlug = Rule::unique('product_slugs', 'slug');

        if ($product instanceof Product) {
            $uniqueSlug->where(fn (Builder $query): Builder => $query->where('product_id', '!=', $product->id));
        }

        return [
            'name' => [...$required, 'string', 'max:100'],
            'category' => [...$required, 'string', Rule::in(Product::CATEGORIES)],
            'unit' => [...$required, 'string', Rule::in(Product::UNITS)],
            'status' => [...$required, 'string', Rule::in(Product::STATUSES)],
            'description' => [...$required, 'string', 'max:20000'],
            'specs' => ['nullable', 'string', 'max:5000'],
            'slug' => [...$required, 'bail', 'string', 'max:160', 'regex:/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/', $uniqueSlug],
            'meta_title' => [...$required, 'string', 'max:70'],
            'meta_description' => [...$required, 'string', 'max:160'],
            'og_title' => ['nullable', 'string', 'max:100'],
            'og_description' => ['nullable', 'string', 'max:200'],
            'image' => ['nullable', 'string', 'max:255', new ProductImagePath],
            'image_alt' => ['nullable', 'string', 'max:255'],
            'gallery_images' => ['sometimes', 'array', 'max:'.Product::MAX_GALLERY_IMAGES],
            'gallery_images.*' => ['required', 'array:image,image_alt'],
            'gallery_images.*.image' => ['required', 'string', 'max:255', new ProductImagePath],
            'gallery_images.*.image_alt' => ['nullable', 'string', 'max:255'],
            'og_image' => ['nullable', 'string', 'max:255', new ProductImagePath],
            'canonical_url' => ['nullable', 'string', 'url:http,https', 'max:2048'],
            'indexable' => ['sometimes', 'required', 'boolean'],
            'min_order_quantity' => ['sometimes', 'required', 'numeric', 'decimal:0,3', 'gt:0', 'max:9999999.999'],
            'availability_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('slug'))) {
            $this->merge(['slug' => Str::lower(trim($this->input('slug')))]);
        }
    }
}
