<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;
use App\Models\role_user;
use App\Models\product_warehouse;
use App\Models\Warehouse;
use App\Models\UserWarehouse;
use App\Models\Server;
use App\Models\PosSetting;
use App\Models\SMSMessage;
use App\Models\EmailMessage;
use App\Models\Client;
use App\utils\helpers;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Intervention\Image\ImageManagerStatic as Image;
use \Nwidart\Modules\Facades\Module;

class UserController extends BaseController
{

    //------------- GET ALL USERS---------\\

    public function index(request $request)
    {

        $this->authorizeForUser($request->user('api'), 'view', User::class);
        // How many items do you want to display.
        $perPage = $request->limit;
        $pageStart = \Request::get('page', 1);
        // Start displaying items from this number;
        $offSet = ($pageStart * $perPage) - $perPage;
        $order = $request->SortField;
        $dir = $request->SortType;
        $helpers = new helpers();
        // Filter fields With Params to retrieve
        $columns = array(0 => 'username', 1 => 'statut', 2 => 'phone', 3 => 'email');
        $param = array(0 => 'like', 1 => '=', 2 => 'like', 3 => 'like');
        $data = array();

        $Role = Auth::user()->roles()->first();
        $ShowRecord = Role::findOrFail($Role->id)->inRole('record_view');

        $users = User::with('workspace', 'assignedWarehouses', 'roles')
            ->where(function ($query) use ($ShowRecord) {
                if (!$ShowRecord) {
                    return $query->where('id', '=', Auth::user()->id);
                }
                // return only workspace users
                if(Auth::user()->workspace_id){
                    return $query->where('workspace_id', '=', Auth::user()->workspace_id);
                }
            });

        //Multiple Filter
        $Filtred = $helpers->filter($users, $columns, $param, $request)
        // Search With Multiple Param
            ->where(function ($query) use ($request) {
                return $query->when($request->filled('search'), function ($query) use ($request) {
                    return $query->where('username', 'LIKE', "%{$request->search}%")
                        ->orWhere('firstname', 'LIKE', "%{$request->search}%")
                        ->orWhere('lastname', 'LIKE', "%{$request->search}%")
                        ->orWhere('email', 'LIKE', "%{$request->search}%")
                        ->orWhere('phone', 'LIKE', "%{$request->search}%");
                });
            });
        $totalRows = $Filtred->count();
        if($perPage == "-1"){
            $perPage = $totalRows;
        }
        $users = $Filtred->offset($offSet)
            ->limit($perPage)
            ->orderBy($order, $dir)
            ->get();
        // $userCountByWorkspace = User::groupBy('workspace_id')->get();
        $userCountByWorkspace = User::all()
            ->where('workspace_id', '<>', null)
            ->groupBy('workspace_id')
            ->toArray();

            // ->select('workspace_id', DB::raw('count(*) as user_count'));
        
        if(Auth::user()->role_id == 1) {
            $roles = Role::where('deleted_at', null)->get(['id', 'name']);
        } else {
            $roles = Role::where('deleted_at', null)
                ->where('id', '<>', 1)
                ->where(function($query) {
                    if(auth()->user()->workspace_id) {
                        return $query->where('workspace_id', '=', auth()->user()->workspace_id)
                            ->orWhere('id', '=', 2);                
                        }
                })
                ->get(['id', 'name']);
        }
        if(Auth::user()->role_id == 1) {
            $warehouses = Warehouse::where('deleted_at', '=', null)->get(['id', 'name', 'workspace_id']);
        } else {
            $warehouses = Warehouse::with('workspace')
                ->where('deleted_at', '=', null)
                ->where('workspace_id', '=', Auth::user()->workspace_id)
                ->get(['id', 'name', 'workspace_id']);
        }
        

        return response()->json([
            'users' => $users,
            'userCountByWorkspace' => $userCountByWorkspace,
            'roles' => $roles,
            'warehouses' => $warehouses,
            'totalRows' => $totalRows,
        ]);
    }

    //------------- GET USER Auth ---------\\

