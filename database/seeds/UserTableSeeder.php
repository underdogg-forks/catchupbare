<?php

use App\Models\User;
use App\Models\Font;
use App\Models\Company;
use App\Models\Corporation;
use App\Models\Affiliate;
use App\Models\Country;
use App\Models\InvoiceDesign;
use App\Models\Client;
use App\Models\Contact;
use App\Models\Product;
use App\Models\DateFormat;
use Faker\Factory;

class UserTableSeeder extends Seeder
{

    public function run()
    {
        $this->command->info('Running UserTableSeeder');

        Eloquent::unguard();

        $faker = Faker\Factory::create();
        $corporation = Corporation::create();

        $company = Company::create([
            'name' => $faker->name,
            'address1' => $faker->streetAddress,
            'address2' => $faker->secondaryAddress,
            'city' => $faker->city,
            'state' => $faker->state,
            'postal_code' => $faker->postcode,
            'country_id' => Country::all()->random()->id,
            'acc_key' => str_random(RANDOM_KEY_LENGTH),
            'invoice_terms' => $faker->text($faker->numberBetween(50, 300)),
            'work_phone' => $faker->phoneNumber,
            'work_email' => $faker->safeEmail,
            'invoice_design_id' => InvoiceDesign::where('id', '<', CUSTOM_DESIGN)->get()->random()->id,
            'header_font_id' => min(Font::all()->random()->id, 17),
            'body_font_id' => min(Font::all()->random()->id, 17),
            'primary_color' => $faker->hexcolor,
            'timezone_id' => 1,
            'corporation_id' => $corporation->id,
            //'date_format_id' => DateFormat::all()->random()->id,
        ]);

        $user = User::create([
            'first_name' => $faker->firstName,
            'last_name' => $faker->lastName,
            'email' => TEST_USERNAME,
            'username' => TEST_USERNAME,
            'company_id' => $company->id,
            'password' => Hash::make(TEST_PASSWORD),
            'registered' => true,
            'confirmed' => true,
            'notify_sent' => false,
            'notify_paid' => false,
            'is_admin' => 1,
        ]);

        $client = Client::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'public_id' => 1,
            'name' => $faker->name,
            'address1' => $faker->streetAddress,
            'address2' => $faker->secondaryAddress,
            'city' => $faker->city,
            'state' => $faker->state,
            'postal_code' => $faker->postcode,
            'country_id' => DEFAULT_COUNTRY,
            'currency_id' => DEFAULT_CURRENCY,
        ]);

        Contact::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'public_id' => 1,
            'email' => env('TEST_EMAIL', TEST_USERNAME),
            'is_primary' => true,
            'send_invoice' => true,
        ]);

        Product::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'public_id' => 1,
            'product_key' => 'ITEM',
            'notes' => 'Something nice...',
            'cost' => 10,
        ]);

        Affiliate::create([
            'affiliate_key' => SELF_HOST_AFFILIATE_KEY
        ]);

    }

}
