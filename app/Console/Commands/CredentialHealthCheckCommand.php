<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Models\SystemLog;
use App\Services\CredentialService;
use Illuminate\Console\Command;

/**
 * Runs daily. Calls CredentialService::verifyCredentials() for every active
 * site — that call itself updates credentials.credential_status, which is
 * what actually halts (or un-halts) publishing for the site: see
 * PublishingSchedulerCommand::credentialHalted() and PublishArticleJob's
 * immediate-halt-on-auth-failure path.
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
            $valid = $this->credentials->verifyCredentials($site);

            SystemLog::create([
                'job_type' => self::class,
                'entity_type' => Site::class,
                'entity_id' => $site->id,
                'status' => $valid ? 'success' : 'failed',
                'message' => $valid
                    ? "Credentials verified OK for site [{$site->id}] {$site->domain}."
                    : "Credential verification FAILED for site [{$site->id}] {$site->domain} — publishing halted.",
            ]);

            $this->info("{$site->domain}: ".($valid ? 'OK' : 'FAILED (halted)'));
        });
    }
}
