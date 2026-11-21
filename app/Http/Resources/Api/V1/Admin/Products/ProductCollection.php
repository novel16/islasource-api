<?php

namespace App\Http\Resources\Api\V1\Admin\Products;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ProductCollection extends ResourceCollection
{
    public $collects = ProductResource::class;
}
