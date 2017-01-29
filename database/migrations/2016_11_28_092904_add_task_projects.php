<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTaskProjects extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('projects', function($table)
        {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('relation_id')->index()->nullable();

            $table->string('name')->nullable();
            $table->boolean('is_deleted')->default(false);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('relation_id')->references('id')->on('relations')->onDelete('cascade');

            $table->unsignedInteger('public_id')->index();
            $table->unique( array('company_id','public_id') );


            $table->timestamps();
            $table->softDeletes();

        });

        Schema::table('tasks', function ($table)
        {
            $table->unsignedInteger('project_id')->nullable()->index();
            if (Schema::hasColumn('tasks', 'description')) {
                $table->text('description')->change();
            }
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        Schema::table('tasks', function ($table)
        {
            $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
        });
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // is_deleted to standardize tables
        Schema::table('expense_categories', function ($table)
        {
            $table->boolean('is_deleted')->default(false);
        });

        Schema::table('products', function ($table)
        {
            $table->boolean('is_deleted')->default(false);
        });

        // add 'delete cascase' to resolve error when deleting an company
        Schema::table('acc_gateway_tokens', function($table)
        {
            $table->dropForeign('acc_gateway_tokens_default_payment_method_id_foreign');
        });

        Schema::table('acc_gateway_tokens', function($table)
        {
            $table->foreign('default_payment_method_id')->references('id')->on('payment_methods')->onDelete('cascade');
        });

        Schema::table('invoices', function ($table)
        {
            $table->boolean('is_public')->default(false);
        });
        DB::table('invoices')->update(['is_public' => true]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tasks', function ($table)
        {
            $table->dropForeign('tasks_project_id_foreign');
            $table->dropColumn('project_id');
        });

        Schema::dropIfExists('projects');

        Schema::table('expense_categories', function ($table)
        {
            $table->dropColumn('is_deleted');
        });

        Schema::table('products', function ($table)
        {
            $table->dropColumn('is_deleted');
        });

        Schema::table('invoices', function ($table)
        {
            $table->dropColumn('is_public');
        });
    }
}
