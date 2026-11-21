<?php

namespace App\Http\Controllers\Api\V1\Admin\Products;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Products\IndexProductRequest;
use App\Http\Requests\Api\V1\Admin\Products\SaveProductRequest;
use App\Http\Resources\Api\V1\Admin\Products\ProductCollection;
use App\Http\Resources\Api\V1\Admin\Products\ProductResource;
use App\Models\Product;
use App\Services\Admin\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $products) {}

    public function index(IndexProductRequest $request): ProductCollection
    {
        return new ProductCollection($this->products->paginate($request->validated()));
    }

    public function store(SaveProductRequest $request): JsonResponse
    {
        return (new ProductResource($this->products->create($request->validated())))
            ->response()->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        Gate::authorize('view', $product);

        return new ProductResource($product->load('slugs'));
    }

    public function update(SaveProductRequest $request, Product $product): ProductResource
    {
        return new ProductResource($this->products->update($product, $request->validated()));
    }

    public function destroy(Product $product): Response
    {
        Gate::authorize('delete', $product);
        $this->products->archive($product);

        return response()->noContent();
    }

    public function restore(Product $archivedProduct): ProductResource
    {
        Gate::authorize('restore', $archivedProduct);

        return new ProductResource($this->products->restore($archivedProduct));
    }
}
