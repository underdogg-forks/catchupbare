<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class EncryptTokens extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $gateways = DB::table('acc_gateways')
            ->get(['id', 'config']);
        foreach ($gateways as $gateway) {
            DB::table('acc_gateways')
                ->where('id', $gateway->id)
                ->update(['config' => Crypt::encrypt($gateway->config)]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $gateways = DB::table('acc_gateways')
            ->get(['id', 'config']);
        foreach ($gateways as $gateway) {
            DB::table('acc_gateways')
                ->where('id', $gateway->id)
                ->update(['config' => Crypt::decrypt($gateway->config)]);
        }

    }

}
