<?php

namespace App\Services;

use App\Models\Credential;
use App\Models\Site;
use Illuminate\Support\Facades\Crypt;

/**
 * The only place credentials are ever encrypted/decrypted/verified.
 * Build in Roadmap Stage 1. See .cursorrules rule 4 — never store or log
 * credentials in plaintext anywhere else in the codebase.
 */
class CredentialService
{
    public function encrypt(string $plain): string
    {
        return Crypt::encryptString($plain);
    }

    public function decrypt(string $cipher): string
    {
        return Crypt::decryptString($cipher);
    }

    /**
     * TODO (Stage 1/6): resolve the site's PublishingAdapterInterface via the
     * adapter factory and call verifyCredentials($site). Update
     * credentials.credential_status and last_verified_at based on the result.
     * Called by CredentialHealthCheckCommand on a daily schedule, and once
     * before a site's very first publish.
     */
    public function verifyCredentials(Site $site): bool
    {
        // TODO: implement per docstring above.
        return false;
    }
}
