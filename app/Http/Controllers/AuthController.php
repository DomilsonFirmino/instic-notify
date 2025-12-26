<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\Api\AuthStoreRequest;

class AuthController extends ApiController
{
    public function store(AuthStoreRequest $request)
    {
        $validatedData = $request->validated();
        $user = User::where('email', $validatedData['email'])->first();

        if(!Auth::attempt($validatedData)) {
            return $this->error('Login failed. Please check your credentials.', [], 401);
        }

        $token = $user->createToken($user->name)->plainTextToken;

        return $this->success([
            'user' => $user->load('course', 'year', 'department')->only(['id', 'name', 'email', 'role']),
            'token' => $token,
        ], [], 200);
    }

    public function destroy(Request $request)
    {
        $data = $request->user();
        $request->user()->currentAccessToken()->delete();
        return $this->success([
            'message' => 'Logged out successfully',
            'user' => $data->only(['id', 'name', 'email', 'role']),
        ], [], 200);
    }
}
