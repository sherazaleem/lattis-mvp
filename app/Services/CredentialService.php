<?php

namespace App\Services;

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
     * TODO (Stage 4): resolve the site's PublishingAdapterInterface via the
     * adapter factory and call verifyCredentials($site). Update
     * credentials.credential_status and last_verified_at based on the result.
     * Called by CredentialHealthCheckCommand on a daily schedule, and once
     * before a site's very first publish.
     *
     * Stub returns true until Stage 4 builds the real adapter-backed check —
     * false here would incorrectly halt publishing for every site before that
     * check exists to actually justify it.
     */
    public function verifyCredentials(Site $site): bool
    {
        return true;
    }
}
