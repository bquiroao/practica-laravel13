<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Central\CentralUser;
use App\Services\Users\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $hospitalId = $request->attributes->get('hospital')->hospital_id;
        $search = trim((string) $request->query('q'));

        $users = CentralUser::query()
            ->whereHas('memberships', fn ($q) => $q->where('hospital_id', $hospitalId))
            ->with(['memberships' => fn ($q) => $q->current()->where('hospital_id', $hospitalId)->orderBy('role_code')])
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('display_name', 'ilike', '%'.$search.'%')->orWhere('email', 'ilike', '%'.$search.'%')))
            ->orderBy('display_name')
            ->paginate(min(100, max(1, (int) $request->query('per_page', 20))));

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $hospital = $request->attributes->get('hospital');
        $user = $this->users->create($request->user(), $hospital, $request->input('name'), $request->input('email'),
            $request->input('password'), $request->input('role'), $request->ip());
        $user->load(['memberships' => fn ($q) => $q->current()->where('hospital_id', $hospital->hospital_id)]);

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function updateStatus(Request $request, string $user): UserResource
    {
        $request->validate(['status' => ['required', 'in:active,inactive']]);

        return new UserResource($this->users->setStatus($request->user(), $request->attributes->get('hospital'), $user,
            $request->input('status'), $request->ip()));
    }
}
