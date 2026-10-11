<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * يمنع حساباً عُطِّل بعد إصدار رمزه من الاستمرار في العمل.
 *
 * بدون هذا، تعطيل حساب من لوحة الإدارة لا أثر له حتى ينتهي الرمز — وهو ما
 * يجعل «تعطيل الحساب» إجراءً بلا مفعول في اللحظة التي يُحتاج فيها.
 */
final class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->isActive()) {
            $user->tokens()->delete();

            return response()->json([
                'message' => 'هذا الحساب معطَّل. تواصل مع الدعم.',
            ], 403);
        }

        return $next($request);
    }
}
