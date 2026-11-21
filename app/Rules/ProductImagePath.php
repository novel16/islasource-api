<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Translation\PotentiallyTranslatedString;

class ProductImagePath implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (in_array($value, ['/images/squid.jpg', '/images/crab.jpg', '/images/scallops.jpg', '/images/dried-fish.jpg'], true)) {
            return;
        }

        if (is_string($value) && preg_match('#\A/storage/products/[A-Za-z0-9]{40}\.(?:jpg|jpeg|png|webp)\z#', $value)) {
            $path = substr($value, strlen('/storage/'));
            $disk = Storage::disk('public');

            if ($disk->exists($path)) {
                $image = @getimagesize($disk->path($path));

                if ($image !== false && in_array($image['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
                    return;
                }
            }
        }

        $fail('The :attribute must be a supplied product image or an existing uploaded raster image.');
    }
}
