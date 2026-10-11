<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::with('roles');

        if ($role = $request->query('role')) {
            $query->role($role);
        }

        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like));
        }

        return UserResource::collection($query->latest()->paginate(20));
    }

    public function show(User $user): JsonResponse
    {
        return response()->json([
            'data' => new UserResource($user->load('roles')),
            'stats' => [
                'orders' => $user->orders()->count(),
                'reservations' => $user->reservations()->count(),
                'totalSpent' => (float) $user->orders()->where('payment_status', 'paid')->sum('grand_total'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'password' => ['required', Password::min(8)->letters()->numbers()],
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        $user = User::create([...$data, 'is_active' => true]);
        $user->assignRole($data['role']);
        $user->forceFill(['email_verified_at' => now()])->save();

        return response()->json(['data' => new UserResource($user->load('roles'))], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['sometimes', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($user->id)],
            'role' => ['sometimes', Rule::enum(Role::class)],
        ]);

        $user->update($data);

        if (isset($data['role'])) {
            $user->syncRoles([$data['role']]);
        }

        return response()->json(['data' => new UserResource($user->fresh('roles'))]);
    }

    /**
     * تفعيل/تعطيل الحساب.
     *
     * التعطيل يُلغي رموز الدخول فوراً بدل انتظار انتهائها — انظر
     * EnsureUserIsActive. وبدون ذلك يبقى الحساب المعطَّل عاملاً.
     */
    public function toggleActive(Request $request, User $user): JsonResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'لا يمكنك تعطيل حسابك.');

        $user->update(['is_active' => ! $user->is_active]);

        if (! $user->is_active) {
            $user->tokens()->delete();
        }

        return response()->json([
            'data' => new UserResource($user->fresh('roles')),
            'message' => $user->is_active ? 'فُعِّل الحساب.' : 'عُطِّل الحساب.',
        ]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'لا يمكنك حذف حسابك.');
        abort_if($user->orders()->exists(), 422, 'لا يمكن حذف حساب له طلبات. عطّله بدلاً من ذلك.');

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'حُذف الحساب.']);
    }
}