    public function GetUserAuth(Request $request)
    {
        $helpers = new helpers();
        $user['id'] = Auth::user()->id;
        $user['role_id'] = Auth::user()->role_id;
        $user['avatar'] = Auth::user()->avatar;
        $user['username'] = Auth::user()->username;
        $user['workspace_id'] = Auth::user()->workspace_id;
        $user['currency'] = $helpers->Get_Currency();
        $user['logo'] = Setting::where(function ($query) {
                if(auth()->user()->workspace_id){
                    return $query->where('workspace_id', '=', auth()->user()->workspace_id);
                }
            })->first()->logo;
        $user['default_language'] = Setting::where(function ($query) {
            if(auth()->user()->workspace_id){
                return $query->where('workspace_id', '=', auth()->user()->workspace_id);
            }
        })->first()->default_language;
        $user['footer'] = Setting::where(function ($query) {
            if(auth()->user()->workspace_id){
                return $query->where('workspace_id', '=', auth()->user()->workspace_id);
            }
        })->first()->footer;
        $user['developed_by'] = Setting::where(function ($query) {
            if(auth()->user()->workspace_id){
                return $query->where('workspace_id', '=', auth()->user()->workspace_id);
            }
        })->first()->developed_by;
        $permissions = Auth::user()->roles()->first()->permissions->pluck('name');

        $user_auth = auth()->user();
        $warehouses_ids = Warehouse::where('deleted_at', '=', null)->pluck('id')->toArray();
        if(!$user_auth->is_all_warehouses){
            $warehouses_ids = UserWarehouse::where('user_id', $user_auth->id)->pluck('warehouse_id')->toArray();
        }

        $products_alerts = product_warehouse::join('products', 'product_warehouse.product_id', '=', 'products.id')
            ->where(function ($query){
                if (auth()->user()->workspace_id) {
                    $query->where('products.workspace_id', '=', auth()->user()->workspace_id);
                }
            })
            ->whereIn('warehouse_id', $warehouses_ids)
            ->whereRaw('qte <= stock_alert')
            ->where('product_warehouse.deleted_at', null)
            ->join('warehouses', 'product_warehouse.warehouse_id', '=', 'warehouses.id')
            ->where(function ($query) {
                if (auth()->user()->workspace_id) {
                    $query->where('warehouses.workspace_id', '=', auth()->user()->workspace_id);
                }
            })
            ->count();

        return response()->json([
            'success' => true,
            'user' => $user,
            'notifs' => $products_alerts,
            'permissions' => $permissions,
        ]);
    }

    //------------- GET USER ROLES ---------\\

    public function GetUserRole(Request $request)
    {

        $roles = Auth::user()->roles()->with('permissions')->first();

        $data = [];
        if ($roles) {
            foreach ($roles->permissions as $permission) {
                $data[] = $permission->name;

            }
            return response()->json(['success' => true, 'data' => $data]);
        }

    }

    //------------- STORE NEW USER ---------\\

