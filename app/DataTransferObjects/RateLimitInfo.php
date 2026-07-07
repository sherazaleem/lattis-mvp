<?php

namespace App\DataTransferObjects;

class RateLimitInfo
{
    public function __construct(
        public readonly int $requestsPerMinute,
        public readonly int $tokensPerMinute,
    ) {}
}
