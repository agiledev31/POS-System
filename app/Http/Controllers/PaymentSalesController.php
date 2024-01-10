<?php
namespace App\Http\Controllers;

use Twilio\Rest\Client as Client_Twilio;
use GuzzleHttp\Client as Client_guzzle;
use App\Models\SMSMessage;
use Infobip\Api\SendSmsApi;
use Infobip\Configuration;
use Infobip\Model\SmsAdvancedTextualRequest;
use Infobip\Model\SmsDestination;
use Infobip\Model\SmsTextualMessage;
use Illuminate\Support\Str;
use App\Models\EmailMessage;
use App\Mail\CustomEmail;
use App\utils\helpers;

use App\Mail\Payment_Sale;
use App\Models\Client;
use App\Models\PaymentSale;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Models\PaymentWithCreditCard;
use \Nwidart\Modules\Facades\Module;
use App\Models\sms_gateway;
use Stripe;
use DB;
use PDF;
use ArPHP\I18N\Arabic;

class PaymentSalesController extends BaseController
{

    //------------- Get All Payments Sales --------------\\

    public function index(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Reports_payments_Sales', PaymentSale::class);

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $helpers = new helpers();
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        // Filter fields With Params to retriever
        $param = array(0 => 'like', 1 => '=', 2 => 'like');
        $columns = array(0 => 'Ref', 1 => 'sale_id', 2 => 'Reglement');
        $data = array();

        // Check If User Has Permission View  All Records
        $Payments = PaymentSale::with('sale.client')
            ->where('deleted_at', '=', null)
            ->whereBetween('date', array($request->from, $request->to))
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            })
        // Multiple Filter
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('client_id'), function ($query) use ($request) {
                    return $query->whereHas('sale.client', function ($q) use ($request) {
                        $q->where('id', '=', $request->client_id);
                    });
                });
            });
        $Filtred = $helpers->filter($Payments, $columns, $param, $request)
        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('date', 'LIKE', "%{$request->search}%")
                        ->orWhere('Reglement', 'LIKE', "%{$request->search}%")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('sale', function ($q) use ($request) {
                                $q->where('Ref', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('sale.client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $Filtred->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $Payments = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($Payments as $Payment) {

            $item['date'] = $Payment->date;
            $item['Ref'] = $Payment->Ref;
            $item['Ref_Sale'] = $Payment['sale']->Ref;
            $item['client_name'] = $Payment['sale']['client']->name;
            $item['Reglement'] = $Payment->Reglement;
            $item['montant'] = $Payment->montant;
            // $item['montant'] = number_format($Payment->montant, 2, '.', '');
            $data[] = $item;
        }

        $clients = Client::where('deleted_at', '=', null)->get(['id', 'name']);
        $sales = Sale::get(['Ref', 'id']);

        return response()->json([
            'totalRows' => $totalRows,
            'payments' => $data,
            'sales' => $sales,
            'clients' => $clients,
        ]);

    }

    //----------- Store new Payment Sale --------------\\

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', PaymentSale::class);

        \DB::transaction(function () use ($request) {
            $helpers = new helpers();
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $sale = Sale::findOrFail($request['sale_id']);

            // Check If User Has Permission view All Records
            if (!$view_records) {
                // Check If User->id === sale->id
                $this->authorizeForUser($request->user('api'), 'check_record', $sale);
            }

            try {

                $total_paid = $sale->paid_amount + $request['montant'];
                $due = $sale->GrandTotal - $total_paid;

                if ($due === 0.0 || $due < 0.0) {
                    $payment_statut = 'paid';
                } else if ($due !== $sale->GrandTotal) {
                    $payment_statut = 'partial';
                } else if ($due === $sale->GrandTotal) {
                    $payment_statut = 'unpaid';
                }

                if($request['montant'] > 0){
                    if ($request['Reglement'] == 'credit card') {
                        $Client = Client::whereId($sale->client_id)->first();
                        Stripe\Stripe::setApiKey(config('app.STRIPE_SECRET'));

                        // Check if the payment record exists
                        $PaymentWithCreditCard = PaymentWithCreditCard::where('customer_id', $sale->client_id)->first();
                        if (!$PaymentWithCreditCard) {

                            // Create a new customer and charge the customer with a new credit card
                            $customer = \Stripe\Customer::create([
                                'source' => $request->token,
                                'email'  => $Client->email,
                                'name'   => $Client->name,
                            ]);

                            // Charge the Customer instead of the card:
                            $charge = \Stripe\Charge::create([
                                'amount'   => $request['montant'] * 100,
                                'currency' => 'usd',
                                'customer' => $customer->id,
                            ]);
                            $PaymentCard['customer_stripe_id'] = $customer->id;

                        // Check if the payment record not exists
                        } else {

                             // Retrieve the customer ID and card ID
                            $customer_id = $PaymentWithCreditCard->customer_stripe_id;
                            $card_id = $request->card_id;

                            // Charge the customer with the new credit card or the selected card
                            if ($request->is_new_credit_card || $request->is_new_credit_card == 'true' || $request->is_new_credit_card === 1) {
                                // Retrieve the customer
                                $customer = \Stripe\Customer::retrieve($customer_id);

                                // Create New Source
                                $card = \Stripe\Customer::createSource(
                                    $customer_id,
                                    [
                                      'source' => $request->token,
                                    ]
                                  );

                                $charge = \Stripe\Charge::create([
                                    'amount'   => $request['montant'] * 100,
                                    'currency' => 'usd',
                                    'customer' => $customer_id,
                                    'source'   => $card->id,
                                ]);
                                $PaymentCard['customer_stripe_id'] = $customer_id;

                            } else {
                                $charge = \Stripe\Charge::create([
                                    'amount'   => $request['montant'] * 100,
                                    'currency' => 'usd',
                                    'customer' => $customer_id,
                                    'source'   => $card_id,
                                ]);
                                $PaymentCard['customer_stripe_id'] = $customer_id;
                            }
                        }


                        $PaymentSale            = new PaymentSale();
                        $PaymentSale->sale_id   = $sale->id;
                        $PaymentSale->Ref       = app('App\Http\Controllers\PaymentSalesController')->getNumberOrder();
                        $PaymentSale->date      = Carbon::now();
                        $PaymentSale->Reglement = $request['Reglement'];
                        $PaymentSale->montant   = $request['montant'];
                        $PaymentSale->change    = $request['change'];
                        $PaymentSale->notes     = $request['notes'];
                        $PaymentSale->user_id   = Auth::user()->id;
                        $PaymentSale->save();

                        $sale->update([
                            'paid_amount'    => $total_paid,
                            'payment_statut' => $payment_statut,
                        ]);

                        $PaymentCard['customer_id'] = $sale->client_id;
                        $PaymentCard['payment_id']  = $PaymentSale->id;
                        $PaymentCard['charge_id']   = $charge->id;
                        PaymentWithCreditCard::create($PaymentCard);

                        // Paying Method Cash
                    } else {

                        PaymentSale::create([
                            'sale_id'   => $sale->id,
                            'Ref'       => app('App\Http\Controllers\PaymentSalesController')->getNumberOrder(),
                            'date'      => Carbon::now(),
                            'Reglement' => $request['Reglement'],
                            'montant'   => $request['montant'],
                            'change'    => $request['change'],
                            'notes'     => $request['notes'],
                            'user_id'   => Auth::user()->id,
                        ]);

                        $sale->update([
                            'paid_amount'    => $total_paid,
                            'payment_statut' => $payment_statut,
                        ]);
                    }

                }

            } catch (Exception $e) {
                return response()->json(['message' => $e->getMessage()], 500);
            }

        }, 10);

        return response()->json(['success' => true, 'message' => 'Payment Create successfully'], 200);
    }

    //------------ function show -----------\\

    public function show($id){
    //
        
    }

    //----------- Update Payments Sale --------------\\

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', PaymentSale::class);

        \DB::transaction(function () use ($id, $request) {
            $helpers = new helpers();
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $payment = PaymentSale::findOrFail($id);

            // Check If User Has Permission view All Records
            if (!$view_records) {
                // Check If User->id === payment->id
                $this->authorizeForUser($request->user('api'), 'check_record', $payment);
            }

            $sale = Sale::find($payment->sale_id);
            $old_total_paid = $sale->paid_amount - $payment->montant;
            $new_total_paid = $old_total_paid + $request['montant'];

            $due = $sale->GrandTotal - $new_total_paid;
            if ($due === 0.0 || $due < 0.0) {
                $payment_statut = 'paid';
            } else if ($due !== $sale->GrandTotal) {
                $payment_statut = 'partial';
            } else if ($due === $sale->GrandTotal) {
                $payment_statut = 'unpaid';
            }

            try {
                if ($payment->Reglement != 'credit card') {

                    $payment->update([
                        'date'      => $request['date'],
                        'Reglement' => $request['Reglement'],
                        'montant'   => $request['montant'],
                        'change'    => $request['change'],
                        'notes'     => $request['notes'],
                    ]);

                    $sale->update([
                        'paid_amount' => $new_total_paid,
                        'payment_statut' => $payment_statut,
                    ]);

                } 

            } catch (Exception $e) {
                return response()->json(['message' => $e->getMessage()], 500);
            }

        }, 10);

        return response()->json(['success' => true, 'message' => 'Payment Update successfully'], 200);
    }




    //----------- Delete Payment Sales --------------\\

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', PaymentSale::class);

        \DB::transaction(function () use ($id, $request) {
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $payment = PaymentSale::findOrFail($id);

            // Check If User Has Permission view All Records
            if (!$view_records) {
                // Check If User->id === payment->id
                $this->authorizeForUser($request->user('api'), 'check_record', $payment);
            }

            $sale = Sale::find($payment->sale_id);
            $total_paid = $sale->paid_amount - $payment->montant;
            $due = $sale->GrandTotal - $total_paid;

            if ($due === 0.0 || $due < 0.0) {
                $payment_statut = 'paid';
            } else if ($due !== $sale->GrandTotal) {
                $payment_statut = 'partial';
            } else if ($due === $sale->GrandTotal) {
                $payment_statut = 'unpaid';
            }

            if ($payment->Reglement == 'credit card') {
                $PaymentWithCreditCard = PaymentWithCreditCard::where('payment_id', $id)->first();
                if($PaymentWithCreditCard){
                    Stripe\Stripe::setApiKey(config('app.STRIPE_SECRET'));
                    // Create Refund
                    \Stripe\Refund::create([
                        'charge' => $PaymentWithCreditCard->charge_id,
                    ]);
    
                    $PaymentWithCreditCard->delete();
                }
            }

            PaymentSale::whereId($id)->update([
                'deleted_at' => Carbon::now(),
            ]);

            $sale->update([
                'paid_amount' => $total_paid,
                'payment_statut' => $payment_statut,
            ]);

        }, 10);

        return response()->json(['success' => true, 'message' => 'Payment Delete successfully'], 200);

    }

    //----------- Reference order Payment Sales --------------\\

    public function getNumberOrder()
    {
        $last = DB::table('payment_sales')->latest('id')->first();

        if ($last) {
            $item = $last->Ref;
            $nwMsg = explode("_", $item);
            $inMsg = $nwMsg[1] + 1;
            $code = $nwMsg[0] . '_' . $inMsg;

        } else {
            $code = 'INV/SL_1111';
        }

        return $code;
    }

    //----------- Payment Sale PDF --------------\\

    public function payment_sale(Request $request, $id)
    {
        $payment = PaymentSale::with('sale', 'sale.client')->findOrFail($id);

        $payment_data['sale_Ref'] = $payment['sale']->Ref;
        $payment_data['client_name'] = $payment['sale']['client']->name;
        $payment_data['client_phone'] = $payment['sale']['client']->phone;
        $payment_data['client_adr'] = $payment['sale']['client']->adresse;
        $payment_data['client_email'] = $payment['sale']['client']->email;
        $payment_data['montant'] = $payment->montant;
        $payment_data['Ref'] = $payment->Ref;
        $payment_data['date'] = $payment->date;
        $payment_data['Reglement'] = $payment->Reglement;

        $helpers = new helpers();
        $settings = Setting::where('deleted_at', '=', null)->first();
        $symbol = $helpers->Get_Currency_Code();

        $Html = view('pdf.payment_sale', [
            'symbol' => $symbol,
            'setting' => $settings,
            'payment' => $payment_data,
        ])->render();

        $arabic = new Arabic();
        $p = $arabic->arIdentify($Html);

        for ($i = count($p)-1; $i >= 0; $i-=2) {
            $utf8ar = $arabic->utf8Glyphs(substr($Html, $p[$i-1], $p[$i] - $p[$i-1]));
            $Html = substr_replace($Html, $utf8ar, $p[$i-1], $p[$i] - $p[$i-1]);
        }

        $pdf = PDF::loadHTML($Html);

        return $pdf->download('Payment_Sale.pdf');


    }



    //------------- Send Payment Sale on Email -----------\\


    public function SendEmail(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', PaymentSale::class);
        //PaymentSale
        $payment = PaymentSale::with('sale.client')->findOrFail($request->id);

        $helpers = new helpers();
        $currency = $helpers->Get_Currency();

        //settings
        $settings = Setting::where('deleted_at', '=', null)->first();
    
        //the custom msg of payment_received
        $emailMessage  = EmailMessage::where('name', 'payment_received')->first();

        if($emailMessage){
            $message_body = $emailMessage->body;
            $message_subject = $emailMessage->subject;
        }else{
            $message_body = '';
            $message_subject = '';
        }
    
        
        $payment_number = $payment->Ref;

        $total_amount = $currency .' '.number_format($payment->montant, 2, '.', ',');
    
        $contact_name = $payment['sale']['client']->name;
        $business_name = $settings->CompanyName;

        //receiver email
        $receiver_email = $payment['sale']['client']->email;

        //replace the text with tags
        $message_body = str_replace('{contact_name}', $contact_name, $message_body);
        $message_body = str_replace('{business_name}', $business_name, $message_body);
        $message_body = str_replace('{payment_number}', $payment_number, $message_body);
        $message_body = str_replace('{total_amount}', $total_amount, $message_body);

        $email['subject'] = $message_subject;
        $email['body'] = $message_body;
        $email['company_name'] = $business_name;

        $this->Set_config_mail(); 

        $mail = Mail::to($receiver_email)->send(new CustomEmail($email));

        return $mail;
    }
   
 
   
   
    //-------------------Sms Notifications -----------------\\

    public function Send_SMS(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', PaymentSale::class);

        //PaymentSale
        $payment = PaymentSale::with('sale.client')->findOrFail($request->id);

        //settings
        $settings = Setting::where('deleted_at', '=', null)->first();
        
        $default_sms_gateway = sms_gateway::where('id' , $settings->sms_gateway)
         ->where('deleted_at', '=', null)->first();

        $helpers = new helpers();
        $currency = $helpers->Get_Currency();

        //the custom msg of payment_received
        $smsMessage  = SMSMessage::where('name', 'payment_received')->first();

        if($smsMessage){
            $message_text = $smsMessage->text;
        }else{
            $message_text = '';
        }
        
        $payment_number = $payment->Ref;

        $total_amount = $currency .' '.number_format($payment->montant, 2, '.', ',');
        
        $contact_name = $payment['sale']['client']->name;
        $business_name = $settings->CompanyName;
    
        //receiver phone
        $receiverNumber = $payment['sale']['client']->phone;

        //replace the text with tags
        $message_text = str_replace('{contact_name}', $contact_name, $message_text);
        $message_text = str_replace('{business_name}', $business_name, $message_text);
        $message_text = str_replace('{payment_number}', $payment_number, $message_text);
        $message_text = str_replace('{total_amount}', $total_amount, $message_text);

        //twilio
        if($default_sms_gateway->title == "twilio"){
            try {
    
                $account_sid = env("TWILIO_SID");
                $auth_token = env("TWILIO_TOKEN");
                $twilio_number = env("TWILIO_FROM");
    
                $client = new Client_Twilio($account_sid, $auth_token);
                $client->messages->create($receiverNumber, [
                    'from' => $twilio_number, 
                    'body' => $message_text]);
        
            } catch (Exception $e) {
                return response()->json(['message' => $e->getMessage()], 500);
            }
        //nexmo
        }elseif($default_sms_gateway->title == "nexmo"){
            try {

                $basic  = new \Nexmo\Client\Credentials\Basic(env("NEXMO_KEY"), env("NEXMO_SECRET"));
                $client = new \Nexmo\Client($basic);
                $nexmo_from = env("NEXMO_FROM");
        
                $message = $client->message()->send([
                    'to' => $receiverNumber,
                    'from' => $nexmo_from,
                    'text' => $message_text
                ]);
                        
            } catch (Exception $e) {
                return response()->json(['message' => $e->getMessage()], 500);
            }

        //---- infobip
        }elseif($default_sms_gateway->title == "infobip"){

            $BASE_URL = env("base_url");
            $API_KEY = env("api_key");
            $SENDER = env("sender_from");

            $configuration = (new Configuration())
                ->setHost($BASE_URL)
                ->setApiKeyPrefix('Authorization', 'App')
                ->setApiKey('Authorization', $API_KEY);
            
            $client = new Client_guzzle();
            
            $sendSmsApi = new SendSMSApi($client, $configuration);
            $destination = (new SmsDestination())->setTo($receiverNumber);
            $message = (new SmsTextualMessage())
                ->setFrom($SENDER)
                ->setText($message_text)
                ->setDestinations([$destination]);
                
            $request = (new SmsAdvancedTextualRequest())->setMessages([$message]);
            
            try {
                $smsResponse = $sendSmsApi->sendSmsMessage($request);
                echo ("Response body: " . $smsResponse);
            } catch (Throwable $apiException) {
                echo("HTTP Code: " . $apiException->getCode() . "\n");
            }
            
        }

        return response()->json(['success' => true]);
    }

}
