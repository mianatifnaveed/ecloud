<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\V2\Nic;
use Faker\Generator as Faker;

$factory->define(Nic::class, function (Faker $faker) {
    return [
        'mac_address' => $faker->macAddress,
        'instance_id' => 'i-' . $faker->numberBetween(0, PHP_INT_MAX),
        'network_id' => $faker->ipv4,
        'ip_address' => $faker->ipv4,
    ];
});
