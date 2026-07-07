<?php

namespace App\Services\Publishing;

use App\Interfaces\PublishingAdapterInterface;
use InvalidArgumentException;

/**
 * Resolves the configured PublishingAdapterInterface implementation, keyed
 * on Site::$stack_type. Jobs must always go through this factory, never
 * instantiate an adapter directly — see .cursorrules rule 2. Adding a
 * second target (e.g. WordPress) = one new class + one new match arm.
 */
class PublishingAdapterFactory
{
    public function make(string $stackType): PublishingAdapterInterface
    {
        return match ($stackType) {
            'ftp_html' => app(FtpHtmlAdapter::class),
            default => throw new InvalidArgumentException("Unknown stack type: [{$stackType}]."),
        };
    }
}
