<?php
namespace App\Dtos;

use Spatie\DataTransferObject\DataTransferObject;

class BlogData
{
    public function __construct(
        public readonly string $title,
        public readonly string $content,
        public readonly bool $status,
        public readonly array $productIds,
        public readonly ?string $slug = null,
    ) {}

    public static function fromRequest(BlogRequest $request): self
    {
        return new self(
            title: $request->title,
            content: $request->content,
            status: $request->status,
            productIds: $request->product_ids,
            slug: Str::slug($request->title)
        );
    }
}
