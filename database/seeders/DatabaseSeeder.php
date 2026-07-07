<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seeds the single admin account for this MVP (no roles, no registration —
     * see .cursorrules: "skip the four-role system for now"). Credentials come
     * from .env so nothing is hardcoded; re-running this is safe (idempotent).
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => env('ADMIN_NAME', 'Admin'),
                'password' => env('ADMIN_PASSWORD', 'password'),
            ]
        );
    }
}
