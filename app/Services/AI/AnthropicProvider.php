<?php

namespace App\Services\AI;

use App\DataTransferObjects\GenerationResult;
use App\DataTransferObjects\PromptPayload;
use App\DataTransferObjects\RateLimitInfo;
use App\Interfaces\AIProviderInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Anthropic (Claude) implementation of AIProviderInterface. Never called
 * directly by jobs — always resolved through AIProviderFactory.
 */
class AnthropicProvider implements AIProviderInterface
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';
    private const ANTHROPIC_VERSION = '2023-06-01';
    private const MAX_TOKENS = 4096;

    public function generate(PromptPayload $prompt): GenerationResult
    {
        $model = config('services.anthropic.model');
        $startedAt = microtime(true);

        try {
            $response = Http::timeout(120)
                ->withHeaders([
                    'x-api-key' => config('services.anthropic.key'),
                    'anthropic-version' => self::ANTHROPIC_VERSION,
                ])
                ->post(self::API_URL, [
                    'model' => $model,
                    'max_tokens' => self::MAX_TOKENS,
                    'system' => $prompt->systemPrompt,
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt->userPrompt],
                    ],
                ]);
        } catch (ConnectionException $e) {
            return new GenerationResult(
                success: false,
                bodyHtml: null,
                modelUsed: $model,
                providerName: $this->getProviderName(),
                generationMs: $this->elapsedMs($startedAt),
                errorType: 'timeout',
                errorMessage: $e->getMessage(),
            );
        }

        if ($response->failed()) {
            return new GenerationResult(
                success: false,
                bodyHtml: null,
                modelUsed: $model,
                providerName: $this->getProviderName(),
                generationMs: $this->elapsedMs($startedAt),
                errorType: match ($response->status()) {
                    429 => 'rate_limit',
                    401, 403 => 'auth',
                    default => 'unknown',
                },
                errorMessage: data_get($response->json(), 'error.message', $response->body()),
            );
        }

        $json = $response->json();
        $bodyHtml = collect(data_get($json, 'content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode("\n");

        if (trim($bodyHtml) === '') {
            return new GenerationResult(
                success: false,
                bodyHtml: null,
                modelUsed: $model,
                providerName: $this->getProviderName(),
                generationMs: $this->elapsedMs($startedAt),
                errorType: 'unknown',
                errorMessage: 'Provider returned an empty response body.',
            );
        }

        return new GenerationResult(
            success: true,
            bodyHtml: $bodyHtml,
            modelUsed: data_get($json, 'model', $model),
            providerName: $this->getProviderName(),
            tokensInput: (int) data_get($json, 'usage.input_tokens', 0),
            tokensOutput: (int) data_get($json, 'usage.output_tokens', 0),
            generationMs: $this->elapsedMs($startedAt),
        );
    }

    public function supportsModel(string $modelName): bool
    {
        return str_starts_with($modelName, 'claude-');
    }

    public function getProviderName(): string
    {
        return 'anthropic';
    }

    public function getRateLimit(): RateLimitInfo
    {
        // Static default for Anthropic's tier-1 usage — not read from live
        // response headers. Raise this (or read it from response headers)
        // if usage tier changes and this MVP outgrows tier 1.
        return new RateLimitInfo(
            requestsPerMinute: 50,
            tokensPerMinute: 40000,
        );
    }

    private function elapsedMs(float $startedAt): int
    {
        return (int) round((microtime(true) - $startedAt) * 1000);
    }
}
