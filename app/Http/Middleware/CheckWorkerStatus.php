<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckWorkerStatus
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        $worker = auth('api')->user();

        if (!$worker) {
            return response()->json([
                'success' => false,
                'message' => 'لم يتم العثور على العامل أو لم يتم تسجيل الدخول.',
            ], 401);
        }

        if ($worker->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'حساب العامل غير مفعل.',
            ], 403);
        }

        if (!$worker->station || $worker->station->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'محطة العمل غير مفعّلة أو غير متاحة.',
            ], 403);
        }

        return $next($request);
    }
}
