<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTasks extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
        Schema::create('tasks', function($table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('company_id')->index();
            $table->unsignedInteger('relation_id')->nullable();
            $table->unsignedInteger('invoice_id')->nullable();


            $table->timestamp('start_time')->nullable();
            $table->integer('duration')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_deleted')->default(false);

            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
            $table->foreign('relation_id')->references('id')->on('relations')->onDelete('cascade');
            
            $table->unsignedInteger('public_id')->index();
            $table->unique( array('company_id','public_id') );

            $table->timestamps();
            $table->softDeletes();

        });

        Schema::dropIfExists('timesheets');
        Schema::dropIfExists('timesheet_events');
        Schema::dropIfExists('timesheet_event_sources');
        Schema::dropIfExists('project_codes');
        Schema::dropIfExists('projects');
	}

	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
        Schema::drop('tasks');
	}

}
