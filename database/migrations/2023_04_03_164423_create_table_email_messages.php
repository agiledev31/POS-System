<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTableEmailMessages extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('email_messages', function (Blueprint $table) {
            $table->engine = 'InnoDB';
			$table->integer('id', true);
            $table->text('name')->nullable();
            $table->text('subject')->nullable();
            $table->text('body')->nullable();
            $table->timestamps(6);
			$table->softDeletes();
        });
    
        DB::table('email_messages')->insert(
            array([
               'id' => 1,
               'name' => 'sale',
               'subject' => 'Thank you for your purchase!',
               'body' => "<h1><b><span style='font-size:14px;'>Dear</span><span style='font-size:14px;'>  </span></b><span style='font-size:14px;'><b>{contact_name},</b></span></h1><p><span style='font-size:14px;'>Thank you for your purchase! Your invoice number is {invoice_number}.</span></p><p><span style='font-size:14px;'>If you have any questions or concerns, please don't hesitate to reach out to us. We are here to help!</span></p><p><span style='font-size:14px;'>Best regards,</span></p><p><b>{business_name}</b></p>",
            ],
            [
                'id' => 2,
                'name' => 'quotation',
                'subject' => 'Thank you for your interest in our products !',
                'body' =>  '<p><b><span style="font-size:14px;">Dear {contact_name},</span></b></p><p>Thank you for your interest in our products. Your quotation number is {quotation_number}.</p><p>Please let us know if you have any questions or concerns regarding your quotation. We are here to assist you.</p><p>Best regards,</p><p><b><span style="font-size:14px;">{business_name}</span></b></p>',
            ],
            [
                'id' => 3,
                'name' => 'payment_received',
                'subject' => 'Payment Received - Thank You',
                'body' =>  '<p><b><span style="font-size:14px;">Dear {contact_name},</span></b></p><p>Thank you for making your payment. We have received it and it has been processed successfully.</p><p>If you have any further questions or concerns, please don\'t hesitate to reach out to us. We are always here to help.</p><p>Best regards,</p><p><b><span style="font-size:14px;">{business_name}</span></b></p>',
            ],
            [
                'id' => 4,
                'name' => 'purchase',
                'subject' => 'Thank You for Your Cooperation and Service',
                'body' =>  '<p><b><span style="font-size:14px;">Dear {contact_name},</span></b></p><p>I recently made a purchase from your company and I wanted to thank you for your cooperation and service. My invoice number is {invoice_number} .</p><p>If you have any questions or concerns regarding my purchase, please don\'t hesitate to contact me. I am here to make sure I have a positive experience with your company.</p><p>Best regards,</p><p><b><span style="font-size:14px;">{business_name}</span></b></p>',
            ],
            [
                'id' => 5,
                'name' => 'payment_sent',
                'subject' => 'Payment Sent - Thank You for Your Service',
                'body' =>  '<p><b><span style="font-size:14px;">Dear {contact_name},</span></b></p><p>We have just sent the payment . We appreciate your prompt attention to this matter and the high level of service you provide.</p><p>If you need any further information or clarification, please do not hesitate to reach out to us. We are here to help.</p><p>Best regards,</p><p><b><span style="font-size:14px;">{business_name}</span></b></p>',
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
        Schema::dropIfExists('email_messages');
    }
}
