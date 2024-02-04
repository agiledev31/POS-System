<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSettingsTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('settings', function(Blueprint $table)
		{
			$table->engine = 'InnoDB';
			$table->integer('id', true);
			$table->integer('workspace_id')->nullable()->indix('workspace_id');
			$table->string('email', 191)->nullable();
			$table->integer('currency_id')->nullable()->index('currency_id');
			$table->string('CompanyName')->nullable();
			$table->string('CompanyPhone')->nullable();
			$table->string('CompanyAdress')->nullable();
			$table->string('logo', 191)->nullable();
			$table->string('timezone')->nullable();
			$table->timestamps(6);
			$table->softDeletes();
		});

}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('settings');
	}

}
