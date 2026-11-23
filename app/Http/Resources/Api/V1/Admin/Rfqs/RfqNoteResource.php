<?php

namespace App\Http\Resources\Api\V1\Admin\Rfqs;

use App\Models\RfqNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin RfqNote */
class RfqNoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'body' => $this->body,
            'author_name' => $this->author?->name ?? 'Admin',
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
