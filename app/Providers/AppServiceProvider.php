<?php

namespace App\Providers;

use App\Services\ContentFilters\DuplicateParagraphFilter;
use App\Services\ContentFilters\ForbiddenTopicFilter;
use App\Services\ContentFilters\HealthSatireHoldFilter;
use App\Services\ContentFilters\SourceSimilarityFilter;
use App\Services\ContentFilters\WordCountFilter;
use App\Services\OutputValidator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // HealthSatireHoldFilter MUST run first — OutputValidator::validate()
        // breaks its loop on the first FAIL, and a HOLD must never be
        // short-circuited past by a FAIL that runs before it.
        $this->app->bind(OutputValidator::class, fn () => new OutputValidator([
            new HealthSatireHoldFilter(),
            new WordCountFilter(),
            new DuplicateParagraphFilter(),
            new SourceSimilarityFilter(),
            new ForbiddenTopicFilter(),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Some local PHP/cURL installs (this WAMP box included) ship without
        // curl.cainfo/openssl.cafile configured, so outbound HTTPS fails SSL
        // verification. Pin a bundled CA bundle for every outbound HTTP call
        // (RSS fetching, AI providers, publishing adapters) instead of
        // relying on machine-level php.ini config.
        Http::globalOptions(['verify' => resource_path('certs/cacert.pem')]);
    }
}
