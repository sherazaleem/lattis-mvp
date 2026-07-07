<?php

namespace App\Interfaces;

use App\DataTransferObjects\PromptPayload;
use App\DataTransferObjects\GenerationResult;
use App\DataTransferObjects\RateLimitInfo;

/**
 * Every AI provider (OpenAI, Claude, Ollama, ...) implements this.
 * Generation jobs must NEVER call a provider SDK directly — only through
 * an implementation of this interface, selected by a provider factory.
 * Adding a new provider = one new class, zero changes to GenerateArticleJob.
 */
interface AIProviderInterface
{
    public function generate(PromptPayload $prompt): GenerationResult;

    public function supportsModel(string $modelName): bool;

    public function getProviderName(): string;

    public function getRateLimit(): RateLimitInfo;
}
