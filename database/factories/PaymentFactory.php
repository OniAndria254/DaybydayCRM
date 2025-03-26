<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\Payment;
use Faker\Generator as Faker;

$factory->define(Payment::class, function (Faker $faker) {
    return [
        'external_id' => $faker->uuid,
        'invoice_id' => factory(\App\Models\Invoice::class),
        'amount' => $faker->numberBetween(1000, 10000),
        'payment_date' => $faker->date,
        'payment_source' => $faker->randomElement(['bank', 'cash']),
        'description' => $faker->sentence,
    ];
});
