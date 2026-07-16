<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminEmail = env('ADMIN_EMAIL', 'admin@abrasilsistemas.com');

        User::query()
            ->where('email', '!=', $adminEmail)
            ->delete();

        User::query()->updateOrCreate([
            'email' => $adminEmail,
        ], [
            'name' => env('ADMIN_NAME', 'Administrador'),
            'password' => Hash::make(env('ADMIN_PASSWORD', 'Admin@123456')),
            'email_verified_at' => now(),
        ]);
    }
}
