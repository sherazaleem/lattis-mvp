<?php

namespace App\DataTransferObjects;

/**
 * Result of a single ContentFilterInterface check.
 * $outcome is one of: 'pass', 'fail', 'hold', 'skip' — see
 * docs/MVP_ROADMAP.md Stage 3 for what each filter should return.
 * A 'hold' outcome is absolute and must force human review regardless of
 * the site's auto_publish setting — never let this be bypassed.
 */
class FilterResult
{
    public function __construct(
        public readonly string $outcome,   // pass | fail | hold | skip
        public readonly string $filterName,
        public readonly ?string $reason = null,
    ) {}

    public function passed(): bool
    {
        return $this->outcome === 'pass';
    }

    public function isHold(): bool
    {
        return $this->outcome === 'hold';
    }

    public function isFail(): bool
    {
        return $this->outcome === 'fail';
    }
}
