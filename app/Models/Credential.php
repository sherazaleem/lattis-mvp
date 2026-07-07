<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class Credential extends Model
{
    use HasFactory;

    protected $fillable = [
        'site_id', 'adapter_type', 'host', 'port', 'username', 'secret',
        'credential_status', 'last_verified_at',
    ];

    protected $hidden = ['username', 'secret'];

    protected $casts = [
        'last_verified_at' => 'datetime',
    ];

    // Always encrypted at rest — never read/write these columns directly elsewhere.
    // Use CredentialService, not these accessors, from job/service code.
    public function setUsernameAttribute(string $value): void
    {
        $this->attributes['username'] = Crypt::encryptString($value);
    }

    public function getUsernameAttribute(string $value): string
    {
        return Crypt::decryptString($value);
    }

    public function setSecretAttribute(string $value): void
    {
        $this->attributes['secret'] = Crypt::encryptString($value);
    }

    public function getSecretAttribute(string $value): string
    {
        return Crypt::decryptString($value);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
