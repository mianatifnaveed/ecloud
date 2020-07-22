<?php

/** @var \Illuminate\Database\Eloquent\Factory $factory */

use App\Models\V2\Network;
use Faker\Generator as Faker;

$factory->define(Network::class, function (Faker $faker) {
    return [
        'name' => 'My Gateway 1'
    ];
});
