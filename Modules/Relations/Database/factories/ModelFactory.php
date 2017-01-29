<?php

use Modules\Relations\Models\Relation; 
use Modules\Relations\Models\Contact;
use App\Models\Country;

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| Here you may define all of your model factories. Model factories give
| you a convenient way to create models for testing and seeding your
| database. Just tell the factory how a default model should look.
|
*/

$factory->define(Contact::class, function (Faker\Generator $faker) {
    return [
        'relation_id' => function() {
            return factory(Relation::class)->create()->id;
        },
        'user_id' => 1,
        'company_id' => 1,
        'public_id' => Contact::count() + 1,
        'is_primary' => true,
        'send_invoice' => true,
        'first_name' => $faker->firstName,
        'last_name' => $faker->lastName,
        'email' => $faker->safeEmail,
        'phone' => $faker->phoneNumber,
    ];
});

$factory->define(Relation::class, function (Faker\Generator $faker) {
    return [
        'user_id' => 1,
        'company_id' => 1,
        'public_id' => Relation::count() + 1,
        'name' => $faker->name,
        'address1' => $faker->streetAddress,
        'address2' => $faker->secondaryAddress,
        'city' => $faker->city,
        'state' => $faker->state,
        'postal_code' => $faker->postcode,
        'country_id' => Country::all()->random()->id, 
    ];
});