<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

class NotifyUsersJob implements ShouldQueue
{
    use Queueable;

    /**
     * @var array<int, int|string>
     */
    public array $userIds;

    /**
     * @param iterable<int, User|array{id:mixed}|int|string> $users
     * @param array<string, mixed> $payload
     */
    public function __construct(iterable $users, public array $payload)
    {
        $this->onConnection('database');

        $this->userIds = collect($users)
            ->map(function ($user) {
                if ($user instanceof User) {
                    return $user->id;
                }

                if (is_array($user)) {
                    return $user['id'] ?? null;
                }

                return $user;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function handle(): void
    {
        if ($this->userIds === []) {
            return;
        }

        $timestamp = now();
        $rows = Collection::make($this->userIds)
            ->map(fn ($userId) => [
                'user_id' => $userId,
                'informativo_id' => $this->payload['informativo_id'] ?? null,
                'title' => $this->payload['title'],
                'message' => $this->payload['message'],
                'read_at' => $this->payload['read_at'] ?? null,
                'created_at' => $this->payload['created_at'] ?? $timestamp,
            ])
            ->all();

        Notification::insert($rows);
    }
}
