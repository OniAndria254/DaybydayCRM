<?php

use App\Models\Offer;
use Illuminate\Database\Seeder;

class DummyDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $this->call('UsersDummyTableSeeder');
        $this->call('ClientsDummyTableSeeder');
        $this->call('TasksDummyTableSeeder');
        $this->call('LeadsDummyTableSeeder');
        $this->call(OfferSeeder::class); // Register the InvoiceSeeder
        $this->call(InvoiceSeeder::class); // Register the InvoiceSeeder
        $this->call(PaymentSeeder::class); 
    }
}
