<?php

namespace App\Services\Admin;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class ProductImageService
{
    public function upload(UploadedFile $image): string
    {
        $path = $image->store('products', 'public');

        if ($path === false) {
            throw new RuntimeException('The product image could not be stored.');
        }

        return '/storage/'.$path;
    }
}
