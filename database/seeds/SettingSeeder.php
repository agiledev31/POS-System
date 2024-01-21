<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
       // Insert some stuff
        DB::table('settings')->insert(
            array(
                'id' => 1,
                'email' => 'admin@example.com',
                'currency_id' => 1,
                'client_id' => 1,
                'sms_gateway' => 1,
                'is_invoice_footer' => 0,
                'invoice_footer' => Null,
                'warehouse_id' => Null,
                'CompanyName' => 'Ahmed POS',
                'CompanyPhone' => '9647803151508',
                'CompanyAdress' => 'Baghdad, Iraq',
                'footer' => 'Ahmed - Ultimate Inventory With POS',
                'developed_by' => 'Ahmed',
                'logo' => 'logo-2.png',
            )
            
        );
    }
}
