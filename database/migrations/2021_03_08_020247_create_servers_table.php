<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServersTable extends Migration {

	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('servers', function(Blueprint $table)
		{
			$table->engine = 'InnoDB';
			$table->integer('id', true);
			$table->integer('workspace_id')->nullable()->index('workspace_id');
			$table->string('host', 191)->nullable();
			$table->integer('port')->nullable();
			$table->string('username', 191)->nullable();
			$table->string('password', 191)->nullable();
			$table->string('encryption', 191)->nullable();
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
		Schema::drop('servers');
	}

}
