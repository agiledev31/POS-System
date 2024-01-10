<?php

namespace App\Http\Controllers;
use App\Models\User;
use App\Models\UserWarehouse;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\product_warehouse;
use App\Models\Unit;
use App\Models\Warehouse;
use App\utils\helpers;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use \Gumlet\ImageResize;

class ProductsController extends BaseController
{

    //------------ Get ALL Products --------------\\

    public function index(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'view', Product::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $helpers = new helpers();
        // Filter fields With Params to retrieve
        $columns = array(0 => 'name', 1 => 'category_id', 2 => 'brand_id', 3 => 'code');
        $param = array(0 => 'like', 1 => '=', 2 => '=', 3 => 'like');
        $data = array();

        $products = Product::with('unit', 'category', 'brand')
            ->where('deleted_at', '=', null);

        //Multiple Filter
        $Filtred = $helpers->filter($products, $columns, $param, $request)
        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('products.name', 'LIKE', "%{$request->search}%")
                        ->orWhere('products.code', 'LIKE', "%{$request->search}%")
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('category', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        })
                        ->orWhere(function ($query) use ($request) {
                            return $query->whereHas('brand', function ($q) use ($request) {
                                $q->where('name', 'LIKE', "%{$request->search}%");
                            });
                        });
                });
            });
        $totalRows = $Filtred->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $products = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();

        foreach ($products as $product) {
            $item['id'] = $product->id;
            $item['code'] = $product->code;
            $item['name'] = $product->name;
            $item['category'] = $product['category']->name;
            $item['brand'] = $product['brand'] ? $product['brand']->name : 'N/D';
           

            $firstimage = explode(',', $product->image);
            $item['image'] = $firstimage[0];


            if($product->type == 'is_single'){

                $item['type']  = 'Single';
                $item['cost']  = number_format($product->cost, 2, '.', ',');
                $item['price'] = number_format($product->price, 2, '.', ',');
                $item['unit'] = $product['unit']->ShortName;

              $product_warehouse_total_qty = product_warehouse::where('product_id', $product->id)
              ->where('deleted_at', '=', null)
              ->sum('qte');
             
              $item['quantity'] = $product_warehouse_total_qty .' '.$product['unit']->ShortName;

              }elseif($product->type == 'is_variant'){

                  $item['type'] = 'Variable';
                  $product_variant_data = ProductVariant::where('product_id', $product->id)
                  ->where('deleted_at', '=', null)
                  ->get();

                  $item['cost'] = '';
                  $item['price'] = '';
                  $item['unit'] = $product['unit']->ShortName;

                  foreach ($product_variant_data as $product_variant) {
                      $item['cost']  .= number_format($product_variant->cost, 2, '.', ',');
                      $item['cost']  .= '<br>';
                      $item['price'] .= number_format($product_variant->price, 2, '.', ',');
                      $item['price'] .= '<br>';
                  }

                  $product_warehouse_total_qty = product_warehouse::where('product_id', $product->id)
                  ->where('deleted_at', '=', null)
                  ->sum('qte');
                 
                  $item['quantity'] = $product_warehouse_total_qty .' '.$product['unit']->ShortName;

              }else{
                  $item['type'] = 'Service';
                  $item['cost'] = '----';
                  $item['quantity'] = '----';
                  $item['unit'] = '----';

                  $item['price'] = number_format($product->price, 2, '.', ',');
              }


            $data[] = $item;
        }

        //get warehouses assigned to user
        $user_auth = auth()->user();
        if($user_auth->is_all_warehouses){
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        }else{
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        $categories = Category::where('deleted_at', null)->get(['id', 'name']);
        $brands = Brand::where('deleted_at', null)->get(['id', 'name']);

        return response()->json([
            'warehouses' => $warehouses,
            'categories' => $categories,
            'brands' => $brands,
            'products' => $data,
            'totalRows' => $totalRows,
        ]);
    }

    //-------------- Store new  Product  ---------------\\

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', Product::class);

        try {
           
            // define validation rules for product
            $productRules = [
                'code'         => [
                    'required',
                    Rule::unique('products')->where(function ($query) {
                        return $query->where('deleted_at', '=', null);
                    }),

                    Rule::unique('product_variants')->where(function ($query) {
                        return $query->where('deleted_at', '=', null);
                    }),
                ],
                'name'         => 'required',
                'Type_barcode' => 'required',
                'category_id'  => 'required',
                'type'         => 'required',
                'tax_method'   => 'required',
                'unit_id'      => Rule::requiredIf($request->type != 'is_service'),
                'cost'         => Rule::requiredIf($request->type == 'is_single'),
                'price'        => Rule::requiredIf($request->type != 'is_variant'),
            ];


           // if type is not is_variant, add validation for variants array
            if ($request->type == 'is_variant') {
                $productRules['variants'] = [
                    'required',
                    function ($attribute, $value, $fail) use ($request) {
                        // check if array is not empty
                        if (empty($value)) {
                            $fail('The variants array is required.');
                            return;
                        }

                        // check for duplicate codes in variants array
                        $variants = json_decode($request->variants, true);

                        if($variants){
                            foreach ($variants as $variant) {
                                if (!array_key_exists('text', $variant) || empty($variant['text'])) {
                                    $fail('Variant Name cannot be empty.');
                                    return;
                                }else if(!array_key_exists('code', $variant) || empty($variant['code'])) {
                                    $fail('Variant code cannot be empty.');
                                    return;
                                }else if(!array_key_exists('cost', $variant) || empty($variant['cost'])) {
                                    $fail('Variant cost cannot be empty.');
                                    return;
                                }else if(!array_key_exists('price', $variant) || empty($variant['price'])) {
                                    $fail('Variant price cannot be empty.');
                                    return;
                                }
                            }
                        }else{
                            $fail('The variants data is invalid.');
                            return;
                        }

                       

                        //check if variant name empty
                        $names = array_column($variants, 'text');
                        if($names){
                            foreach ($names as $name) {
                                if (empty($name)) {
                                    $fail('Variant Name cannot be empty.');
                                    return;
                                }
                            }
                        }else{
                            $fail('Variant Name cannot be empty.');
                            return;
                        }

                        //check if variant cost empty
                        $all_cost = array_column($variants, 'cost');
                        if($all_cost){
                            foreach ($all_cost as $cost) {
                                if (empty($cost)) {
                                    $fail('Variant Cost cannot be empty.');
                                    return;
                                }
                            }
                        }else{
                            $fail('Variant Cost cannot be empty.');
                            return;
                        }

                        //check if variant price empty
                        $all_price = array_column($variants, 'price');
                        if($all_price){
                            foreach ($all_price as $price) {
                                if (empty($price)) {
                                    $fail('Variant Price cannot be empty.');
                                    return;
                                }
                            }
                        }else{
                            $fail('Variant Price cannot be empty.');
                            return;
                        }

                        //check if code empty
                        $codes = array_column($variants, 'code');
                        if($codes){
                            foreach ($codes as $code) {
                                if (empty($code)) {
                                    $fail('Variant code cannot be empty.');
                                    return;
                                }
                            }
                        }else{
                            $fail('Variant code cannot be empty.');
                            return;
                        }

                        //check if code Duplicate
                        if (count(array_unique($codes)) !== count($codes)) {
                            $fail('Duplicate codes found in variants array.');
                            return;
                        }

                        // check for duplicate codes in product_variants table
                        $duplicateCodes = DB::table('product_variants')
                            ->whereIn('code', $codes)
                            ->whereNull('deleted_at')
                            ->pluck('code')
                            ->toArray();
                        if (!empty($duplicateCodes)) {
                            $fail('This code : '.implode(', ', $duplicateCodes).' already used');
                        }

                        // check for duplicate codes in products table
                        $duplicateCodes_products = DB::table('products')
                            ->whereIn('code', $codes)
                            ->whereNull('deleted_at')
                            ->pluck('code')
                            ->toArray();
                        if (!empty($duplicateCodes_products)) {
                            $fail('This code : '.implode(', ', $duplicateCodes_products).' already used');
                        }
                    },
                ];
            }



            // validate the request data
            $validatedData = $request->validate($productRules, [
                'code.unique'   => 'Product code already used.',
                'code.required' => 'This field is required',
            ]);


            \DB::transaction(function () use ($request) {

                //-- Create New Product
                $Product = new Product;

                //-- Field Required
                $Product->type         = $request['type'];
                $Product->name         = $request['name'];
                $Product->code         = $request['code'];
                $Product->Type_barcode = $request['Type_barcode'];
                $Product->category_id  = $request['category_id'];
                $Product->brand_id     = $request['brand_id'];
                $Product->note         = $request['note'];
                $Product->TaxNet       = $request['TaxNet'] ? $request['TaxNet'] : 0;
                $Product->tax_method   = $request['tax_method'];


                 //-- check if type is_single
                 if($request['type'] == 'is_single'){
                    $Product->price = $request['price'];
                    $Product->cost  = $request['cost'];

                    $Product->unit_id = $request['unit_id'];
                    $Product->unit_sale_id = $request['unit_sale_id'] ? $request['unit_sale_id'] : $request['unit_id'];
                    $Product->unit_purchase_id = $request['unit_purchase_id'] ? $request['unit_purchase_id'] : $request['unit_id'];


                    $Product->stock_alert = $request['stock_alert'] ? $request['stock_alert'] : 0;

                    $manage_stock = 1;

                //-- check if type is_variant
                }elseif($request['type'] == 'is_variant'){

                    $Product->price = 0;
                    $Product->cost  = 0;

                    $Product->unit_id = $request['unit_id'];
                    $Product->unit_sale_id = $request['unit_sale_id'] ? $request['unit_sale_id'] : $request['unit_id'];
                    $Product->unit_purchase_id = $request['unit_purchase_id'] ? $request['unit_purchase_id'] : $request['unit_id'];

                    $Product->stock_alert = $request['stock_alert'] ? $request['stock_alert'] : 0;

                    $manage_stock = 1;

                //-- check if type is_service
                }else{
                    $Product->price = $request['price'];
                    $Product->cost  = 0;

                    $Product->unit_id = NULL;
                    $Product->unit_sale_id = NULL;
                    $Product->unit_purchase_id = NULL;

                    $Product->stock_alert = 0;

                    $manage_stock = 0;

                }
                
                $Product->is_variant = $request['is_variant'] == 'true' ? 1 : 0;
                $Product->is_imei = $request['is_imei'] == 'true' ? 1 : 0;
                $Product->not_selling = $request['not_selling'] == 'true' ? 1 : 0;

                if ($request['images']) {
                    $files = $request['images'];
                    foreach ($files as $file) {
                        $fileData = ImageResize::createFromString(base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $file['path'])));
                        $fileData->resize(200, 200);
                        $name = rand(11111111, 99999999) . $file['name'];
                        $path = public_path() . '/images/products/';
                        $success = file_put_contents($path . $name, $fileData);
                        $images[] = $name;
                    }
                    $filename = implode(",", $images);
                } else {
                    $filename = 'no-image.png';
                }

                $Product->image = $filename;
                $Product->save();

               // Store Variants Product
               if ($request['type'] == 'is_variant') {
                    $variants = json_decode($request->variants);

                    foreach ($variants as $variant) {
                        $Product_variants_data[] = [
                            'product_id' => $Product->id,
                            'name'  => $variant->text,
                            'cost'  => $variant->cost,
                            'price' => $variant->price,
                            'code'  => $variant->code,
                        ];
                    }
                    ProductVariant::insert($Product_variants_data);
                }

                //--Store Product Warehouse
                $warehouses = Warehouse::where('deleted_at', null)->pluck('id')->toArray();
                if ($warehouses) {
                    $Product_variants = ProductVariant::where('product_id', $Product->id)
                        ->where('deleted_at', null)
                        ->get();
                    foreach ($warehouses as $warehouse) {
                        if ($request['is_variant'] == 'true') {
                            foreach ($Product_variants as $product_variant) {

                                $product_warehouse[] = [
                                    'product_id'         => $Product->id,
                                    'warehouse_id'       => $warehouse,
                                    'product_variant_id' => $product_variant->id,
                                    'manage_stock'       => $manage_stock,
                                ];
                            }
                        } else {
                            $product_warehouse[] = [
                                'product_id'   => $Product->id,
                                'warehouse_id' => $warehouse,
                                'manage_stock' => $manage_stock,
                            ];
                        }
                    }
                    product_warehouse::insert($product_warehouse);
                }

            }, 10);

            return response()->json(['success' => true]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 422,
                'msg' => 'error',
                'errors' => $e->errors(),
            ], 422);
        }

    }

    //-------------- Update Product  ---------------\\
    //-----------------------------------------------\\

    public function update(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'update', Product::class);
        try {
            
             // define validation rules for product
             $productRules = [
                'code'         => [
                    'required',

                    Rule::unique('products')->ignore($id)->where(function ($query) {
                        return $query->where('deleted_at', '=', null);
                    }),

                    Rule::unique('product_variants')->ignore($id, 'product_id')->where(function ($query) {
                        return $query->where('deleted_at', '=', null);
                    }),
                ],
                'name'        => 'required',
                'category_id' => 'required',
                'tax_method'  => 'required',
                'type'        => 'required',
                'unit_id'     => Rule::requiredIf($request->type != 'is_service'),
                'cost'        => Rule::requiredIf($request->type == 'is_single'),
                'price'       => Rule::requiredIf($request->type != 'is_variant'),
            ];



           // if type is not is_variant, add validation for variants array
            if ($request->type == 'is_variant') {
                $productRules['variants'] = [
                    'required',
                    function ($attribute, $value, $fail) use ($request, $id) {
                        // check if array is not empty
                        if (empty($value)) {
                            $fail('The variants array is required.');
                            return;
                        }
                        // check for duplicate codes in variants array
                        $variants = $request->variants;
                       

                        if($variants){
                            foreach ($variants as $variant) {
                                if (!array_key_exists('text', $variant) || empty($variant['text'])) {
                                    $fail('Variant Name cannot be empty.');
                                    return;
                                }else if(!array_key_exists('code', $variant) || empty($variant['code'])) {
                                    $fail('Variant code cannot be empty.');
                                    return;
                                }else if(!array_key_exists('cost', $variant) || empty($variant['cost'])) {
                                    $fail('Variant cost cannot be empty.');
                                    return;
                                }else if(!array_key_exists('price', $variant) || empty($variant['price'])) {
                                    $fail('Variant price cannot be empty.');
                                    return;
                                }
                            }
                        }else{
                            $fail('The variants data is invalid.');
                            return;
                        }

                        //check if variant name empty
                        $names = array_column($variants, 'text');
                        if($names){
                            foreach ($names as $name) {
                                if (empty($name)) {
                                    $fail('Variant Name cannot be empty.');
                                    return;
                                }
                            }
                        }else{
                            $fail('Variant Name cannot be empty.');
                            return;
                        }

                        //check if variant cost empty
                        $all_cost = array_column($variants, 'cost');
                        if($all_cost){
                            foreach ($all_cost as $cost) {
                                if (empty($cost)) {
                                    $fail('Variant Cost cannot be empty.');
                                    return;
                                }
                            }
                        }else{
                            $fail('Variant Cost cannot be empty.');
                            return;
                        }

                        //check if variant price empty
                        $all_price = array_column($variants, 'price');
                        if($all_price){
                            foreach ($all_price as $price) {
                                if (empty($price)) {
                                    $fail('Variant Price cannot be empty.');
                                    return;
                                }
                            }
                        }else{
                            $fail('Variant Price cannot be empty.');
                            return;
                        }

                        //check if code empty
                        $codes = array_column($variants, 'code');
                        if($codes){
                            foreach ($codes as $code) {
                                if (empty($code)) {
                                    $fail('Variant code cannot be empty.');
                                    return;
                                }
                            }
                        }else{
                            $fail('Variant code cannot be empty.');
                            return;
                        }

                        //check if code Duplicate
                        if (count(array_unique($codes)) !== count($codes)) {
                            $fail('Duplicate codes found in variants array.');
                            return;
                        }

                        
                        // check for duplicate codes in product_variants table
                        $duplicateCodes = DB::table('product_variants')
                            ->where(function ($query) use ($id) {
                                $query->where('product_id', '<>', $id);
                            })
                            ->whereIn('code', $codes)
                            ->whereNull('deleted_at')
                            ->pluck('code')
                            ->toArray();
                        if (!empty($duplicateCodes)) {
                            $fail('This code : '.implode(', ', $duplicateCodes).' already used');
                        }

                        // check for duplicate codes in products table
                        $duplicateCodes_products = DB::table('products')
                            ->where('id', '!=', $id)
                            ->whereIn('code', $codes)
                            ->whereNull('deleted_at')
                            ->pluck('code')
                            ->toArray();
                        if (!empty($duplicateCodes_products)) {
                            $fail('This code : '.implode(', ', $duplicateCodes_products).' already used');
                        }
                    },
                ];
            }



            // validate the request data
            $validatedData = $request->validate($productRules, [
                'code.unique'   => 'Product code already used.',
                'code.required' => 'This field is required',
            ]);









            \DB::transaction(function () use ($request, $id) {

                $Product = Product::where('id', $id)
                    ->where('deleted_at', '=', null)
                    ->first();

                //-- Update Product
                $Product->type = $request['type'];
                $Product->name = $request['name'];
                $Product->code = $request['code'];
                $Product->Type_barcode = $request['Type_barcode'];
                $Product->category_id = $request['category_id'];
                $Product->brand_id = $request['brand_id'] == 'null' ?Null: $request['brand_id'];
                $Product->TaxNet = $request['TaxNet'];
                $Product->tax_method = $request['tax_method'];
                $Product->note = $request['note'];

                 //-- check if type is_single
                 if($request['type'] == 'is_single'){
                    $Product->price = $request['price'];
                    $Product->cost  = $request['cost'];

                    $Product->unit_id = $request['unit_id'];
                    $Product->unit_sale_id = $request['unit_sale_id'] ? $request['unit_sale_id'] : $request['unit_id'];
                    $Product->unit_purchase_id = $request['unit_purchase_id'] ? $request['unit_purchase_id'] : $request['unit_id'];


                    $Product->stock_alert = $request['stock_alert'] ? $request['stock_alert'] : 0;
                    $Product->is_variant = 0;

                    $manage_stock = 1;

                //-- check if type is_variant
                }elseif($request['type'] == 'is_variant'){

                    $Product->price = 0;
                    $Product->cost  = 0;

                    $Product->unit_id = $request['unit_id'];
                    $Product->unit_sale_id = $request['unit_sale_id'] ? $request['unit_sale_id'] : $request['unit_id'];
                    $Product->unit_purchase_id = $request['unit_purchase_id'] ? $request['unit_purchase_id'] : $request['unit_id'];

                    $Product->stock_alert = $request['stock_alert'] ? $request['stock_alert'] : 0;
                    $Product->is_variant = 1;
                    $manage_stock = 1;

                //-- check if type is_service
                }else{
                    $Product->price = $request['price'];
                    $Product->cost  = 0;

                    $Product->unit_id = NULL;
                    $Product->unit_sale_id = NULL;
                    $Product->unit_purchase_id = NULL;

                    $Product->stock_alert = 0;
                    $Product->is_variant = 0;
                    $manage_stock = 0;

                }


            
                $Product->is_imei = $request['is_imei'] == 'true' ? 1 : 0;
                $Product->not_selling = $request['not_selling'] == 'true' ? 1 : 0;
                
                // Store Variants Product
                $oldVariants = ProductVariant::where('product_id', $id)
                    ->where('deleted_at', null)
                    ->get();

                $warehouses = Warehouse::where('deleted_at', null)
                    ->pluck('id')
                    ->toArray();


                if ($request['type'] == 'is_variant') {

                    if ($oldVariants->isNotEmpty()) {
                        $new_variants_id = [];
                        $var = 'id';

                        foreach ($request['variants'] as $new_id) {
                            if (array_key_exists($var, $new_id)) {
                                $new_variants_id[] = $new_id['id'];
                            } else {
                                $new_variants_id[] = 0;
                            }
                        }

                        foreach ($oldVariants as $key => $value) {
                            $old_variants_id[] = $value->id;

                            // Delete Variant
                            if (!in_array($old_variants_id[$key], $new_variants_id)) {
                                $ProductVariant = ProductVariant::findOrFail($value->id);
                                $ProductVariant->deleted_at = Carbon::now();
                                $ProductVariant->save();

                                $ProductWarehouse = product_warehouse::where('product_variant_id', $value->id)
                                    ->update(['deleted_at' => Carbon::now()]);
                            }
                        }

                        foreach ($request['variants'] as $key => $variant) {
                            if (array_key_exists($var, $variant)) {

                                $ProductVariantDT = new ProductVariant;
                                //-- Field Required
                                $ProductVariantDT->product_id = $variant['product_id'];
                                $ProductVariantDT->name = $variant['text'];
                                $ProductVariantDT->price = $variant['price'];
                                $ProductVariantDT->cost = $variant['cost'];
                                $ProductVariantDT->code = $variant['code'];

                                $ProductVariantUP['product_id'] = $variant['product_id'];
                                $ProductVariantUP['code'] = $variant['code'];
                                $ProductVariantUP['name'] = $variant['text'];
                                $ProductVariantUP['price'] = $variant['price'];
                                $ProductVariantUP['cost'] = $variant['cost'];

                            } else {
                                $ProductVariantDT = new ProductVariant;

                                 //-- Field Required
                                 $ProductVariantDT->product_id = $id;
                                 $ProductVariantDT->code = $variant['code'];
                                 $ProductVariantDT->name = $variant['text'];
                                 $ProductVariantDT->price = $variant['price'];
                                 $ProductVariantDT->cost = $variant['cost'];

                                 $ProductVariantUP['product_id'] = $id;
                                 $ProductVariantUP['code'] = $variant['code'];
                                 $ProductVariantUP['name'] = $variant['text'];
                                 $ProductVariantUP['price'] = $variant['price'];
                                 $ProductVariantUP['cost'] = $variant['cost'];
                                 $ProductVariantUP['qty'] = 0.00;
                            }

                            if (!in_array($new_variants_id[$key], $old_variants_id)) {
                                $ProductVariantDT->save();

                                //--Store Product warehouse
                                if ($warehouses) {
                                    $product_warehouse= [];
                                    foreach ($warehouses as $warehouse) {

                                        $product_warehouse[] = [
                                            'product_id'         => $id,
                                            'warehouse_id'       => $warehouse,
                                            'product_variant_id' => $ProductVariantDT->id,
                                            'manage_stock'       => $manage_stock,
                                        ];

                                    }
                                    product_warehouse::insert($product_warehouse);
                                }
                            } else {
                                ProductVariant::where('id', $variant['id'])->update($ProductVariantUP);
                            }
                        }

                    } else {
                        $ProducttWarehouse = product_warehouse::where('product_id', $id)
                            ->update([
                                'deleted_at' => Carbon::now(),
                            ]);

                        foreach ($request['variants'] as $variant) {
                            $product_warehouse_DT = [];
                            $ProductVarDT = new ProductVariant;

                           //-- Field Required
                           $ProductVarDT->product_id = $id;
                           $ProductVarDT->code = $variant['code'];
                           $ProductVarDT->name = $variant['text'];
                           $ProductVarDT->cost = $variant['cost'];
                           $ProductVarDT->price = $variant['price'];
                           $ProductVarDT->save();


                            //-- Store Product warehouse
                            if ($warehouses) {
                                foreach ($warehouses as $warehouse) {

                                    $product_warehouse_DT[] = [
                                        'product_id'         => $id,
                                        'warehouse_id'       => $warehouse,
                                        'product_variant_id' => $ProductVarDT->id,
                                        'manage_stock'       => $manage_stock,
                                    ];
                                }

                                product_warehouse::insert($product_warehouse_DT);
                            }
                        }

                    }
                } else {
                    if ($oldVariants->isNotEmpty()) {
                        foreach ($oldVariants as $old_var) {
                            $var_old = ProductVariant::where('product_id', $old_var['product_id'])
                                ->where('deleted_at', null)
                                ->first();
                            $var_old->deleted_at = Carbon::now();
                            $var_old->save();

                            $ProducttWarehouse = product_warehouse::where('product_variant_id', $old_var['id'])
                                ->update([
                                    'deleted_at' => Carbon::now(),
                                ]);
                        }

                        if ($warehouses) {
                            foreach ($warehouses as $warehouse) {

                                $product_warehouse[] = [
                                    'product_id'         => $id,
                                    'warehouse_id'       => $warehouse,
                                    'product_variant_id' => null,
                                    'manage_stock'       => $manage_stock,
                                ];

                            }
                            product_warehouse::insert($product_warehouse);
                        }
                    }
                }

                if ($request['images'] === null) {

                    if ($Product->image !== null) {
                        foreach (explode(',', $Product->image) as $img) {
                            $pathIMG = public_path() . '/images/products/' . $img;
                            if (file_exists($pathIMG)) {
                                if ($img != 'no-image.png') {
                                    @unlink($pathIMG);
                                }
                            }
                        }
                    }
                    $filename = 'no-image.png';
                } else {
                    if ($Product->image !== null) {
                        foreach (explode(',', $Product->image) as $img) {
                            $pathIMG = public_path() . '/images/products/' . $img;
                            if (file_exists($pathIMG)) {
                                if ($img != 'no-image.png') {
                                    @unlink($pathIMG);
                                }
                            }
                        }
                    }
                    $files = $request['images'];
                    foreach ($files as $file) {
                        $fileData = ImageResize::createFromString(base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $file['path'])));
                        $fileData->resize(200, 200);
                        $name = rand(11111111, 99999999) . $file['name'];
                        $path = public_path() . '/images/products/';
                        $success = file_put_contents($path . $name, $fileData);
                        $images[] = $name;
                    }
                    $filename = implode(",", $images);
                }

                $Product->image = $filename;
                $Product->save();

            }, 10);

            return response()->json(['success' => true]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => 422,
                'msg' => 'error',
                'errors' => $e->errors(),
            ], 422);
        }

    }

    //-------------- Remove Product  ---------------\\
    //-----------------------------------------------\\

    public function destroy(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Product::class);

        \DB::transaction(function () use ($id) {

            $Product = Product::findOrFail($id);
            $Product->deleted_at = Carbon::now();
            $Product->save();

            foreach (explode(',', $Product->image) as $img) {
                $pathIMG = public_path() . '/images/products/' . $img;
                if (file_exists($pathIMG)) {
                    if ($img != 'no-image.png') {
                        @unlink($pathIMG);
                    }
                }
            }

            product_warehouse::where('product_id', $id)->update([
                'deleted_at' => Carbon::now(),
            ]);

            ProductVariant::where('product_id', $id)->update([
                'deleted_at' => Carbon::now(),
            ]);

        }, 10);

        return response()->json(['success' => true]);

    }

    //-------------- Delete by selection  ---------------\\

    public function delete_by_selection(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'delete', Product::class);

        \DB::transaction(function () use ($request) {
            $selectedIds = $request->selectedIds;
            foreach ($selectedIds as $product_id) {

                $Product = Product::findOrFail($product_id);
                $Product->deleted_at = Carbon::now();
                $Product->save();

                foreach (explode(',', $Product->image) as $img) {
                    $pathIMG = public_path() . '/images/products/' . $img;
                    if (file_exists($pathIMG)) {
                        if ($img != 'no-image.png') {
                            @unlink($pathIMG);
                        }
                    }
                }

                product_warehouse::where('product_id', $product_id)->update([
                    'deleted_at' => Carbon::now(),
                ]);

                ProductVariant::where('product_id', $product_id)->update([
                    'deleted_at' => Carbon::now(),
                ]);
            }

        }, 10);

        return response()->json(['success' => true]);

    }

   
    //--------------  Show Product Details ---------------\\

    public function Get_Products_Details(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'view', Product::class);

        $Product = Product::where('deleted_at', '=', null)->findOrFail($id);
        //get warehouses assigned to user
        $user_auth = auth()->user();
        if($user_auth->is_all_warehouses){
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
        }else{
            $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
            $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
        }

        $item['id'] = $Product->id;
        $item['type'] = $Product->type;
        $item['code'] = $Product->code;
        $item['Type_barcode'] = $Product->Type_barcode;
        $item['name'] = $Product->name;
        $item['note'] = $Product->note;
        $item['category'] = $Product['category']->name;
        $item['brand'] = $Product['brand'] ? $Product['brand']->name : 'N/D';
        $item['price'] = $Product->price;
        $item['cost'] = $Product->cost;
        $item['stock_alert'] = $Product->stock_alert;
        $item['taxe'] = $Product->TaxNet;
        $item['tax_method'] = $Product->tax_method == '1' ? 'Exclusive' : 'Inclusive';

        if($Product->type == 'is_single'){
            $item['type_name']  = 'Single';
            $item['unit'] = $Product['unit']->ShortName;

        }elseif($Product->type == 'is_variant'){
            $item['type_name'] = 'Variable';
            $item['unit'] = $Product['unit']->ShortName;

        }else{
            $item['type_name'] = 'Service';
            $item['unit'] = '----';

        }

        if ($Product->is_variant) {
            $item['is_variant'] = 'yes';
            $productsVariants = ProductVariant::where('product_id', $id)
                ->where('deleted_at', null)
                ->get();
            foreach ($productsVariants as $variant) {
                $ProductVariant['code'] = $variant->code;
                $ProductVariant['name'] = $variant->name;
                $ProductVariant['cost'] = number_format($variant->cost, 2, '.', ',');
                $ProductVariant['price'] = number_format($variant->price, 2, '.', ',');

                $item['products_variants_data'][] = $ProductVariant;

                foreach ($warehouses as $warehouse) {
                    $product_warehouse = DB::table('product_warehouse')
                        ->where('product_id', $id)
                        ->where('deleted_at', '=', null)
                        ->where('warehouse_id', $warehouse->id)
                        ->where('product_variant_id', $variant->id)
                        ->select(DB::raw('SUM(product_warehouse.qte) AS sum'))
                        ->first();

                    $war_var['mag'] = $warehouse->name;
                    $war_var['variant'] = $variant->name;
                    $war_var['qte'] = $product_warehouse->sum;
                    $item['CountQTY_variants'][] = $war_var;
                }

            }
        } else {
            $item['is_variant'] = 'no';
            $item['CountQTY_variants'] = [];
        }

        foreach ($warehouses as $warehouse) {
            $product_warehouse_data = DB::table('product_warehouse')
                ->where('deleted_at', '=', null)
                ->where('product_id', $id)
                ->where('warehouse_id', $warehouse->id)
                ->select(DB::raw('SUM(product_warehouse.qte) AS sum'))
                ->first();

            $war['mag'] = $warehouse->name;
            $war['qte'] = $product_warehouse_data->sum;
            $item['CountQTY'][] = $war;
        }

        if ($Product->image != '') {
            foreach (explode(',', $Product->image) as $img) {
                $item['images'][] = $img;
            }
        }

        $data[] = $item;

        return response()->json($data[0]);

    }

    //------------ Get products By Warehouse -----------------\\

    public function Products_by_Warehouse(request $request, $id)
    {
        $data = [];
        $product_warehouse_data = product_warehouse::with('warehouse', 'product', 'productVariant')

        ->where(function ($query) use ($request , $id) {
                return $query->where('warehouse_id', $id)
                    ->where('deleted_at', '=', null)
                    ->where(function ($query) use ($request) {
                        return $query->whereHas('product', function ($q) use ($request) {
                            if ($request->is_sale == '1') {
                                $q->where('not_selling', '=', 0);
                            }
                        });
                    })
                    ->where(function ($query) use ($request) {
                        if ($request->stock == '1' && $request->product_service == '1') {
                            return $query->where('qte', '>', 0)->orWhere('manage_stock', false);
        
                        }elseif($request->stock == '1' && $request->product_service == '0') {
                            return $query->where('qte', '>', 0)->orWhere('manage_stock', true);
        
                        }else{
                            return $query->where('manage_stock', true);
                        }
                    });
        })->get();

        foreach ($product_warehouse_data as $product_warehouse) {

            if ($product_warehouse->product_variant_id) {
                $item['product_variant_id'] = $product_warehouse->product_variant_id;

                $item['code'] = $product_warehouse['productVariant']->code;
                $item['Variant'] = '['.$product_warehouse['productVariant']->name . ']' . $product_warehouse['product']->name;
                $item['name'] = '['.$product_warehouse['productVariant']->name . ']' . $product_warehouse['product']->name;
                $item['barcode'] = $product_warehouse['productVariant']->code;


                $product_price = $product_warehouse['productVariant']->price;

            } else {
                $item['product_variant_id'] = null;
                $item['Variant'] = null;
                $item['code'] = $product_warehouse['product']->code;
                $item['name'] = $product_warehouse['product']->name;
                $item['barcode'] = $product_warehouse['product']->code;

                $product_price =  $product_warehouse['product']->price;
            }

            $item['id'] = $product_warehouse->product_id;
            $item['product_type'] = $product_warehouse['product']->type;
            $item['Type_barcode'] = $product_warehouse['product']->Type_barcode;
            $firstimage = explode(',', $product_warehouse['product']->image);
            $item['image'] = $firstimage[0];

            if($product_warehouse['product']['unitSale']){

                if ($product_warehouse['product']['unitSale']->operator == '/') {
                    $item['qte_sale'] = $product_warehouse->qte * $product_warehouse['product']['unitSale']->operator_value;
                    $price = $product_price / $product_warehouse['product']['unitSale']->operator_value;
                } else {
                    $item['qte_sale'] = $product_warehouse->qte / $product_warehouse['product']['unitSale']->operator_value;
                    $price = $product_price * $product_warehouse['product']['unitSale']->operator_value;
                }

                
            }else{
                $item['qte_sale'] = $product_warehouse['product']->type!='is_service'?$product_warehouse->qte:'---';
                $price = $product_price;
            }

            if($product_warehouse['product']['unitPurchase']) {

                if ($product_warehouse['product']['unitPurchase']->operator == '/') {
                    $item['qte_purchase'] = round($product_warehouse->qte * $product_warehouse['product']['unitPurchase']->operator_value, 5);
                } else {
                    $item['qte_purchase'] = round($product_warehouse->qte / $product_warehouse['product']['unitPurchase']->operator_value, 5);
                }

            }else{
                $item['qte_purchase'] = $product_warehouse->qte;
            }

            $item['manage_stock'] = $product_warehouse->manage_stock;
            $item['qte'] = $product_warehouse['product']->type!='is_service'?$product_warehouse->qte:'---';
            $item['unitSale'] = $product_warehouse['product']['unitSale']?$product_warehouse['product']['unitSale']->ShortName:'';
            $item['unitPurchase'] = $product_warehouse['product']['unitPurchase']?$product_warehouse['product']['unitPurchase']->ShortName:'';

            if ($product_warehouse['product']->TaxNet !== 0.0) {
                //Exclusive
                if ($product_warehouse['product']->tax_method == '1') {
                    $tax_price = $price * $product_warehouse['product']->TaxNet / 100;
                    $item['Net_price'] = $price + $tax_price;
                    // Inxclusive
                } else {
                    $item['Net_price'] = $price;
                }
            } else {
                $item['Net_price'] = $price;
            }

            $data[] = $item;
        }

        return response()->json($data);
    }

    
    public function show($id)
    {
        //
    }
    
    
    //------------ Get product By ID -----------------\\
    public function show_product_data($id , $variant_id)
    {

        $Product_data = Product::with('unit')
            ->where('id', $id)
            ->where('deleted_at', '=', null)
            ->first();

        $data = [];
        $item['id']           = $Product_data['id'];
        $item['image']        = $Product_data['image'];
        $item['product_type'] = $Product_data['type'];
        $item['Type_barcode'] = $Product_data['Type_barcode'];

        $item['unit_id'] = $Product_data['unit']?$Product_data['unit']->id:'';
        $item['unit']    = $Product_data['unit']?$Product_data['unit']->ShortName:'';

        $item['purchase_unit_id'] = $Product_data['unitPurchase']?$Product_data['unitPurchase']->id:'';
        $item['unitPurchase']     = $Product_data['unitPurchase']?$Product_data['unitPurchase']->ShortName:'';

        $item['sale_unit_id'] = $Product_data['unitSale']?$Product_data['unitSale']->id:'';
        $item['unitSale']     = $Product_data['unitSale']?$Product_data['unitSale']->ShortName:'';

        $item['tax_method']  = $Product_data['tax_method'];
        $item['tax_percent'] = $Product_data['TaxNet'];

        $item['is_imei']     = $Product_data['is_imei'];
        $item['not_selling'] = $Product_data['not_selling'];

        //product single
        if($Product_data['type'] == 'is_single'){
            $product_price = $Product_data['price'];
            $product_cost  = $Product_data['cost'];

            $item['code'] = $Product_data['code'];
            $item['name'] = $Product_data['name'];

        //product is_variant
        }elseif($Product_data['type'] == 'is_variant'){

            $product_variant_data = ProductVariant::where('product_id', $id)
            ->where('id', $variant_id)->first();

            $product_price = $product_variant_data['price'];
            $product_cost  = $product_variant_data['cost'];
            $item['code'] = $product_variant_data['code'];
            $item['name'] = '['.$product_variant_data['name'].']'.$Product_data['name'];

         //product is_service
        }else{

            $product_price = $Product_data['price'];
            $product_cost  = 0;

            $item['code'] = $Product_data['code'];
            $item['name'] = $Product_data['name'];
        }

       
        //check if product has Unit sale
        if ($Product_data['unitSale']) {

            if ($Product_data['unitSale']->operator == '/') {
                $price = $product_price / $Product_data['unitSale']->operator_value;

            } else {
                $price = $product_price * $Product_data['unitSale']->operator_value;
            }

        }else{
            $price = $product_price;
        }

        //check if product has Unit Purchase

        if ($Product_data['unitPurchase']) {

            if ($Product_data['unitPurchase']->operator == '/') {
                $cost = $product_cost / $Product_data['unitPurchase']->operator_value;
            } else {
                $cost = $product_cost * $Product_data['unitPurchase']->operator_value;
            }

        }else{
            $cost = 0;
        }

        $item['Unit_cost'] = $cost;
        $item['fix_cost'] = $product_cost;
        $item['Unit_price'] = $price;
        $item['fix_price'] = $product_price;

        if ($Product_data->TaxNet !== 0.0) {
            //Exclusive
            if ($Product_data['tax_method'] == '1') {
                $tax_price = $price * $Product_data['TaxNet'] / 100;
                $tax_cost = $cost * $Product_data['TaxNet'] / 100;

                $item['Total_cost'] = $cost + $tax_cost;
                $item['Total_price'] = $price + $tax_price;
                $item['Net_cost'] = $cost;
                $item['Net_price'] = $price;
                $item['tax_price'] = $tax_price;
                $item['tax_cost'] = $tax_cost;

                // Inxclusive
            } else {
                $item['Total_cost'] = $cost;
                $item['Total_price'] = $price;
                $item['Net_cost'] = $cost / (($Product_data['TaxNet'] / 100) + 1);
                $item['Net_price'] = $price / (($Product_data['TaxNet'] / 100) + 1);
                $item['tax_cost'] = $item['Total_cost'] - $item['Net_cost'];
                $item['tax_price'] = $item['Total_price'] - $item['Net_price'];
            }
        } else {
            $item['Total_cost'] = $cost;
            $item['Total_price'] = $price;
            $item['Net_cost'] = $cost;
            $item['Net_price'] = $price;
            $item['tax_price'] = 0;
            $item['tax_cost'] = 0;
        }

        $data[] = $item;

        return response()->json($data[0]);
    }

    //--------------  Product Quantity Alerts ---------------\\

    public function Products_Alert(request $request)
    {
        $this->authorizeForUser($request->user('api'), 'Stock_Alerts', Product::class);

        $product_warehouse_data = product_warehouse::with('warehouse', 'product', 'productVariant')
            ->join('products', 'product_warehouse.product_id', '=', 'products.id')
            ->where('manage_stock', true)
            ->whereRaw('qte <= stock_alert')
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('warehouse'), function ($query) use ($request) {
                    return $query->where('warehouse_id', $request->warehouse);
                });
            })->where('product_warehouse.deleted_at', null)->get();

        $data = [];

        if ($product_warehouse_data->isNotEmpty()) {

            foreach ($product_warehouse_data as $product_warehouse) {
                if ($product_warehouse->qte <= $product_warehouse['product']->stock_alert) {
                    if ($product_warehouse->product_variant_id !== null) {
                        $item['code'] = $product_warehouse['productVariant']->code ;
                        $item['name'] = '['.$product_warehouse['productVariant']->name . ']' . $product_warehouse['product']->name;
                    } else {
                        $item['code'] = $product_warehouse['product']->code;
                        $item['name'] = $product_warehouse['product']->name;
                    }
                    $item['quantity'] = $product_warehouse->qte;
                    $item['warehouse'] = $product_warehouse['warehouse']->name;
                    $item['stock_alert'] = $product_warehouse['product']->stock_alert;
                    $data[] = $item;
                }
            }
        }

        $perPage = $request->limit; // How many items do you want to display.
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $collection = collect($data);
        // Get only the items you need using array_slice
        $data_collection = $collection->slice($offSet, $perPage)->values();

        $products = new LengthAwarePaginator($data_collection, count($data), $perPage, Paginator::resolveCurrentPage(), array('path' => Paginator::resolveCurrentPath()));
       
         //get warehouses assigned to user
         $user_auth = auth()->user();
         if($user_auth->is_all_warehouses){
             $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
         }else{
             $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
             $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
         }
 
        return response()->json([
            'products' => $products,
            'warehouses' => $warehouses,
        ]);
    }

    //---------------- Show Form Create Product ---------------\\

    public function create(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'create', Product::class);

        $categories = Category::where('deleted_at', null)->get(['id', 'name']);
        $brands = Brand::where('deleted_at', null)->get(['id', 'name']);
        $units = Unit::where('deleted_at', null)->where('base_unit', null)->get();
        return response()->json([
            'categories' => $categories,
            'brands' => $brands,
            'units' => $units,
        ]);

    }

    //---------------- Show Elements Barcode ---------------\\

    public function Get_element_barcode(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'barcode', Product::class);

         //get warehouses assigned to user
         $user_auth = auth()->user();
         if($user_auth->is_all_warehouses){
             $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name']);
         }else{
             $warehouses_id = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
             $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $warehouses_id)->get(['id', 'name']);
         }
        
        return response()->json(['warehouses' => $warehouses]);

    }

    //---------------- Show Form Edit Product ---------------\\

    public function edit(Request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'update', Product::class);

        $Product = Product::where('deleted_at', '=', null)->findOrFail($id);

        $item['id'] = $Product->id;
        $item['type'] = $Product->type;
        $item['code'] = $Product->code;
        $item['Type_barcode'] = $Product->Type_barcode;
        $item['name'] = $Product->name;
        if ($Product->category_id) {
            if (Category::where('id', $Product->category_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $item['category_id'] = $Product->category_id;
            } else {
                $item['category_id'] = '';
            }
        } else {
            $item['category_id'] = '';
        }

        if ($Product->brand_id) {
            if (Brand::where('id', $Product->brand_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $item['brand_id'] = $Product->brand_id;
            } else {
                $item['brand_id'] = '';
            }
        } else {
            $item['brand_id'] = '';
        }

        if ($Product->unit_id) {
            if (Unit::where('id', $Product->unit_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $item['unit_id'] = $Product->unit_id;
            } else {
                $item['unit_id'] = '';
            }

            if (Unit::where('id', $Product->unit_sale_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $item['unit_sale_id'] = $Product->unit_sale_id;
            } else {
                $item['unit_sale_id'] = '';
            }

            if (Unit::where('id', $Product->unit_purchase_id)
                ->where('deleted_at', '=', null)
                ->first()) {
                $item['unit_purchase_id'] = $Product->unit_purchase_id;
            } else {
                $item['unit_purchase_id'] = '';
            }

        } else {
            $item['unit_id'] = '';
        }

        $item['tax_method'] = $Product->tax_method;
        $item['price'] = $Product->price;
        $item['cost'] = $Product->cost;
        $item['stock_alert'] = $Product->stock_alert;
        $item['TaxNet'] = $Product->TaxNet;
        $item['note'] = $Product->note ? $Product->note : '';
        $item['images'] = [];
        if ($Product->image != '' && $Product->image != 'no-image.png') {
            foreach (explode(',', $Product->image) as $img) {
                $path = public_path() . '/images/products/' . $img;
                if (file_exists($path)) {
                    $itemImg['name'] = $img;
                    $type = pathinfo($path, PATHINFO_EXTENSION);
                    $data = file_get_contents($path);
                    $itemImg['path'] = 'data:image/' . $type . ';base64,' . base64_encode($data);

                    $item['images'][] = $itemImg;
                }
            }
        } else {
            $item['images'] = [];
        }

        if ($Product->type == 'is_variant') {
            $item['is_variant'] = true;
            $productsVariants = ProductVariant::where('product_id', $id)
                ->where('deleted_at', null)
                ->get();

            $var_id = 0;
            foreach ($productsVariants as $variant) {
                $variant_item['var_id'] = $var_id += 1;
                $variant_item['id'] = $variant->id;
                $variant_item['text'] = $variant->name;
                $variant_item['code'] = $variant->code;
                $variant_item['price'] = $variant->price;
                $variant_item['cost'] = $variant->cost;
                $variant_item['product_id'] = $variant->product_id;
                $item['ProductVariant'][] = $variant_item;
            }
        } else {
            $item['is_variant'] = false;
            $item['ProductVariant'] = [];
        }

        $item['is_imei'] = $Product->is_imei?true:false;
        $item['not_selling'] = $Product->not_selling?true:false;

        $data = $item;
        $categories = Category::where('deleted_at', null)->get(['id', 'name']);
        $brands = Brand::where('deleted_at', null)->get(['id', 'name']);

        $product_units = Unit::where('id', $Product->unit_id)
                              ->orWhere('base_unit', $Product->unit_id)
                              ->where('deleted_at', null)
                              ->get();

      
        $units = Unit::where('deleted_at', null)
            ->where('base_unit', null)
            ->get();

        return response()->json([
            'product' => $data,
            'categories' => $categories,
            'brands' => $brands,
            'units' => $units,
            'units_sub' => $product_units,
        ]);

    }

    // import Products
    public function import_products(Request $request)
    {
       
        $file_upload = $request->file('products');
        $ext = pathinfo($file_upload->getClientOriginalName(), PATHINFO_EXTENSION);
        if ($ext != 'csv') {
            return response()->json([
                'msg' => 'must be in csv format',
                'status' => false,
            ]);
        } else {
            $data = array();
            $rowcount = 0;
            if (($handle = fopen($file_upload, "r")) !== false) {

                $max_line_length = defined('MAX_LINE_LENGTH') ? MAX_LINE_LENGTH : 10000;
                $header = fgetcsv($handle, $max_line_length);
                $header_colcount = count($header);
                while (($row = fgetcsv($handle, $max_line_length)) !== false) {
                    $row_colcount = count($row);
                    if ($row_colcount == $header_colcount) {
                        $entry = array_combine($header, $row);
                        $data[] = $entry;
                    } else {
                        return null;
                    }
                    $rowcount++;
                }
                fclose($handle);
            } else {
                return null;
            }


            $warehouses = Warehouse::where('deleted_at', null)->pluck('id')->toArray();

              // Create a new instance of Illuminate\Http\Request and pass the imported data to it.

            $validator = validator()->make($data, [
                '*.name' => 'required',
                '*.code' => 'required|unique:products',
            ]);

            if ($validator->fails()) {
                // Validation failed
                return response()->json([
                    'msg' => 'Validation failed',
                    'errors' => $validator->errors(),
                    'status' => false,
                ]);
            }
          
            try {
                \DB::transaction(function () use ($data , $warehouses) {


                    //-- Create New Product
                    foreach ($data as $key => $value) {
                        $category = Category::where('deleted_at', null)->firstOrCreate(['name' => $value['category']]);
                        $category_id = $category->id;

                        $unit = Unit::where(['ShortName' => $value['unit']])
                            ->orWhere(['name' => $value['unit']])
                            ->where('deleted_at', null)->first();
                        $unit_id = $unit->id;

                        if ($value['brand'] != 'N/A' && $value['brand'] != '') {
                            $brand = Brand::where('deleted_at', null)->firstOrCreate(['name' => $value['brand']]);
                            $brand_id = $brand->id;
                        } else {
                            $brand_id = NULL;
                        }
                        $Product = new Product;
                        $Product->name = $value['name'] == '' ? NULL : $value['name'];
                        $Product->code = $this->check_code_exist($value['code']);
                        $Product->Type_barcode = 'CODE128';
                        $Product->type = 'is_single';
                        $Product->price = $value['price'];
                        $Product->cost = $value['cost'];
                        $Product->category_id = $category_id;
                        $Product->brand_id = $brand_id;
                        $Product->TaxNet = 0;
                        $Product->tax_method = 1;
                        $Product->note = $value['note'] ? $value['note'] : '';
                        $Product->unit_id = $unit_id;
                        $Product->unit_sale_id = $unit_id;
                        $Product->unit_purchase_id = $unit_id;
                        $Product->stock_alert = $value['stock_alert'] ? $value['stock_alert'] : 0;
                        $Product->is_variant = 0;
                        $Product->image = 'no-image.png';
                        $Product->save();

                        if ($warehouses) {
                            foreach ($warehouses as $warehouse) {
                                $product_warehouse[] = [
                                    'product_id' => $Product->id,
                                    'warehouse_id' => $warehouse,
                                ];
                            }
                        }
                    }
                    if ($warehouses) {
                        product_warehouse::insert($product_warehouse);
                    }

                }, 10);

            } catch (\Exception $e) {
                // Return error response
                return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
            }

        }

    
        // Return success response
        return response()->json(['status' => true]);

    }

    // Generate_random_code
    public function generate_random_code($value_code)
    {
        if($value_code == ''){
            $gen_code = substr(number_format(time() * mt_rand(), 0, '', ''), 0, 8);
            $this->check_code_exist($gen_code);
        }else{
            $this->check_code_exist($value_code);
        }
    }


    // check_code_exist
    public function check_code_exist($code)
    {
        $check_code = Product::where('code', $code)->first();
        if ($check_code) {
            $this->generate_random_code($code);
        } else {
            return $code;
        }

    }



}
