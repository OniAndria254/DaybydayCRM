<?php

use Illuminate\Database\Seeder;

class InvoiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        factory(App\Models\Invoice::class, 10)->create()->each(function ($invoice) {
            // Optionally, create related invoice lines or other related data here
        });
    }
}