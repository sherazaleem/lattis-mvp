<?php

namespace App\Services;

use App\Models\Site;
use App\Services\Publishing\PublishingAdapterFactory;
use Illuminate\Support\Facades\Crypt;

/**
 * The only place credentials are ever encrypted/decrypted/verified.
 * See .cursorrules rule 4 — never store or log credentials in plaintext
 * anywhere else in the codebase.
 */
class CredentialService
{
    public function __construct(
        protected PublishingAdapterFactory $adapterFactory,
    ) {}

    public function encrypt(string $plain): string
    {
        return Crypt::encryptString($plain);
    }

    public function decrypt(string $cipher): string
    {
        return Crypt::decryptString($cipher);
    }

    /**
     * Resolves the site's PublishingAdapterInterface via the factory and
     * calls verifyCredentials($site). Updates credentials.credential_status
     * and last_verified_at based on the result. Called by
     * CredentialHealthCheckCommand daily, and before a site's first publish.
     */
    public function verifyCredentials(Site $site): bool
    {
        $credential = $site->credentials()->where('adapter_type', $site->stack_type)->first();

        if (!$credential) {
            return false;
        }

        $adapter = $this->adapterFactory->make($site->stack_type);
        $result = $adapter->verifyCredentials($site);

        $credential->update([
            'credential_status' => $result->valid ? 'active' : 'failed',
            'last_verified_at' => $result->checkedAt ?? now(),
        ]);

        return $result->valid;
    }
}
