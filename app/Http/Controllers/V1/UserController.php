<?php

namespace App\Http\Controllers\V1;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Models\User;

class UserController extends ApiController
{
    public function me(Request $request)
    {
        $user = $request->user()->load('course', 'year', 'department');
        return $this->success($user,[],200);
    }

    public function index()
    {
        $auth = request()->user();

        $role = $auth->role ?? null;
        if ($role === 'admin') {
            $users = User::query()->paginate();
            return $this->success($users, [], 200);
        }

        if ($role === 'leitor') {
            $users = User::query()->where('role', 'leitor')->paginate();
            return $this->success($users, [], 200);
        }

        return $this->error('Acesso negado a esta listagem.', 'FORBIDDEN', [], 403);
    }
    public function show($id)
    {
        $auth = request()->user();
        $isAdmin = $auth->hasRole('admin');
        $isSelf = $auth && (string)$auth->id === (string)$id;
        if (!($isAdmin || $isSelf)) {
            return $this->error('Acesso negado a este recurso.', 'FORBIDDEN', [], 403);
        }

        try {
            $user = User::with('course', 'year', 'department')->findOrFail($id);
            return $this->success($user, [], 200);
        } catch (ModelNotFoundException $e) {
            return $this->error('Usuário não encontrado.', 'NOT_FOUND', [], 404);
        }
    }

    public function store(StoreUserRequest $request)
    {
        $data = $request->validated();
        // Default role to 'leitor' when not provided
        $data['role'] = $data['role'] ?? 'leitor';
        $data['password'] = bcrypt($data['password']);
        $user = User::create($data);
        return $this->success($user, [], 201);
    }
    public function update(UpdateUserRequest $request, $id)
    {
        try {
            $user = User::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return $this->error('Usuário não encontrado.', 'NOT_FOUND', [], 404);
        }

        $data = $request->validated();

        // Only admins can change role; otherwise strip it
        $auth = $request->user();
        $isAdmin = $auth && method_exists($auth, 'hasRole') && $auth->hasRole('admin');
        if (!$isAdmin) {
            unset($data['role']);
        }

        // Limit fields explicitly
        $allowed = ['name','email','password','course_id','year_id','department_id','role'];
        $data = array_intersect_key($data, array_flip($allowed));

        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }

        $user->update($data);
        return $this->success($user->fresh(),  [], 200);
    }
    public function destroy($id)
    {
        $auth = request()->user();
        $isAdmin = $auth && method_exists($auth, 'hasRole') && $auth->hasRole('admin');
        $isSelf = $auth && (string)$auth->id === (string)$id;
        if (!($isAdmin || $isSelf)) {
            return $this->error('Acesso negado à remoção deste usuário.', 'FORBIDDEN', [], 403);
        }

        try {
            $user = User::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return $this->error('Usuário não encontrado.', 'NOT_FOUND', [], 404);
        }

        // Invalidate all API tokens for the user (Sanctum)
        if (method_exists($user, 'tokens')) {
            $user->tokens()->delete();
        }

        $user->delete();
        return $this->success(['message' => "User with ID: $id deleted"],[], 200);
    }

    public function favorites($id)
    {
        try {
            $user = User::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return $this->error('Usuário não encontrado.', 'NOT_FOUND', [], 404);
        }
        $favorites = $user->favorites; // Assuming a 'favorites' relationship exists in User model
        return $this->success($favorites,[],200);
    }

    public function notifications($id)
    {
        try {
            $user = User::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return $this->error('Usuário não encontrado.', 'NOT_FOUND', [], 404);
        }
        $notifications = $user->notifications; // Assuming a 'notifications' relationship exists in User model
        return $this->success($notifications,[],200);
    }

    public function markNotificationAsRead($userId, $notificationId)
    {
        try {
            $user = User::findOrFail($userId);
        } catch (ModelNotFoundException $e) {
            return $this->error('Usuário não encontrado.', 'NOT_FOUND', [], 404);
        }
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
        try {
            $user = User::findOrFail($userId);
        } catch (ModelNotFoundException $e) {
            return $this->error('Usuário não encontrado.', 'NOT_FOUND', [], 404);
        }
        $user->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read'], 200);
    }
}
