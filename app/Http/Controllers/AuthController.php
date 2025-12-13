<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends ApiController
{
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if(!Auth::attempt($validatedData)) {
            return $this->error('Login failed. Please check your credentials.', [], 401);
        }

        $token = $user->createToken($user->name)->plainTextToken;


        // Implement login logic here
        return $this->success([
            'user' => $user->load('course', 'year', 'department')->only(['id', 'name', 'email', 'role']),
            'token' => $token,
        ], [], 200);
    }

    public function destroy(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return $this->success([
            'message' => 'Logged out successfully'
        ], [], 200);
    }
}
