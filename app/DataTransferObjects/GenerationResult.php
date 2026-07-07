<?php

namespace App\DataTransferObjects;

class GenerationResult
{
    public function __construct(
        public readonly bool $success,
        public readonly ?string $bodyHtml,
        public readonly ?string $modelUsed,
        public readonly ?string $providerName,
        public readonly int $tokensInput = 0,
        public readonly int $tokensOutput = 0,
        public readonly int $generationMs = 0,
        public readonly ?string $errorType = null,   // e.g. rate_limit, content_policy, timeout, unknown
        public readonly ?string $errorMessage = null,
    ) {}
}
