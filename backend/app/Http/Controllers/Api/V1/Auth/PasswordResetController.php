<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

final class PasswordResetController extends Controller
{
    /**
     * طلب رابط إعادة التعيين.
     *
     * الرد واحد سواء وُجد البريد أم لا. لو اختلف الردّ لأصبحت هذه النقطة
     * أداة لمعرفة من له حساب على المنصة.
     */
    public function sendResetLink(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'إن كان البريد مسجّلاً لدينا فستصلك رسالة بخطوات إعادة التعيين.',
        ]);
    }

    public function reset(Request $request): JsonResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password): void {
                $user->update(['password' => $password]);
                // إعادة التعيين تعني غالباً حساباً مخترقاً — تُلغى كل الرموز.
                $user->tokens()->delete();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => ['رابط إعادة التعيين غير صالح أو منتهي الصلاحية.'],
            ]);
        }

        return response()->json(['message' => 'تم تعيين كلمة المرور الجديدة. يمكنك تسجيل الدخول الآن.']);
    }
}
