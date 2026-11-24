<?php

namespace App\Http\Controllers\Api\V1\Admin\Orders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\Orders\IndexOrderRequest;
use App\Http\Requests\Api\V1\Admin\Orders\UpdateOrderRequest;
use App\Http\Resources\Api\V1\Admin\Orders\OrderCollection;
use App\Http\Resources\Api\V1\Admin\Orders\OrderResource;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(IndexOrderRequest $request): OrderCollection
    {
        return new OrderCollection($this->orders->paginate($request->validated()));
    }

    public function show(Request $request, Order $order): OrderResource
    {
        abort_unless($request->user()?->can('view', $order) ?? false, 403);

        return new OrderResource($this->orders->findForAdmin($order));
    }

    public function update(UpdateOrderRequest $request, Order $order): OrderResource
    {
        $validated = $request->validated();

        return new OrderResource($this->orders->updateStatus(
            $order,
            $validated['status'],
            $validated['cancel_reason'] ?? null,
        ));
    }
}