    public function store(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'create', User::class);
        $this->validate($request, [
            'email' => 'required|unique:users',
        ], [
            'email.unique' => 'This Email already taken.',
        ]);
        \DB::transaction(function () use ($request) {
            if ($request->hasFile('avatar')) {

                $image = $request->file('avatar');
                $filename = rand(11111111, 99999999) . $image->getClientOriginalName();

                $image_resize = Image::make($image->getRealPath());
                $image_resize->resize(128, 128);
                $image_resize->save(public_path('/images/avatar/' . $filename));

            } else {
                $filename = 'no_avatar.png';
            }

            if($request['is_all_warehouses'] == '1' || $request['is_all_warehouses'] == 'true'){
                $is_all_warehouses = 1;
            }else{
                $is_all_warehouses = 0;
            }
            
            $User = new User;
            $User->firstname = $request['firstname'];
            $User->lastname  = $request['lastname'];
            $User->username  = $request['username'];
            $User->email     = $request['email'];
            $User->phone     = $request['phone'];
            $User->password  = Hash::make($request['password']);
            $User->avatar    = $filename;
            $User->role_id   = $request['role'];
            $User->is_all_warehouses   = $is_all_warehouses;
            // user workspace
            if($request['role'] !== 1){
                if(Auth::user()->role_id == 1) {
                    // create default worksapce for a new user
                    $Workspace = new Workspace;
                    $Workspace->name  = $request['username'] . "'s workspace";
                    $Workspace->save();
                    $User->workspace_id = $Workspace->id;
                    // create default warehouse for a new user
                    $Warehouse = new Warehouse;
                    $Warehouse->name = $request['username'] . "'s warehouse";
                    $Warehouse->workspace_id = $Workspace->id;
                    $Warehouse->save();
                } else {
                    // assign an existing workspace to a new user
                    $User->workspace_id = Auth::user()->workspace_id;
                }
            } else {
                $User->workspace_id = null;
            }

            $User->save();

            //set workspace owner
            if(Auth::user()->role_id == 1 && $request['role'] !== 1){
                // set workspace owner
                $Workspace->owner = $User->id;
                $Workspace->save();
            }
            
            $role_user = new role_user;
            $role_user->user_id = $User->id;
            $role_user->role_id = $request['role'];
            $role_user->save();

            if(Auth::user()->role_id == 1 && $request['role'] !== 1){
                // set warehouse to a user
                $assigned_to = array();
                array_push($assigned_to, $Warehouse->id);
                $User->assignedWarehouses()->sync($assigned_to);
            } else {
                if(!$User->is_all_warehouses){
                    $User->assignedWarehouses()->sync($request['assigned_to']);
                }
            }
            
            // save owner setting
            if($request['role'] == 2) {
                // set mail sender and etc
                $Server = new Server;
                $Server->workspace_id   = $User->workspace_id;
                $Server->username       = $User->username;
                $Server->sender_name    = $User->firstname . $User->lastname;
                $Server->save();

                // create walk-in-customer
                $ClientController = app(ClientController::class);
                $Client = new Client;
                $Client->name = 'walk-in-customer';
                $Client->workspace_id   = $User->workspace_id;
                $Client->code = $ClientController->getNumberOrder();
                $Client->save();
                // create setting

                $Setting = new Setting;
                $Setting->workspace_id          = $User->workspace_id;
                $Setting->email                 = $User->email;
                $Setting->currency_id           = 1;
                $Setting->sms_gateway           = 1;
                $Setting->client_id             = $Client->id;
                $Setting->is_invoice_footer     = 0;
                $Setting->invoice_footer        = 1;
                $Setting->warehouse_id          = $Warehouse->id;
                $Setting->logo                  = 'logo-2.png';
                $Setting->save();

                // create pos setting
                PosSetting::create([
                    'workspace_id' => $User->workspace_id,
                    'note_customer' => $User->username . 'Thank You For Shopping With Us . Please Come Again',
                    'show_note' => 1,
                    'show_barcode' => 1,
                    'show_discount' => 1,
                    'show_customer' => 1,
                    'show_email' => 1,
                    'show_phone' => 1,
                    'show_address' => 1,
                ]);

                // sms message
                SMSMessage::create([
                    'name' => 'sale',
                    'workspace_id' => $User->workspace_id,
                    'text' => "Dear {contact_name},\nThank you for your purchase! Your invoice number is {invoice_number}.\nIf you have any questions or concerns, please don't hesitate to reach out to us. We are here to help!\nBest regards,\n{business_name}"
                ]);
                SMSMessage::create([
                    'name' => 'quotation',
                    'workspace_id' => $User->workspace_id,
                    'text' => "Dear {contact_name},\nI recently made a purchase from your company and I wanted to thank you for your cooperation and service. My invoice number is {invoice_number} .\nIf you have any questions or concerns regarding my purchase, please don't hesitate to contact me. I am here to make sure I have a positive experience with your company.\nBest regards,\n{business_name}"
                ]);
                SMSMessage::create([
                    'name' => 'payment_received',
                    'workspace_id' => $User->workspace_id,
                    'text' => "Dear {contact_name},\nThank you for your interest in our products. Your quotation number is {quotation_number}.\nPlease let us know if you have any questions or concerns regarding your quotation. We are here to assist you.\nBest regards,\n{business_name}"
                ]);
                SMSMessage::create([
                    'name' => 'purchase',
                    'workspace_id' => $User->workspace_id,
                    'text' => "Dear {contact_name},\nThank you for making your payment. We have received it and it has been processed successfully.\nIf you have any further questions or concerns, please don't hesitate to reach out to us. We are always here to help.\nBest regards,\n{business_name}"
                ]);
                SMSMessage::create([
                    'name' => 'payment_sent',
                    'workspace_id' => $User->workspace_id,
                    'text' => "Dear {contact_name},\nWe have just sent the payment . We appreciate your prompt attention to this matter and the high level of service you provide.\nIf you need any further information or clarification, please do not hesitate to reach out to us. We are here to help.\nBest regards,\n{business_name}"
                ]);

                // email message
                EmailMessage::create([
                    'name' => 'sale',
                    'workspace_id' => $User->workspace_id,
                    'subject' => 'Thank you for your purchase!',
                    'body' => "<h1><b><span style='font-size:14px;'>Dear</span><span style='font-size:14px;'>  </span></b><span style='font-size:14px;'><b>{contact_name},</b></span></h1><p><span style='font-size:14px;'>Thank you for your purchase! Your invoice number is {invoice_number}.</span></p><p><span style='font-size:14px;'>If you have any questions or concerns, please don't hesitate to reach out to us. We are here to help!</span></p><p><span style='font-size:14px;'>Best regards,</span></p><p><b>{business_name}</b></p>",
                ]);
                EmailMessage::create([
                    'name' => 'quotation',
                    'workspace_id' => $User->workspace_id,
                    'subject' => 'Thank you for your interest in our products !',
                    'body' =>  '<p><b><span style="font-size:14px;">Dear {contact_name},</span></b></p><p>Thank you for your interest in our products. Your quotation number is {quotation_number}.</p><p>Please let us know if you have any questions or concerns regarding your quotation. We are here to assist you.</p><p>Best regards,</p><p><b><span style="font-size:14px;">{business_name}</span></b></p>',
                ]);
                EmailMessage::create([
                    'name' => 'payment_received',
                    'workspace_id' => $User->workspace_id,
                    'subject' => 'Payment Received - Thank You',
                    'body' =>  '<p><b><span style="font-size:14px;">Dear {contact_name},</span></b></p><p>Thank you for making your payment. We have received it and it has been processed successfully.</p><p>If you have any further questions or concerns, please don\'t hesitate to reach out to us. We are always here to help.</p><p>Best regards,</p><p><b><span style="font-size:14px;">{business_name}</span></b></p>',
                ]);
                EmailMessage::create([
                    'name' => 'purchase',
                    'workspace_id' => $User->workspace_id,
                    'subject' => 'Thank You for Your Cooperation and Service',
                    'body' =>  '<p><b><span style="font-size:14px;">Dear {contact_name},</span></b></p><p>I recently made a purchase from your company and I wanted to thank you for your cooperation and service. My invoice number is {invoice_number} .</p><p>If you have any questions or concerns regarding my purchase, please don\'t hesitate to contact me. I am here to make sure I have a positive experience with your company.</p><p>Best regards,</p><p><b><span style="font-size:14px;">{business_name}</span></b></p>',
                ]);
                EmailMessage::create([
                    'name' => 'payment_sent',
                    'workspace_id' => $User->workspace_id,
                    'subject' => 'Payment Sent - Thank You for Your Service',
                    'body' =>  '<p><b><span style="font-size:14px;">Dear {contact_name},</span></b></p><p>We have just sent the payment . We appreciate your prompt attention to this matter and the high level of service you provide.</p><p>If you need any further information or clarification, please do not hesitate to reach out to us. We are here to help.</p><p>Best regards,</p><p><b><span style="font-size:14px;">{business_name}</span></b></p>',
                ]);
            }
        }, 10);

