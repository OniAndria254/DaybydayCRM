<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DiscountSettingController extends Controller
{
    /**
     * Obtenir la configuration de remise actuelle
     */
    public function getDiscountSetting()
    {
        $setting = Setting::first();
        
        return response()->json([
            'globalDiscountRate' => $setting->global_discount_rate
        ]);
    }
    
    /**
     * Mettre à jour la configuration de remise
     */
    public function updateDiscountSetting(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'globalDiscountRate' => 'required|numeric|min:0|max:100',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        $setting = Setting::first();
        $setting->global_discount_rate = $request->globalDiscountRate;
        $setting->save();
        
        return response()->json([
            'globalDiscountRate' => $setting->global_discount_rate,
            'message' => 'Discount setting updated successfully'
        ]);
    }
}
