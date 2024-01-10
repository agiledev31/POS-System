<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Stripe;
use App\Models\PaymentWithCreditCard;

class StripeController extends Controller
{
    public function __construct()
    {
        Stripe\Stripe::setApiKey(config('app.STRIPE_SECRET'));
    }

    // retrieve Customer
    public function retrieveCustomer(Request $request)
    {
        Stripe\Stripe::setApiKey(config('app.STRIPE_SECRET'));
        
        $customerId = $request->query('customerId');

        try {

            $customerPayments = PaymentWithCreditCard::where('customer_id', $customerId)->get();
            $data = [];
            $customer_default_source = '';

            if ($customerPayments->count() > 0) {
                // The customer has saved payment methods
                // You can retrieve the customer's Stripe ID from any of the payments
                $customerStripeId = $customerPayments->first()->customer_stripe_id;

                $customer = \Stripe\Customer::retrieve($customerStripeId);
                $customer_default_source = $customer->default_source;

                $sources =  \Stripe\Customer::allSources(
                    $customerStripeId,
                    ['object' => 'card']
                );

                if ($sources) {
                    // Loop through the payment sources and retrieve the last 4 digits of each credit card
                    foreach ($sources->data as $source) {
                        $item['card_id'] = $source->id;
                        $item['last4']   = $source->last4;
                        $item['type']    = $source->brand;
                        $item['exp']     = $source->exp_month.'/'.$source->exp_year;

                        $data[] =  $item;
                    }
                }
   
            }

            if (!empty($data)) {

                return response()->json(['data' => $data , 'customer_default_source' => $customer_default_source], 200);
            }else{
                return response()->json(['data' => $data , 'customer_default_source' => $customer_default_source], 402);
            }
           
        } catch (\Stripe\Exception\CardException $e) {
            return response()->json(['error' => $e->getMessage()]);
        }

        
    }

    // Update a customer
    public function updateCustomer(Request $request)
    {
        Stripe\Stripe::setApiKey(config('app.STRIPE_SECRET'));
        
        $customer_id = $request->customer_id;
        $card_id     = $request->card_id;

        $customerPayments = PaymentWithCreditCard::where('customer_id', $customer_id)->get();

        try {

            if ($customerPayments->count() > 0) {
                // The customer has saved payment methods
                // You can retrieve the customer's Stripe ID from any of the payments
                $customerStripeId = $customerPayments->first()->customer_stripe_id;

                $customer = \Stripe\Customer::retrieve($customerStripeId);
                $customer->default_source= $card_id;

                $customer->save(); 
            }

           

            return response()->json(['success' => true], 200);

        } catch (\Stripe\Exception\CardException $e) {
            return response()->json(['error' => $e->getMessage()]);
        }

    }
}
