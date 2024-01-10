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

use App\Mail\QuotationMail;
use App\Models\Client;
use App\Models\Product;
use App\Models\Unit;
use App\Models\ProductVariant;
use App\Models\product_warehouse;
use App\Models\Quotation;
use App\Models\QuotationDetail;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\UserWarehouse;
use App\utils\helpers;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use \Nwidart\Modules\Facades\Module;
use App\Models\sms_gateway;
use DB;
use PDF;
use ArPHP\I18N\Arabic;

class QuotationsController extends BaseController
{

    //---------------- GET ALL QUOTATIONS ---------------\\
    public function index(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Quotation::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');

        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $helpers = new helpers();
        // Filter fields With Params to retrieve
        $param = array(
            0 => 'like',
            1 => 'like',
            2 => '=',
            3 => '=',
            4 => '=',
        );
        $columns = array(
            0 => 'Ref',
            1 => 'statut',
            2 => 'client_id',
            3 => 'date',
            4 => 'warehouse_id',
        );
        $data = array();

        // Check If User Has Permission View  All Records
        $Quotations = Quotation::with('client', 'warehouse')
            ->where('deleted_at', '=', null)
            ->where(function ($query) use ($view_records) {
                if (!$view_records) {
                    return $query->where('user_id', '=', Auth::user()->id);
                }
            });

        //Multiple Filter
        $Filtred = $helpers->filter($Quotations, $columns, $param, $request)
        //Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('Ref', 'LIKE', "%{$request->search}%")
                        ->orWhere('statut', 'LIKE', "%{$request->search}%")
                        ->orWhere('GrandTotal', $request->search)
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('client', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('warehouse', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });

        $totalRows = $Filtred->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $Quotations = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($Quotations as $Quotation) {

            $item['id'] = $Quotation->id;
            $item['date'] = $Quotation->date;
            $item['Ref'] = $Quotation->Ref;
            $item['statut'] = $Quotation->statut;
            $item['warehouse_name'] = $Quotation['warehouse']->name;
            $item['client_name'] = $Quotation['client']->name;
            $item['client_email'] = $Quotation['client']->email;
            $item['GrandTotal'] = $Quotation->GrandTotal;

            $data[] = $item;
        }

        $customers = client::where('deleted_at', '=', null)->get();
        
        //get warehouses assigned to user
        $user_auth = auth()->user();
        if($user_auth->is_all_warehouses){
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        }else{
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        return response()->json([
            'totalRows' => $totalRows,
            'quotations' => $data,
            'customers' => $customers,
            'warehouses' => $warehouses,
        ]);
    }

    //------------ Store new Quotation -------------\\

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Quotation::class);

        request()->validate([
            'client_id' => 'required',
            'warehouse_id' => 'required',
        ]);

