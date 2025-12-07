<?php

namespace App\Http\Controllers\V1;

use Illuminate\Http\Request;
use App\Http\Controllers\ApiController;
use App\Models\User;

class UserController extends ApiController
{
    public function index()
    {
        $users = User::all();
        return $this->success($users,[],200);
    }
    public function show($id)
    {
        $user = User::find($id);
        return $this->success($user,[],200);
    }
    public function store(Request $request)
    {
        return response()->json(['message' => 'User created'], 201);
    }
    public function update(Request $request, $id)
    {
        return response()->json(['message' => "User with ID: $id updated"]);
    }
    public function destroy($id)
    {
        return response()->json(['message' => "User with ID: $id deleted"]);
    }
}
