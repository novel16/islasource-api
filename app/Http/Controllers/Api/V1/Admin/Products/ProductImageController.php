<?php

namespace App\Http\Controllers\Api\V1\Admin\Products;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Products\UploadProductImageRequest;
use App\Services\Admin\ProductImageService;
use Illuminate\Http\JsonResponse;

class ProductImageController extends Controller
{
    public function store(UploadProductImageRequest $request, ProductImageService $images): JsonResponse
    {
        return response()->json(['data' => ['image' => $images->upload($request->file('image'))]], 201);
    }
}
