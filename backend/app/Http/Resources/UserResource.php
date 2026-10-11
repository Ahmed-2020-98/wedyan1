<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\User */
final class UserResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatarUrl' => $this->avatar_path ? asset('storage/' . $this->avatar_path) : null,
            'isActive' => $this->is_active,
            'emailVerified' => $this->email_verified_at !== null,
            // getRoleNames() تأتي من spatie؛ الواجهة تبني قوائمها منها.
            'roles' => $this->getRoleNames(),
            'createdAt' => $this->created_at?->toIso8601String(),
        ];
    }
}
