<?php

namespace App\Http\Controllers\V1;

use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Models\Notification;
use App\Models\User;

class UserController extends ApiController
{
    public function me(Request $request)
    {
        $user = $request->user()->load('course', 'year', 'department','notifications', 'favorites');
        return $this->success($user,[],200);
    }

    public function index()
    {
        $auth = request()->user();

        $role = $auth->role ?? null;
        $perPage = (int) request()->input('per_page', 15);
        $perPage = max(1, min($perPage, 500));

        if ($role === 'admin') {
            $users = User::query()->paginate($perPage);
            $meta = [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ];
            return $this->success($users->items(), $meta, 200);
        }

        if ($role === 'leitor') {
            $users = User::query()->where('role', 'leitor')->paginate($perPage);
            $meta = [
                'current_page' => $users->currentPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
                'last_page' => $users->lastPage(),
            ];
            return $this->success($users->items(), $meta, 200);
        }

        return $this->error('Acesso negado a esta listagem.', 'FORBIDDEN', [], 403);
    }
    public function show($id)
    {
        $auth = request()->user();
        $isAdmin = $auth->hasRole('admin');
        $isSelf = $auth && (string)$auth->id === (string)$id;

        try {
            $user = User::with('course', 'year', 'department')->findOrFail($id);
            if($isAdmin || $isSelf || $user->role === 'leitor') {
                return $this->success($user, [], 200);
            }
            return $this->error('Acesso negado a este recurso.', 'FORBIDDEN', [], 403);
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
        $auth = $request->user();
        $isAdmin = $auth && method_exists($auth, 'hasRole') && $auth->hasRole('admin');
        $isSelf = $auth && (string)$auth->id === (string)$id;
        if (!($isAdmin || $isSelf)) {
            return $this->error('Acesso negado à atualização deste usuário.', 'FORBIDDEN', [], 403);
        }
        try {
            $user = User::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return $this->error('Usuário não encontrado.', 'NOT_FOUND', [], 404);
        }

        $data = $request->validated();

        // Only admins can change role; otherwise strip it
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
        return $this->success([
            'message' => "User with deleted",
            'user' => $user->only(['id', 'name', 'email', 'role'])
        ],[], 200);
    }

    public function favorites($id)
    {
        $auth = request()->user();
        if ((string)$auth->id !== (string)$id && !$auth->hasRole('admin')) {
            return $this->error('Acesso negado aos favoritos de outro usuário.', 'FORBIDDEN', [], 403);
        }
        try {
            $user = User::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return $this->error('Usuário não encontrado.', 'NOT_FOUND', [], 404);
        }
        $favorites = $user->favorites()->with('informativo')->get();
        return $this->success($favorites,[],200);
    }

    public function notifications($id)
    {
        $auth = request()->user();
        if ((string)$auth->id !== (string)$id && !$auth->hasRole('admin')) {
            return $this->error('Acesso negado às notificações de outro usuário.', 'FORBIDDEN', [], 403);
        }
        try {
            $user = User::findOrFail($id);
        } catch (ModelNotFoundException $e) {
            return $this->error('Usuário não encontrado.', 'NOT_FOUND', [], 404);
        }
        $notifications = $user->notifications()->with('informativo')->paginate();
        $meta = [
            'current_page' => $notifications->currentPage(),
            'per_page' => $notifications->perPage(),
            'total' => $notifications->total(),
            'last_page' => $notifications->lastPage(),
        ];
        return $this->success($notifications->items(),$meta,200);
    }

    public function usersnotifications()
    {
        $auth = request()->user();
        $role = $auth->hasRole('admin');
        if (!$role) {
            return $this->error('Acesso negado às notificações de outro usuário.', 'FORBIDDEN', [], 403);
        }

        $notifications = Notification::query()
            ->with('informativo')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate();
        $meta = [
            'current_page' => $notifications->currentPage(),
            'per_page' => $notifications->perPage(),
            'total' => $notifications->total(),
            'last_page' => $notifications->lastPage(),
        ];
        return $this->success($notifications->items(),$meta, 200);
    }

    public function showFavorite($userId, $favoriteId)
    {
        $auth = request()->user();
        if ((string)$auth->id !== (string)$userId && !$auth->hasRole('admin')) {
            return $this->error('Acesso negado ao favorito de outro usuário.', 'FORBIDDEN', [], 403);
        }
        $favorite = \App\Models\Favorite::where('user_id', $userId)->where('id', $favoriteId)->first();
        if (!$favorite) {
            return $this->error('Favorito não encontrado.', 'NOT_FOUND', [], 404);
        }
        return $this->success($favorite,[],200);
    }

    public function removeFavorite($userId, $favoriteId)
    {
        $auth = request()->user();
        if ((string)$auth->id !== (string)$userId && !$auth->hasRole('admin')) {
            return $this->error('Acesso negado ao favorito de outro usuário.', 'FORBIDDEN', [], 403);
        }
        $favorite = \App\Models\Favorite::where('user_id', $userId)->where('id', $favoriteId)->first();
        if (!$favorite) {
            return $this->error('Favorito não encontrado.', 'NOT_FOUND', [], 404);
        }
        // Permitir apenas se o usuário for dono do favorito ou admin
        if ((string)$auth->id !== (string)$favorite->user_id && !$auth->hasRole('admin')) {
            return $this->error('Sem permissão para remover este favorito.', 'FORBIDDEN', [], 403);
        }
        $favorite->delete();
        return $this->success(['deleted' => true],[],200);
    }

    public function removeAllFavorites($userId)
    {
        $auth = request()->user();
        if ((string)$auth->id !== (string)$userId && !$auth->hasRole('admin')) {
            return $this->error('Acesso negado aos favoritos de outro usuário.', 'FORBIDDEN', [], 403);
        }
        // Permitir apenas se o usuário for dono dos favoritos ou admin
        $deleted = 0;
        if ((string)$auth->id === (string)$userId || $auth->hasRole('admin')) {
            $deleted = \App\Models\Favorite::where('user_id', $userId)->delete();
        }
        return $this->success(['deleted_count' => $deleted],[],200);
    }

    public function showNotification($userId, $notificationId)
    {
        $auth = request()->user();
        if ((string)$auth->id !== (string)$userId && !$auth->hasRole('admin')) {
            return $this->error('Acesso negado à notificação de outro usuário.', 'FORBIDDEN', [], 403);
        }
        $notification = \App\Models\Notification::with('informativo')
            ->where('user_id', $userId)
            ->where('id', $notificationId)
            ->first();
        if (!$notification) {
            return $this->error('Notificação não encontrada.', 'NOT_FOUND', [], 404);
        }
        return $this->success($notification,[],200);
    }

    public function markNotificationAsRead($userId, $notificationId)
    {
        $auth = request()->user();
        if ((string)$auth->id !== (string)$userId && !$auth->hasRole('admin')) {
            return $this->error('Acesso negado à notificação de outro usuário.', 'FORBIDDEN', [], 403);
        }
        try {
            $user = User::findOrFail($userId);
        } catch (ModelNotFoundException $e) {
            return $this->error('Usuário não encontrado.', 'NOT_FOUND', [], 404);
        }
        $notification = $user->notifications()->with('informativo')->where('id', $notificationId)->first();
        if ($notification) {
            // Permitir apenas se o usuário for dono da notificação ou admin
            if ((string)$auth->id !== (string)$userId && !$auth->hasRole('admin')) {
                return $this->error('Sem permissão para marcar como lida.', 'FORBIDDEN', [], 403);
            }
            $notification->read_at = now();
            $notification->save();
            $notification->load('informativo');
            return $this->success($notification,[],200);
        }
        return $this->error('Notificação não encontrada.', 'NOT_FOUND', [], 404);
    }

    public function markAllNotificationsAsRead($userId)
    {
        $auth = request()->user();
        if ((string)$auth->id !== (string)$userId && !$auth->hasRole('admin')) {
            return $this->error('Acesso negado às notificações de outro usuário.', 'FORBIDDEN', [], 403);
        }
        try {
            $user = User::findOrFail($userId);
        } catch (ModelNotFoundException $e) {
            return $this->error('Usuário não encontrado.', 'NOT_FOUND', [], 404);
        }
        // Permitir apenas se o usuário for dono das notificações ou admin
        if ((string)$auth->id === (string)$userId || $auth->hasRole('admin')) {
            $user->notifications()->whereNull('read_at')->update(['read_at' => now()]);
        }

        return $this->success(['message' => 'All notifications marked as read'],[],200);
    }
}
