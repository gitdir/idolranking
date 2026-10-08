<?php

namespace App\Http\Middleware;

use Closure;
use App\Support\DatabaseAvailability;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BlockRestrictedIp
{
    public function handle(Request $request, Closure $next)
    {
        $ip = $request->ip();
        $databaseAvailable = DatabaseAvailability::isAvailable();
        if ($ip && $databaseAvailable && Schema::hasTable('blocked_ips') && DB::table('blocked_ips')
            ->where('ip_address', $ip)->where('is_active', 1)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', now()))->exists()) {
            return response('접근이 제한된 IP 주소입니다.', 403);
        }

        return $next($request);
    }
}
