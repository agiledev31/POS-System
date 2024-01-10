<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSmsMessagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->engine = 'InnoDB';
			$table->integer('id', true);
            $table->text('name')->nullable();
            $table->text('text')->nullable();
            $table->timestamps(6);
			$table->softDeletes();
        });

        DB::table('sms_messages')->insert(
            array([
               'id' => 1,
               'name' => 'sale',
               'text' => "Dear {contact_name},\nThank you for your purchase! Your invoice number is {invoice_number}.\nIf you have any questions or concerns, please don't hesitate to reach out to us. We are here to help!\nBest regards,\n{business_name}"
            ],[
               'id' => 2,
               'name' => 'purchase',
               'text' => "Dear {contact_name},\nI recently made a purchase from your company and I wanted to thank you for your cooperation and service. My invoice number is {invoice_number} .\nIf you have any questions or concerns regarding my purchase, please don't hesitate to contact me. I am here to make sure I have a positive experience with your company.\nBest regards,\n{business_name}"
            ],[
                'id' => 3,
                'name' => 'quotation',
                'text' => "Dear {contact_name},\nThank you for your interest in our products. Your quotation number is {quotation_number}.\nPlease let us know if you have any questions or concerns regarding your quotation. We are here to assist you.\nBest regards,\n{business_name}"
             ],
             [
                'id' => 4,
                'name' => 'payment_received',
                'text' => "Dear {contact_name},\nThank you for making your payment. We have received it and it has been processed successfully.\nIf you have any further questions or concerns, please don't hesitate to reach out to us. We are always here to help.\nBest regards,\n{business_name}"
             ],
             [
                'id' => 5,
                'name' => 'payment_sent',
                'text' => "Dear {contact_name},\nWe have just sent the payment . We appreciate your prompt attention to this matter and the high level of service you provide.\nIf you need any further information or clarification, please do not hesitate to reach out to us. We are here to help.\nBest regards,\n{business_name}"
             ],
            )
         );
         
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sms_messages');
    }
}
