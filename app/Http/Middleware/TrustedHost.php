<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrustedHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured=array_values(array_filter(array_map('trim',explode(',',(string)env('APP_ALLOWED_HOSTS','')))));
        if($configured){
            $host=strtolower($request->getHost());
            $allowed=false;
            foreach($configured as $pattern){
                $pattern=strtolower($pattern);
                if($host===$pattern||str_starts_with($pattern,'*.')&&str_ends_with($host,substr($pattern,1))){$allowed=true;break;}
            }
            if(!$allowed)return response()->json(['message'=>'Untrusted host.'],400);
        }
        return $next($request);
    }
}