        \DB::transaction(function () use ($request) {

            $order = new Quotation;

            $order->date = $request->date;
            $order->Ref = $this->getNumberOrder();
            $order->statut = $request->statut;
            $order->client_id = $request->client_id;
            $order->GrandTotal = $request->GrandTotal;
            $order->warehouse_id = $request->warehouse_id;
            $order->tax_rate = $request->tax_rate;
            $order->TaxNet = $request->TaxNet;
            $order->discount = $request->Discount;
            $order->shipping = $request->shipping;
            $order->notes = $request->notes;
            $order->user_id = Auth::user()->id;

            $order->save();

            $data = $request['details'];

            foreach ($data as $key => $value) {
                $unit = Unit::where('id', $value['sale_unit_id'])->first();

                $orderDetails[] = [
                    'quotation_id'       => $order->id,
                    'quantity'           => $value['quantity'],
                    'sale_unit_id'       => $value['sale_unit_id']?$value['sale_unit_id']:NULL,
                    'price'              => $value['Unit_price'],
                    'TaxNet'             => $value['tax_percent'],
                    'tax_method'         => $value['tax_method'],
                    'discount'           => $value['discount'],
                    'discount_method'    => $value['discount_Method'],
                    'product_id'         => $value['product_id'],
                    'product_variant_id' => $value['product_variant_id']?$value['product_variant_id']:NULL,
                    'total'              => $value['subtotal'],
                    'imei_number'        => $value['imei_number'],
                ];
            }
            QuotationDetail::insert($orderDetails);
        }, 10);
        return response()->json(['success' => true]);
    }

    //------------ Update Quotation -------------\\

    public function update(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', Quotation::class);

        request()->validate([
            'warehouse_id' => 'required',
            'client_id' => 'required',
        ]);

        \DB::transaction(function () use ($request, $id) {
            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $current_Quotation = Quotation::findOrFail($id);

            // Check If User Has Permission view All Records
            if (!$view_records) {
                // Check If User->id === Quotation->id
                $this->authorizeForUser($request->user('api'), 'check_record', $current_Quotation);
            }

            $old_quotation_details = QuotationDetail::where('quotation_id', $id)->get();
            $new_quotation_details = $request['details'];
            $length = sizeof($new_quotation_details);

            // Get Ids details
            $new_details_id = [];
            foreach ($new_quotation_details as $new_detail) {
                $new_details_id[] = $new_detail['id'];
            }

            // Init quotation with old Parametre
            $old_detail_id = [];
            foreach ($old_quotation_details as $key => $value) {
                $old_detail_id[] = $value->id;

                // Delete Detail
                if (!in_array($old_detail_id[$key], $new_details_id)) {
                    $QuotationDetail = QuotationDetail::findOrFail($value->id);
                    $QuotationDetail->delete();
                }

            }

            // Update quotation with New request
            foreach ($new_quotation_details as $key => $product_detail) {

                $QuoteDetail['quotation_id']       = $id;
                $QuoteDetail['quantity']           = $product_detail['quantity'];
                $QuoteDetail['sale_unit_id']       = $product_detail['sale_unit_id'];
                $QuoteDetail['product_id']         = $product_detail['product_id'];
                $QuoteDetail['product_variant_id'] = $product_detail['product_variant_id'];
                $QuoteDetail['price']              = $product_detail['Unit_price'];
                $QuoteDetail['TaxNet']             = $product_detail['tax_percent'];
                $QuoteDetail['tax_method']         = $product_detail['tax_method'];
                $QuoteDetail['discount']           = $product_detail['discount'];
                $QuoteDetail['discount_method']    = $product_detail['discount_Method'];
                $QuoteDetail['total']              = $product_detail['subtotal'];
                $QuoteDetail['imei_number']        = $product_detail['imei_number'];

                if (!in_array($product_detail['id'], $old_detail_id)) {
                    QuotationDetail::Create($QuoteDetail);
                } else {
                    QuotationDetail::where('id', $product_detail['id'])->update($QuoteDetail);
                }
            }

            $current_Quotation->update([
                'client_id' => $request['client_id'],
                'warehouse_id' => $request['warehouse_id'],
                'statut' => $request['statut'],
                'notes' => $request['notes'],
                'tax_rate' => $request['tax_rate'],
                'TaxNet' => $request['TaxNet'],
                'date' => $request['date'],
                'discount' => $request['discount'],
                'shipping' => $request['shipping'],
                'GrandTotal' => $request['GrandTotal'],
            ]);

        }, 10);

        return response()->json(['success' => true]);
    }

    //------------ Delete Quotation -------------\\

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Quotation::class);

        \DB::transaction(function () use ($id, $request) {

            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $Quotation = Quotation::findOrFail($id);

            // Check If User Has Permission view All Records
            if (!$view_records) {
                // Check If User->id === Quotation->id
                $this->authorizeForUser($request->user('api'), 'check_record', $Quotation);
            }
            $Quotation->details()->delete();
            $Quotation->update([
                'deleted_at' => Carbon::now(),
            ]);

        }, 10);

        return response()->json(['success' => true]);
    }

    //-------------- Delete by selection  ---------------\\

    public function delete_by_selection(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Quotation::class);

        \DB::transaction(function () use ($request) {

            $role = Auth::user()->roles()->first();
            $view_records = Role::findOrFail($role->id)->inRole('record_view');
            $selectedIds = $request->selectedIds;
            foreach ($selectedIds as $Quotation_id) {
                $Quotation = Quotation::findOrFail($Quotation_id);

                // Check If User Has Permission view All Records
                if (!$view_records) {
                    // Check If User->id === Quotation->id
                    $this->authorizeForUser($request->user('api'), 'check_record', $Quotation);
                }
                $Quotation->details()->delete();
                $Quotation->update([
                    'deleted_at' => Carbon::now(),
                ]);
            }

        }, 10);

        return response()->json(['success' => true]);
    }


    //---------------- Get Details Quotation-----------------\\

    public function show(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'view', Quotation::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $quotation_data = Quotation::with('details.product.unitSale')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $details = array();

        // Check If User Has Permission view All Records
        if (!$view_records) {
            // Check If User->id === Quotation->id
            $this->authorizeForUser($request->user('api'), 'check_record', $quotation_data);
        }

        $quote['Ref'] = $quotation_data->Ref;
        $quote['date'] = $quotation_data->date;
        $quote['note'] = $quotation_data->notes;
        $quote['statut'] = $quotation_data->statut;
        $quote['discount'] = $quotation_data->discount;
        $quote['shipping'] = $quotation_data->shipping;
        $quote['tax_rate'] = $quotation_data->tax_rate;
        $quote['TaxNet'] = $quotation_data->TaxNet;
        $quote['client_name'] = $quotation_data['client']->name;
        $quote['client_phone'] = $quotation_data['client']->phone;
        $quote['client_adr'] = $quotation_data['client']->adresse;
        $quote['client_email'] = $quotation_data['client']->email;
        $quote['client_tax'] = $quotation_data['client']->tax_number;
        $quote['warehouse'] = $quotation_data['warehouse']->name;
        $quote['GrandTotal'] = number_format($quotation_data['GrandTotal'], 2, '.', '');

        foreach ($quotation_data['details'] as $detail) {

             //check if detail has sale_unit_id Or Null
             if($detail->sale_unit_id !== null){
                $unit = Unit::where('id', $detail->sale_unit_id)->first();
            }else{
                $product_unit_sale_id = Product::with('unitSale')
                ->where('id', $detail->product_id)
                ->first();

                if($product_unit_sale_id['unitSale']){
                    $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                }{
                    $unit = NULL;
                }
            }

            if ($detail->product_variant_id) {

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $data['code'] = $productsVariants->code;
                $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];

            } else {
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];
            }
            
            $data['quantity']  = $detail->quantity;
            $data['total']     = $detail->total;
            $data['price']     = $detail->price;
            $data['unit_sale'] = $unit?$unit->ShortName:'';

            if ($detail->discount_method == '2') {
                $data['DiscountNet'] = $detail->discount;
            } else {
                $data['DiscountNet'] = $detail->price * $detail->discount / 100;
            }

            $tax_price = $detail->TaxNet * (($detail->price - $data['DiscountNet']) / 100);
            $data['Unit_price'] = $detail->price;
            $data['discount'] = $detail->discount;

            if ($detail->tax_method == '1') {
                $data['Net_price'] = $detail->price - $data['DiscountNet'];
                $data['taxe'] = $tax_price;
            } else {
                $data['Net_price'] = ($detail->price - $data['DiscountNet']) / (($detail->TaxNet / 100) + 1);
                $data['taxe'] = $detail->price - $data['Net_price'] - $data['DiscountNet'];
            }

            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;

            $details[] = $data;
        }

        $company = Setting::where('deleted_at', '=', null)->first();

        return response()->json([
            'quote' => $quote,
            'details' => $details,
            'company' => $company,
        ]);

    }

    //---------------- Reference Number Of Quotation  ---------------\\

    public function getNumberOrder()
    {
        $last = DB::table('quotations')->latest('id')->first();

        if ($last) {
            $item = $last->Ref;
            $nwMsg = explode("_", $item);
            $inMsg = $nwMsg[1] + 1;
            $code = $nwMsg[0] . '_' . $inMsg;
        } else {
            $code = 'QT_1111';
        }
        return $code;

    }

    //---------------- Quotation PDF ---------------\\

    public function Quotation_pdf(Request $request, $id)
    {

        $details = array();
        $helpers = new helpers();
        $Quotation = Quotation::with('details.product.unitSale')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);

        $quote['client_name'] = $Quotation['client']->name;
        $quote['client_phone'] = $Quotation['client']->phone;
        $quote['client_adr'] = $Quotation['client']->adresse;
        $quote['client_email'] = $Quotation['client']->email;
        $quote['client_tax'] = $Quotation['client']->tax_number;
        $quote['TaxNet'] = number_format($Quotation->TaxNet, 2, '.', '');
        $quote['discount'] = number_format($Quotation->discount, 2, '.', '');
        $quote['shipping'] = number_format($Quotation->shipping, 2, '.', '');
        $quote['statut'] = $Quotation->statut;
        $quote['Ref'] = $Quotation->Ref;
        $quote['date'] = $Quotation->date;
        $quote['GrandTotal'] = number_format($Quotation->GrandTotal, 2, '.', '');

        $detail_id = 0;
        foreach ($Quotation['details'] as $detail) {

            //check if detail has sale_unit_id Or Null
             if($detail->sale_unit_id !== null){
                $unit = Unit::where('id', $detail->sale_unit_id)->first();
            }else{
                $product_unit_sale_id = Product::with('unitSale')
                ->where('id', $detail->product_id)
                ->first();

                if($product_unit_sale_id['unitSale']){
                    $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                }{
                    $unit = NULL;
                }
            }

            if ($detail->product_variant_id) {

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $data['code'] = $productsVariants->code;
                $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];

            } else {
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];
            }
            
                $data['detail_id'] = $detail_id += 1;
                $data['quantity'] = number_format($detail->quantity, 2, '.', '');
                $data['total'] = number_format($detail->total, 2, '.', '');
                $data['unitSale'] = $unit?$unit->ShortName:'';
                $data['price'] = number_format($detail->price, 2, '.', '');

            if ($detail->discount_method == '2') {
                $data['DiscountNet'] = number_format($detail->discount, 2, '.', '');
            } else {
                $data['DiscountNet'] = number_format($detail->price * $detail->discount / 100, 2, '.', '');
            }

            $tax_price = $detail->TaxNet * (($detail->price - $data['DiscountNet']) / 100);
            $data['Unit_price'] = number_format($detail->price, 2, '.', '');
            $data['discount'] = number_format($detail->discount, 2, '.', '');

            if ($detail->tax_method == '1') {
                $data['Net_price'] = $detail->price - $data['DiscountNet'];
                $data['taxe'] = number_format($tax_price, 2, '.', '');
            } else {
                $data['Net_price'] = ($detail->price - $data['DiscountNet']) / (($detail->TaxNet / 100) + 1);
                $data['taxe'] = number_format($detail->price - $data['Net_price'] - $data['DiscountNet'], 2, '.', '');
            }

            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;

            $details[] = $data;
        }

        $settings = Setting::where('deleted_at', '=', null)->first();
        $symbol = $helpers->Get_Currency_Code();

        $Html = view('pdf.quotation_pdf', [
            'symbol' => $symbol,
            'setting' => $settings,
            'quote' => $quote,
            'details' => $details,
        ])->render();

        $arabic = new Arabic();
        $p = $arabic->arIdentify($Html);

        for ($i = count($p)-1; $i >= 0; $i-=2) {
            $utf8ar = $arabic->utf8Glyphs(substr($Html, $p[$i-1], $p[$i] - $p[$i-1]));
            $Html = substr_replace($Html, $utf8ar, $p[$i-1], $p[$i] - $p[$i-1]);
        }

        $pdf = PDF::loadHTML($Html);
        return $pdf->download('quotation.pdf');

    }

    //---------------- Show Form Create Quotation ---------------\\

    public function create(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'create', Quotation::class);

        //get warehouses assigned to user
        $user_auth = auth()->user();
        if($user_auth->is_all_warehouses){
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        }else{
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        $clients = Client::where('deleted_at', '=', null)->get(['id', 'name']);
        $quotation_with_stock = Setting::where('deleted_at', '=', null)->first()->quotation_with_stock;

        return response()->json([
            'clients'              => $clients,
            'warehouses'           => $warehouses,
            'quotation_with_stock' => $quotation_with_stock,
        ]);
    }

    //------------- Show Form Edit Quotation -----------\\

    public function edit(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'update', Quotation::class);
        $role = Auth::user()->roles()->first();
        $view_records = Role::findOrFail($role->id)->inRole('record_view');
        $Quotation = Quotation::with('details.product.unitSale')
            ->where('deleted_at', '=', null)
            ->findOrFail($id);
        $details = array();
        // Check If User Has Permission view All Records
        if (!$view_records) {
            // Check If User->id === Quotation->id
            $this->authorizeForUser($request->user('api'), 'check_record', $Quotation);
        }

        if ($Quotation->client_id) {
            if (Client::where('id', $Quotation->client_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $quote['client_id'] = $Quotation->client_id;
            } else {
                $quote['client_id'] = '';
            }
        } else {
            $quote['client_id'] = '';
        }

        if ($Quotation->warehouse_id) {
            if (Warehouse::where('id', $Quotation->warehouse_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $quote['warehouse_id'] = $Quotation->warehouse_id;
            } else {
                $quote['warehouse_id'] = '';
            }
        } else {
            $quote['warehouse_id'] = '';
        }

        $quote['date'] = $Quotation->date;
        $quote['tax_rate'] = $Quotation->tax_rate;
        $quote['discount'] = $Quotation->discount;
        $quote['shipping'] = $Quotation->shipping;
        $quote['statut'] = $Quotation->statut;
        $quote['notes'] = $Quotation->notes;

        $detail_id = 0;
        foreach ($Quotation['details'] as $detail) {

             //check if detail has sale_unit_id Or Null
             if($detail->sale_unit_id !== null){
                $unit = Unit::where('id', $detail->sale_unit_id)->first();
                $data['no_unit'] = 1;
            }else{
                $product_unit_sale_id = Product::with('unitSale')
                ->where('id', $detail->product_id)
                ->first();

                if($product_unit_sale_id['unitSale']){
                    $unit = Unit::where('id', $product_unit_sale_id['unitSale']->id)->first();
                }{
                    $unit = NULL;
                }

                $data['no_unit'] = 0;
            }

            if ($detail->product_variant_id) {
                $item_product = product_warehouse::where('product_id', $detail->product_id)
                    ->where('product_variant_id', $detail->product_variant_id)
                    ->where('warehouse_id', $Quotation->warehouse_id)
                    ->where('deleted_at', '=', null)
                    ->first();

                $productsVariants = ProductVariant::where('product_id', $detail->product_id)
                    ->where('id', $detail->product_variant_id)->first();

                $item_product ? $data['del'] = 0 : $data['del'] = 1;
                $data['product_variant_id'] = $detail->product_variant_id;

                $data['code'] = $productsVariants->code;
                $data['name'] = '['.$productsVariants->name . ']' . $detail['product']['name'];

                if ($unit && $unit->operator == '/') {
                    $stock = $item_product ? $item_product->qte * $unit->operator_value : 0;
                } else if ($unit && $unit->operator == '*') {
                    $stock = $item_product ? $item_product->qte / $unit->operator_value : 0;
                } else {
                    $stock = 0;
                }

            } else {
                $item_product = product_warehouse::where('product_id', $detail->product_id)
                    ->where('deleted_at', '=', null)
                    ->where('warehouse_id', $Quotation->warehouse_id)
                    ->where('product_variant_id', '=', null)
                    ->first();

                $item_product ? $data['del'] = 0 : $data['del'] = 1;
                $data['product_variant_id'] = null;
                $data['code'] = $detail['product']['code'];
                $data['name'] = $detail['product']['name'];

                if ($unit && $unit->operator == '/') {
                    $stock = $item_product ? $item_product->qte * $unit->operator_value : 0;
                } else if ($unit && $unit->operator == '*') {
                    $stock = $item_product ? $item_product->qte / $unit->operator_value : 0;
                } else {
                    $stock = 0;
                }

            }

            $data['id'] = $detail->id;
            $data['stock'] = $detail['product']['type'] !='is_service'?$stock:'---';
            $data['product_type'] = $detail['product']['type'];
            $data['detail_id'] = $detail_id += 1;
            $data['product_id'] = $detail->product_id;
            $data['quantity'] = $detail->quantity;
            $data['etat'] = 'current';
            $data['qte_copy'] = $detail->quantity;
            $data['total'] = $detail->total;
            $data['unitSale'] = $unit?$unit->ShortName:'';
            $data['sale_unit_id'] = $unit?$unit->id:'';
            $data['is_imei'] = $detail['product']['is_imei'];
            $data['imei_number'] = $detail->imei_number;

            if ($detail->discount_method == '2') {
                $data['DiscountNet'] = $detail->discount;
            } else {
                $data['DiscountNet'] = $detail->price * $detail->discount / 100;
            }

            $tax_price = $detail->TaxNet * (($detail->price - $data['DiscountNet']) / 100);
            $data['Unit_price'] = $detail->price;
            $data['tax_percent'] = $detail->TaxNet;
            $data['tax_method'] = $detail->tax_method;
            $data['discount'] = $detail->discount;
            $data['discount_Method'] = $detail->discount_method;

            if ($detail->tax_method == '1') {
                $data['Net_price'] = $detail->price - $data['DiscountNet'];
                $data['taxe'] = $tax_price;
                $data['subtotal'] = ($data['Net_price'] * $data['quantity']) + ($tax_price * $data['quantity']);
            } else {
                $data['Net_price'] = ($detail->price - $data['DiscountNet']) / (($detail->TaxNet / 100) + 1);
                $data['taxe'] = $detail->price - $data['Net_price'] - $data['DiscountNet'];
                $data['subtotal'] = ($data['Net_price'] * $data['quantity']) + ($tax_price * $data['quantity']);
            }

            $details[] = $data;
        }

        //get warehouses assigned to user
        $user_auth = auth()->user();
        if($user_auth->is_all_warehouses){
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        }else{
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        $clients = Client::where('deleted_at', '=', null)->get(['id', 'name']);

        return response()->json([
            'details' => $details,
            'quote' => $quote,
            'clients' => $clients,
            'warehouses' => $warehouses,
        ]);
    }


    //------------- Send Quotation on Email -----------\\

    public function SendEmail(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Quotation::class);

        //Quotation
        $quotation = Quotation::with('client')->where('deleted_at', '=', null)->findOrFail($request->id);

        $helpers = new helpers();
        $currency = $helpers->Get_Currency();

         //settings
         $settings = Setting::where('deleted_at', '=', null)->first();
     
         //the custom msg of quotation
         $emailMessage  = EmailMessage::where('name', 'quotation')->first();
 
         if($emailMessage){
             $message_body = $emailMessage->body;
             $message_subject = $emailMessage->subject;
         }else{
             $message_body = '';
             $message_subject = '';
         }
 
         //Tags
         $random_number = Str::random(10);
         $quotation_url = url('/api/quote_pdf/' . $request->id.'?'.$random_number);
         $quotation_number = $quotation->Ref;
 
         $total_amount = $currency .' '.number_format($quotation->GrandTotal, 2, '.', ',');
        
         $contact_name = $quotation['client']->name;
         $business_name = $settings->CompanyName;
 
         //receiver email
         $receiver_email = $quotation['client']->email;
 
         //replace the text with tags
         $message_body = str_replace('{contact_name}', $contact_name, $message_body);
         $message_body = str_replace('{business_name}', $business_name, $message_body);
         $message_body = str_replace('{quotation_url}', $quotation_url, $message_body);
         $message_body = str_replace('{quotation_number}', $quotation_number, $message_body);
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
        $this->authorizeForUser($request->user('api'), 'view', Quotation::class);

        //Quotation
        $quotation = Quotation::with('client')->where('deleted_at', '=', null)->findOrFail($request->id);

        $helpers = new helpers();
        $currency = $helpers->Get_Currency();

        //settings
        $settings = Setting::where('deleted_at', '=', null)->first();

        $default_sms_gateway = sms_gateway::where('id' , $settings->sms_gateway)
            ->where('deleted_at', '=', null)->first();

        //the custom msg of quotation
        $smsMessage  = SMSMessage::where('name', 'quotation')->first();

        if($smsMessage){
            $message_text = $smsMessage->text;
        }else{
            $message_text = '';
        }

        //Tags
        $random_number = Str::random(10);
        $quotation_url = url('/api/quote_pdf/' . $request->id.'?'.$random_number);
        $quotation_number = $quotation->Ref;

        $total_amount = $currency .' '.number_format($quotation->GrandTotal, 2, '.', ',');
        
        $contact_name = $quotation['client']->name;
        $business_name = $settings->CompanyName;

        //receiver phone
        $receiverNumber = $quotation['client']->phone;

        //replace the text with tags
        $message_text = str_replace('{contact_name}', $contact_name, $message_text);
        $message_text = str_replace('{business_name}', $business_name, $message_text);
        $message_text = str_replace('{quotation_url}', $quotation_url, $message_text);
        $message_text = str_replace('{quotation_number}', $quotation_number, $message_text);
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

