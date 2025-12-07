<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;

class AuthController extends Controller
{
    use ApiResponseTrait;
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();

        if(!Auth::attempt($validatedData)) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed. Please check your credentials.'
            ], 401);
        }

        $token = $user->createToken($user->name)->plainTextToken;


        // Implement login logic here
        return $this->success([
            'user' => $user,
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
