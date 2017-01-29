<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBankSubcompanies extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('bank_subaccs', function ($table) {
            $table->increments('id');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('bank_acc_id');

            $table->string('acc_name');
            $table->string('acc_number');

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('bank_acc_id')->references('id')->on('bank_accs')->onDelete('cascade');

            $table->unsignedInteger('public_id')->index();
            $table->unique(['company_id', 'public_id']);

            $table->timestamps();
            $table->softDeletes();

        });

        Schema::table('expenses', function ($table) {
            $table->string('transaction_id')->nullable();
            $table->unsignedInteger('bank_id')->nullable();
        });

        Schema::table('vendors', function ($table) {
            $table->string('transaction_name')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('bank_subaccs');

        Schema::table('expenses', function ($table) {
            $table->dropColumn('transaction_id');
            $table->dropColumn('bank_id');
        });

        Schema::table('vendors', function ($table) {
            $table->dropColumn('transaction_name');
        });
    }

}
