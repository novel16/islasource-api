<?php

namespace App\Http\Resources\Api\V1\Admin\Orders;

use Illuminate\Http\Resources\Json\ResourceCollection;

class OrderCollection extends ResourceCollection
{
    public $collects = OrderResource::class;
}
