<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $this->settings($user)]);
    }

    /**
     * @param UpdateSettingsRequest $request
     *
     * @return JsonResponse
     */
    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->update($request->validated());

        return response()->json(['data' => $this->settings($user)]);
    }

    /**
     * @param User $user
     *
     * @return array{pushover_user_key: string|null, temperature_unit: string}
     */
    private function settings(User $user): array
    {
        return [
            'pushover_user_key' => $user->pushover_user_key,
            'temperature_unit'  => config('foliotrak.temperature_unit', 'F'),
        ];
    }
}
