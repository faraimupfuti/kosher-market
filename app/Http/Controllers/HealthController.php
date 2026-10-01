<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke()
    {
        try {
            DB::select('select 1');
            return response()->json(['status'=>'ok','database'=>'ok','timestamp'=>now()->toISOString()],200);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['status'=>'degraded','database'=>'unavailable'],503);
        }
    }
}
