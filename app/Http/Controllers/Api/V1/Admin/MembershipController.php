<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignRoleRequest;
use App\Http\Requests\Admin\RevokeRoleRequest;
use App\Services\Access\RoleAssignmentService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class MembershipController extends Controller
{
    public function __construct(private readonly RoleAssignmentService $roles) {}

    public function store(AssignRoleRequest $request): JsonResponse
    {
        $membership = $this->roles->assign($request->user(), $request->attributes->get('hospital'), $request->input('user_id'), $request->input('role'),
            $request->filled('valid_from') ? CarbonImmutable::parse($request->input('valid_from')) : null,
            $request->filled('valid_to') ? CarbonImmutable::parse($request->input('valid_to')) : null,
            $request->ip());

        return response()->json(['data' => ['membership_id' => $membership->membership_id, 'role' => $membership->role_code]], 201);
    }

    public function revoke(RevokeRoleRequest $request, string $membership): JsonResponse
    {
        $revoked = $this->roles->revoke($request->user(), $request->attributes->get('hospital'), $membership, $request->input('motivo'), $request->ip());

        return response()->json(['data' => ['membership_id' => $revoked->membership_id, 'status' => $revoked->status]]);
    }
}
