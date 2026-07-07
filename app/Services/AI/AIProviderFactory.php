<?php

namespace App\Services\AI;

use App\Interfaces\AIProviderInterface;
use InvalidArgumentException;

/**
 * Resolves the configured AIProviderInterface implementation. Jobs and
 * services must always go through this factory, never instantiate a
 * provider class directly — see .cursorrules rule 2. Adding a second
 * provider = one new class + one new match arm here.
 */
class AIProviderFactory
{
    public function make(?string $provider = null): AIProviderInterface
    {
        $provider ??= config('services.ai.provider');

        return match ($provider) {
            'anthropic' => app(AnthropicProvider::class),
            default => throw new InvalidArgumentException("Unknown AI provider: [{$provider}]."),
        };
    }
}
