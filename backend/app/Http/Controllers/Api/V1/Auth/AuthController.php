<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

final class AuthController extends Controller
{
    /**
     * تسجيل زبون جديد.
     *
     * الدور مثبَّت على customer ولا يُقرأ من الطلب إطلاقاً — لو قُرئ لأمكن
     * لأي زائر أن يسجّل نفسه مديراً.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone', 'regex:/^(\+?966|0)?5\d{8}$/'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'phone.regex' => 'رقم الجوال يجب أن يكون رقم جوال سعودي صحيح.',
        ]);

        $user = DB::transaction(function () use ($data): User {
            // is_active صراحةً لا اعتماداً على قيمة العمود الافتراضية:
            // الافتراضي يطبَّق في قاعدة البيانات ولا يعرفه الكائن في الذاكرة،
            // فيعود الرد بـ null في أول استجابة بعد التسجيل.
            $user = User::create([...$data, 'is_active' => true]);
            $user->assignRole(Role::Customer->value);

            return $user;
        });

        $user->sendEmailVerificationNotification();

        return response()->json([
            'data' => [
                'user' => new UserResource($user->load('roles')),
                'token' => $user->createToken('api')->plainTextToken,
            ],
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $credentials['email'])->first();

        /*
         | رسالة واحدة لكل حالات الفشل. التفريق بين «بريد غير مسجّل» و«كلمة
         | مرور خاطئة» يمنح المهاجم عدّاد حسابات صالحة مجاناً.
         */
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['بيانات الدخول غير صحيحة.'],
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['هذا الحساب معطَّل. تواصل مع الدعم.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        return response()->json([
            'data' => [
                'user' => new UserResource($user->load('roles')),
                'token' => $user->createToken('api')->plainTextToken,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($request->user()->load('roles')),
        ]);
    }

    /** يُلغي الرمز الحالي فقط، فتبقى جلسات الأجهزة الأخرى قائمة. */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'تم تسجيل الخروج.']);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'string', 'max:20', 'unique:users,phone,' . $user->id, 'regex:/^(\+?966|0)?5\d{8}$/'],
        ]);

        $user->update($data);

        return response()->json(['data' => new UserResource($user->fresh()->load('roles'))]);
    }

    /**
     * تغيير كلمة المرور. يُلغي كل الرموز الأخرى ويُصدر رمزاً جديداً
     * للجهاز الحالي — فمن سرق رمزاً قديماً يفقده فوراً.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['كلمة المرور الحالية غير صحيحة.'],
            ]);
        }

        $user->update(['password' => $data['password']]);
        $user->tokens()->delete();

        return response()->json([
            'data' => ['token' => $user->createToken('api')->plainTextToken],
            'message' => 'تم تغيير كلمة المرور.',
        ]);
    }
}
