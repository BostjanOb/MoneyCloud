<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Bostjan Oblak',
            'email' => 'bostjan@muha.cc',
        ]);

        $this->call(TaxSettingSeeder::class);
    }
}
