<?php

namespace App\Http\Controllers\Api\V1\PublicCatalog;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\PublicCatalog\IndexPublicProductRequest;
use App\Http\Resources\Api\V1\PublicCatalog\PublicProductCollection;
use App\Http\Resources\Api\V1\PublicCatalog\PublicProductResource;
use App\Services\PublicCatalogService;

class PublicProductController extends Controller
{
    public function __construct(private readonly PublicCatalogService $catalog) {}

    public function index(IndexPublicProductRequest $request): PublicProductCollection
    {
        return new PublicProductCollection($this->catalog->paginatePublished($request->validated()));
    }

    public function show(string $slug): PublicProductResource
    {
        return new PublicProductResource($this->catalog->findPublishedBySlug($slug));
    }
}
