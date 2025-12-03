<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // Seed a single product for the flash sale
        \App\Models\Product::updateOrCreate(
            ['name' => 'Flash Sale Product'],
            ['price' => 49.99, 'stock' => 100]
        );
    }
}
