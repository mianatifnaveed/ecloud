<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\V2\Nat;
use Faker\Generator as Faker;

$factory->define(Nat::class, function (Faker $faker) {
    return [
        'destination_id' => 'fip-123456',
        'translated_id' => 'nic-654321',
        'action' => 'DNAT'
    ];
});
