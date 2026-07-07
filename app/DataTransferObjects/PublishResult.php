<?php

namespace App\DataTransferObjects;

class PublishResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $externalId = null,   // e.g. WordPress post ID, or file path for FTP
        public readonly ?string $externalUrl = null,
        public readonly ?string $errorType = null,     // auth, network, rate_limit, unknown
        public readonly ?string $errorMessage = null,
    ) {}
}
