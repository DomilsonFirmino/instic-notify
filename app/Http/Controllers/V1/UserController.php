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

    public function favorites($id)
    {
        $user = User::find($id);
        $favorites = $user->favorites; // Assuming a 'favorites' relationship exists in User model
        return $this->success($favorites,[],200);
    }

    public function notifications($id)
    {
        $user = User::find($id);
        $notifications = $user->notifications; // Assuming a 'notifications' relationship exists in User model
        return $this->success($notifications,[],200);
    }

    public function markNotificationAsRead($userId, $notificationId)
    {
        $user = User::find($userId);
        $notification = $user->notifications()->where('id', $notificationId)->first();

        if ($notification) {
            $notification->read_at = now();
            $notification->save();
            return $this->success($notification,[],200);
        }

        return response()->json(['message' => 'Notification not found'], 404);
    }

    public function markAllNotificationsAsRead($userId)
    {
        $user = User::find($userId);
        $user->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read'], 200);
    }
    public function me(Request $request)
    {
        $user = $request->user()->load('course', 'year', 'department');
        return $this->success($user,[],200);
    }
}
