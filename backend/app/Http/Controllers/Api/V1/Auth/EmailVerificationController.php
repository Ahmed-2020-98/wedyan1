<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;

final class EmailVerificationController extends Controller
{
    /**
     * نقطة الهبوط من رسالة البريد.
     *
     * تتحقق من التوقيع ثم تُعيد التوجيه إلى الواجهة بنتيجة في المعامل،
     * فالمستخدم قادم من بريده في متصفح لا من التطبيق.
     */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $web = rtrim((string) config('platform.urls.web'), '/');
        $user = User::find($id);

        if (! $user || ! hash_equals($hash, sha1((string) $user->getEmailForVerification()))) {
            return redirect()->away($web . '/verify-email?status=invalid');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->away($web . '/verify-email?status=already');
        }

        $user->markEmailAsVerified();

        return redirect()->away($web . '/verify-email?status=success');
    }

    public function resend(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'بريدك مؤكَّد بالفعل.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'أرسلنا رسالة تأكيد جديدة إلى بريدك.']);
    }
}
