<?php

namespace App\Dtos;

use Spatie\DataTransferObject\DataTransferObject;
use App\Enums\OrderStatus;

class OrderStatusData extends DataTransferObject
{
    public OrderStatus $status;
    public ?string $comment;

    public static function fromRequest($request): self
    {
        return new self([
            'status' => OrderStatus::from($request->status),
            'comment' => $request->comment
        ]);
    }
}
