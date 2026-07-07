<?php

namespace App\DataTransferObjects;

class PostStatus
{
    public function __construct(
        public readonly string $status, // e.g. "live", "draft", "not_found", "unknown"
        public readonly ?string $externalUrl = null,
    ) {}
}
