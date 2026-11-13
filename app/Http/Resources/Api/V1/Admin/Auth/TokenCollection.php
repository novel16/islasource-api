<?php

namespace App\Http\Resources\Api\V1\Admin\Auth;

use Illuminate\Http\Resources\Json\ResourceCollection;

class TokenCollection extends ResourceCollection
{
    public $collects = TokenResource::class;
}
