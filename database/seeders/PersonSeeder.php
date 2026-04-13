<?php

namespace Database\Seeders;

use App\Models\Person;
use Illuminate\Database\Seeder;

class PersonSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            [
                'slug' => 'bostjan',
                'name' => 'Boštjan',
                'sort_order' => 1,
            ],
            [
                'slug' => 'jasna',
                'name' => 'Jasna',
                'sort_order' => 2,
            ],
        ])->each(function (array $person): void {
            Person::query()->updateOrCreate(
                ['slug' => $person['slug']],
                [
                    'name' => $person['name'],
                    'is_active' => true,
                    'sort_order' => $person['sort_order'],
                ],
            );
        });
    }
}