        return response()->json(['success' => true]);
    }

    //------------ function show -----------\\

    public function show($id){
        //
        
    }

    public function edit(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'update', User::class);

        $assigned_warehouses = UserWarehouse::where('user_id', $id)->pluck('warehouse_id')->toArray();
        $warehouses = Warehouse::where('deleted_at', '=', null)->whereIn('id', $assigned_warehouses)->pluck('id')->toArray();

        return response()->json([
            'assigned_warehouses' => $warehouses,
        ]);
    }

    //------------- UPDATE  USER ---------\\

    public function update(Request $request, $id)
    {        
        $this->authorizeForUser($request->user('api'), 'update', User::class);
        
        $this->validate($request, [
            'email' => 'required|email|unique:users',
            'email' => Rule::unique('users')->ignore($id),
        ], [
            'email.unique' => 'This Email already taken.',
        ]);

        \DB::transaction(function () use ($id ,$request) {
            $user = User::findOrFail($id);
            $current = $user->password;

            if ($request->NewPassword != 'null') {
                if ($request->NewPassword != $current) {
                    $pass = Hash::make($request->NewPassword);
                } else {
                    $pass = $user->password;
                }

            } else {
                $pass = $user->password;
            }

            $currentAvatar = $user->avatar;
            if ($request->avatar != $currentAvatar) {

                $image = $request->file('avatar');
                $path = public_path() . '/images/avatar';
                $filename = rand(11111111, 99999999) . $image->getClientOriginalName();

                $image_resize = Image::make($image->getRealPath());
                $image_resize->resize(128, 128);
                $image_resize->save(public_path('/images/avatar/' . $filename));

                $userPhoto = $path . '/' . $currentAvatar;
                if (file_exists($userPhoto)) {
                    if ($user->avatar != 'no_avatar.png') {
                        @unlink($userPhoto);
                    }
                }
            } else {
                $filename = $currentAvatar;
            }

            if($request['is_all_warehouses'] == '1' || $request['is_all_warehouses'] == 'true'){
                $is_all_warehouses = 1;
            }else{
                $is_all_warehouses = 0;
            }

            User::whereId($id)->update([
                'firstname' => $request['firstname'],
                'lastname' => $request['lastname'],
                'username' => $request['username'],
                'email' => $request['email'],
                'phone' => $request['phone'],
                'password' => $pass,
                'avatar' => $filename,
                'statut' => $request['statut'],
                'is_all_warehouses' => $is_all_warehouses,
                'role_id' => $request['role'],

            ]);

            role_user::where('user_id' , $id)->update([
                'user_id' => $id,
                'role_id' => $request['role'],
            ]);

            $user_saved = User::where('deleted_at', '=', null)->findOrFail($id);
            $user_saved->assignedWarehouses()->sync($request['assigned_to']);

        }, 10);
        
        return response()->json(['success' => true]);

    }


    //------------- UPDATE PROFILE ---------\\

    public function updateProfile(Request $request)
    {
        $id = Auth::user()->id;
        $user = User::findOrFail($id);
        $current = $user->password;

        if ($request->NewPassword != 'undefined') {
            if ($request->NewPassword != $current) {
                $pass = Hash::make($request->NewPassword);
            } else {
                $pass = $user->password;
            }

        } else {
            $pass = $user->password;
        }

        $currentAvatar = $user->avatar;
        if ($request->avatar != $currentAvatar) {

            $image = $request->file('avatar');
            $path = public_path() . '/images/avatar';
            $filename = rand(11111111, 99999999) . $image->getClientOriginalName();

            $image_resize = Image::make($image->getRealPath());
            $image_resize->resize(128, 128);
            $image_resize->save(public_path('/images/avatar/' . $filename));

            $userPhoto = $path . '/' . $currentAvatar;

            if (file_exists($userPhoto)) {
                if ($user->avatar != 'no_avatar.png') {
                    @unlink($userPhoto);
                }
            }
        } else {
            $filename = $currentAvatar;
        }

        User::whereId($id)->update([
            'firstname' => $request['firstname'],
            'lastname' => $request['lastname'],
            'username' => $request['username'],
            'email' => $request['email'],
            'phone' => $request['phone'],
            'password' => $pass,
            'avatar' => $filename,

        ]);

        return response()->json(['avatar' => $filename, 'user' => $request['username']]);

    }

    //----------- IsActivated (Update Statut User) -------\\

    public function IsActivated(request $request, $id)
    {

        $this->authorizeForUser($request->user('api'), 'update', User::class);

        $user = Auth::user();
        if ($request['id'] !== $user->id) {
            User::whereId($id)->update([
                'statut' => $request['statut'],
            ]);
            return response()->json([
                'success' => true,
            ]);
        } else {
            return response()->json([
                'success' => false,
            ]);
        }
    }

    public function GetPermissions()
    {
        $roles = Auth::user()->roles()->with('permissions')->first();
        $data = [];
        if ($roles) {
            foreach ($roles->permissions as $permission) {
                $item[$permission->name]['slug'] = $permission->name;
                $item[$permission->name]['id'] = $permission->id;

            }
            $data[] = $item;
        }
        return $data[0];

    }

    //------------- GET USER Auth ---------\\

    public function GetInfoProfile(Request $request)
    {
        $data = Auth::user();
        return response()->json(['success' => true, 'user' => $data]);
    }

}
