<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreModeratorRequest;
use App\Http\Requests\UpdateModeratorRequest;
use App\Http\Resources\ModeratorResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Moderator accounts, created and managed by the super administrator only.
 */
class ModeratorController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $moderators = User::where('role', UserRole::Moderator)
            ->with('creator')
            ->withCount('moderatedProducts')
            ->latest()
            ->get();

        return ModeratorResource::collection($moderators);
    }

    /**
     * Without a password, a temporary one is generated and returned once, so it can be handed over.
     */
    public function store(StoreModeratorRequest $request): JsonResponse
    {
        $password = $request->validated('password') ?: $this->temporaryPassword();

        $moderator = new User([
            'name' => $request->validated('name'),
            'email' => Str::lower($request->validated('email')),
            'password' => $password,
        ]);
        $moderator->forceFill(['role' => UserRole::Moderator, 'created_by' => $request->user()->id])->save();

        return (new ModeratorResource($moderator->load('creator')->loadCount('moderatedProducts')))
            ->additional(['meta' => ['password' => $password]])
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateModeratorRequest $request, User $moderator): ModeratorResource
    {
        $this->ensureIsModerator($moderator);

        $moderator->fill($request->safe()->only(['name', 'email']));

        if ($request->has('suspended')) {
            $moderator->forceFill(['suspended_at' => $request->boolean('suspended') ? ($moderator->suspended_at ?? now()) : null]);

            if ($request->boolean('suspended')) {
                $moderator->tokens()->delete();
            }
        }

        $moderator->save();

        return new ModeratorResource($moderator->load('creator')->loadCount('moderatedProducts'));
    }

    /**
     * Replace the password with a new temporary one and sign the moderator out everywhere.
     */
    public function resetPassword(User $moderator): JsonResponse
    {
        $this->ensureIsModerator($moderator);

        $password = $this->temporaryPassword();
        $moderator->forceFill(['password' => $password])->save();
        $moderator->tokens()->delete();

        return (new ModeratorResource($moderator->load('creator')->loadCount('moderatedProducts')))
            ->additional(['meta' => ['password' => $password]])
            ->response();
    }

    public function destroy(Request $request, User $moderator): Response
    {
        $this->ensureIsModerator($moderator);

        $moderator->tokens()->delete();
        $moderator->delete();

        return response()->noContent();
    }

    private function ensureIsModerator(User $user): void
    {
        abort_unless($user->role === UserRole::Moderator, 404);
    }

    private function temporaryPassword(): string
    {
        return Str::password(14, symbols: false);
    }
}
