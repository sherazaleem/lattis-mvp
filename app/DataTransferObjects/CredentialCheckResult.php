<?php

namespace App\DataTransferObjects;

class CredentialCheckResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly ?string $errorMessage = null,
        public readonly ?\DateTimeInterface $checkedAt = null,
    ) {}
}
