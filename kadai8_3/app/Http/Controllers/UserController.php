<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ConfirmRequest;
use App\Http\Requests\GameOverRequest;

class UserController extends Controller
{
    private $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    /**
     * Create a user
     *
     * @param UserCreateRequest $request
     * @return JsonResponse
     */

    public function create(UserRequest $request)
    {
        return $this->userService->insertUser($request->only(['nickname']));
    }

    public function login(LoginRequest $request)
    {
        $loginResponse = $this->userService->assignTokenToUser($request->id);
        if ($loginResponse === "E10500") {
            return response()->json(['data' => ['存在しないIDです。']], 200);
        } else {
            return $loginResponse;
        }
    }

    public function confirm(ConfirmRequest $request)
    {
        $confirmResponse = $this->userService->confirmUserToken($request->id, $request->token);
        if ($confirmResponse === "E10500") {
            return response()->json(['data' => ['ユーザーは存在しません。']], 200);
        } elseif ($confirmResponse === "E10510") {
            // The token entered is wrong
            return response()->json(['data' => ['不正です。']], 200);
        } else {
            return $confirmResponse;
        }
    }

    public function gameover(GameOverRequest $request)
    {
        // Check if the request is for a non-existent user
        if ($this->userService->getUserByUserID($request->id) === null) {
            return response()->json(['data' => ['ユーザーは存在しません。']], 200);
        }
        // Update the level if needed
        $gameoverResponse = $this->userService->incrementExperienceAndUpdateLevel($request->id, $request->exp);
        return response()->json(['data' => $gameoverResponse], 200);
    }
}
