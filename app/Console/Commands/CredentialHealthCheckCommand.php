<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\CredentialService;
use Illuminate\Console\Command;

/**
 * Runs daily. Build in Roadmap Stage 4.
 * Calls CredentialService::verifyCredentials() for every active site,
 * updates deployment_state, halts publishing on failure (see .cursorrules
 * rule 4 and the "publishing failures are silent" reasoning in the open
 * questions doc — this is the single most important alert to get right).
 */
class CredentialHealthCheckCommand extends Command
{
    protected $signature = 'atlas:check-credentials';
    protected $description = 'Verify publishing credentials for every active site; halt publishing on failure.';

    public function __construct(protected CredentialService $credentials)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        Site::query()->where('is_active', true)->each(function (Site $site) {
            // TODO: $ok = $this->credentials->verifyCredentials($site);
            // update site.deployment_state + credentials.credential_status,
            // and if failed, set is_active/publishing halt + log + alert.
        });
    }
}
