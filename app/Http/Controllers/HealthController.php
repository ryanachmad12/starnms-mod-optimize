<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function ready()
    {
        try {
            DB::select('select 1');
            Cache::store('redis')->get('starnms:health:ready');
        } catch (\Throwable) {
            return response()->json(['status' => 'unavailable'], 503)->header('Cache-Control', 'no-store');
        }

        return response()->json(['status' => 'ready'])->header('Cache-Control', 'no-store');
    }
}
