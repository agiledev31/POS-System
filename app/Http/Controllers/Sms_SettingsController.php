<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use App\Models\sms_gateway;
use App\Models\SMSMessage;
use File;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use Nexmo\Laravel\Facade\Nexmo;

class Sms_SettingsController extends Controller
{


    //-------------- Get_sms_config ---------------\\

    public function get_sms_config(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'sms_settings', Setting::class);
        Artisan::call('config:cache');
        Artisan::call('config:clear');

        $twilio['TWILIO_SID'] = env('TWILIO_SID');
        $twilio['TWILIO_FROM'] = env('TWILIO_FROM');
        $twilio['TWILIO_TOKEN'] = '';

        $nexmo['nexmo_key'] = env('NEXMO_KEY');
        $nexmo['nexmo_secret'] = env('NEXMO_SECRET');
        $nexmo['nexmo_from'] = env('NEXMO_FROM');

        $infobip['base_url']    = env('base_url');
        $infobip['api_key']     = env('api_key');
        $infobip['sender_from'] = env('sender_from');

        $sms_gateway = sms_gateway::where('deleted_at', '=', null)->get(['id', 'title']);
        $settings = Setting::where('deleted_at', '=', null)->first();

        if ($settings->sms_gateway) {
            if (sms_gateway::where('id', $settings->sms_gateway)->where('deleted_at', '=', null)->first()) {
                $default_sms_gateway = $settings->sms_gateway;
            } else {
                $default_sms_gateway = '';
            }
        } else {
            $default_sms_gateway = '';
        }


        return response()->json([
            'twilio' => $twilio,
            'nexmo' => $nexmo,
            'infobip' => $infobip,
            'sms_gateway' => $sms_gateway,
            'default_sms_gateway' => $default_sms_gateway,
        ], 200);
    }


    //-------------- update_twilio_config ---------------\\

    public function update_twilio_config(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'sms_settings', Setting::class);

        
            $this->setEnvironmentValue([
                'TWILIO_SID' => $request['TWILIO_SID'] !== null?'"' . $request['TWILIO_SID'] . '"':'"' . env('TWILIO_SID') . '"',
                'TWILIO_TOKEN' => $request['TWILIO_TOKEN'] !== null?'"' . $request['TWILIO_TOKEN'] . '"':'"' . env('TWILIO_TOKEN') . '"',
                'TWILIO_FROM' => $request['TWILIO_FROM'] !== null?'"' . $request['TWILIO_FROM'] . '"':'"' . env('TWILIO_FROM') . '"',
            ]);

            Artisan::call('config:cache');
            Artisan::call('config:clear');

        return response()->json(['success' => true]);

    }



     //-------------- Update nexmo_sms_config ---------------\\

     public function update_nexmo_config(Request $request)
     {
         $this->authorizeForUser($request->user('api'), 'sms_settings', Setting::class);

        $this->setEnvironmentValue([
            'NEXMO_KEY' => $request['nexmo_key'] !== null?'"' . $request['nexmo_key'] . '"':'"' . env('NEXMO_KEY') . '"',
            'NEXMO_SECRET' => $request['nexmo_secret'] !== null?'"' . $request['nexmo_secret'] . '"':'"' . env('NEXMO_SECRET') . '"',
            'NEXMO_FROM' => $request['nexmo_from'] !== null?'"' . $request['nexmo_from'] . '"':'"' . env('NEXMO_FROM') . '"',
        ]);

        Artisan::call('config:cache');
        Artisan::call('config:clear');

       return response()->json(['success' => true]);

     }


      //-------------- update_infobip_config ---------------\\

    public function update_infobip_config(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'sms_settings', Setting::class);

        
            $this->setEnvironmentValue([
                'base_url' => $request['base_url'] !== null?'"' . $request['base_url'] . '"':'"' . env('base_url') . '"',
                'api_key' => $request['api_key'] !== null?'"' . $request['api_key'] . '"':'"' . env('api_key') . '"',
                'sender_from' => $request['sender_from'] !== null?'"' . $request['sender_from'] . '"':'"' . env('sender_from') . '"',
            ]);

            Artisan::call('config:cache');
            Artisan::call('config:clear');

        return response()->json(['success' => true]);

    }

    //-------------- update_Default_SMS ---------------\\

    public function update_Default_SMS(Request $request)
    {

        $this->authorizeForUser($request->user('api'), 'sms_settings', Setting::class);

        if ($request['default_sms_gateway'] != 'null') {
            $sms_gateway = $request['default_sms_gateway'];
        } else {
            $sms_gateway = null;
        }

        Setting::whereId(1)->update([
            'sms_gateway' => $sms_gateway,
        ]);

    }


     
    //-------------- Set Environment Value ---------------\\

    public function setEnvironmentValue(array $values)
    {
        $envFile = app()->environmentFilePath();
        $str = file_get_contents($envFile);
        $str .= "\r\n";
        if (count($values) > 0) {
            foreach ($values as $envKey => $envValue) {
    
                $keyPosition = strpos($str, "$envKey=");
                $endOfLinePosition = strpos($str, "\n", $keyPosition);
                $oldLine = substr($str, $keyPosition, $endOfLinePosition - $keyPosition);
    
                if (is_bool($keyPosition) && $keyPosition === false) {
                    // variable doesnot exist
                    $str .= "$envKey=$envValue";
                    $str .= "\r\n";
                } else {
                    // variable exist                    
                    $str = str_replace($oldLine, "$envKey=$envValue", $str);
                }            
            }
        }
    
        $str = substr($str, 0, -1);
        if (!file_put_contents($envFile, $str)) {
            return false;
        }
    
        app()->loadEnvironmentFrom($envFile);    
    
        return true;
    }

}
