<?php

namespace App\Http\Resources;

use App\Models\Central\CentralUser;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CentralUser */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->user_id,
            'name' => $this->display_name,
            'email' => $this->email,
            'status' => $this->status,
            'last_authenticated_at' => $this->last_authenticated_at?->toIso8601String(),
            'roles' => $this->whenLoaded('memberships', fn () => $this->memberships->map(fn ($m) => [
                'membership_id' => $m->membership_id,
                'role' => $m->role_code,
                'valid_from' => $m->valid_from?->toIso8601String(),
                'valid_to' => $m->valid_to?->toIso8601String(),
            ])->values()),
        ];
    }
}
