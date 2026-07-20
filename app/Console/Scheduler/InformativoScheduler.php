<?php

namespace App\Console\Scheduler;

use App\Jobs\NotifyUsersJob;
use App\Models\Informativo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InformativoScheduler
{
    public function __invoke()
    {
        $now = now()->startOfMinute();
        Log::info('Scheduler iniciado em ' . $now);

        $publicados = 0;
        $skipPublicar = 0;
        $despublicados = 0;

        // Publish: only agendado|aprovado, due, and not already past unpublish time
        $toPublishIds = Informativo::query()
            ->whereIn('status', ['agendado', 'aprovado'])
            ->whereNotNull('publish_at')
            ->where('publish_at', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('unpublished_at')
                    ->orWhere('unpublished_at', '>', $now);
            })
            ->pluck('id');

        foreach ($toPublishIds as $id) {
            $published = null;
            try {
                $published = DB::transaction(function () use ($id, $now) {
                    $info = Informativo::whereKey($id)
                        ->whereIn('status', ['agendado', 'aprovado'])
                        ->whereNotNull('publish_at')
                        ->where('publish_at', '<=', $now)
                        ->where(function ($q) use ($now) {
                            $q->whereNull('unpublished_at')
                                ->orWhere('unpublished_at', '>', $now);
                        })
                        ->lockForUpdate()
                        ->first();

                    if (!$info) {
                        return null;
                    }

                    Log::info('Publicando informativo ID ' . $info->id
                        . ' (publish_at: ' . $info->publish_at . ', status: ' . $info->status . ')');

                    $info->update([
                        'status' => 'publicado',
                        'published_at' => $now,
                    ]);

                    return $info->fresh();
                });
            } catch (\Throwable $e) {
                Log::error('Erro ao publicar informativo ID ' . $id . ': ' . $e->getMessage(), [
                    'exception' => $e,
                ]);
                continue;
            }

            if (!$published) {
                $skipPublicar++;
                continue;
            }

            $this->notifyReaders($published, 'Novo informativo publicado',
                'O informativo #' . $published->id . ' foi publicado.');
            $publicados++;
        }

        // Unpublish: only items still publicado whose unpublished_at is due
        $toUnpublishIds = Informativo::query()
            ->where('status', 'publicado')
            ->whereNotNull('unpublished_at')
            ->where('unpublished_at', '<=', $now)
            ->pluck('id');

        foreach ($toUnpublishIds as $id) {
            $unpublished = null;
            try {
                $unpublished = DB::transaction(function () use ($id, $now) {
                    $info = Informativo::whereKey($id)
                        ->where('status', 'publicado')
                        ->whereNotNull('unpublished_at')
                        ->where('unpublished_at', '<=', $now)
                        ->lockForUpdate()
                        ->first();

                    if (!$info) {
                        return null;
                    }

                    Log::info('Despublicando informativo ID ' . $info->id
                        . ' (unpublished_at: ' . $info->unpublished_at . ')');

                    $info->update(['status' => 'despublicado']);

                    return $info->fresh();
                });
            } catch (\Throwable $e) {
                Log::error('Erro ao despublicar informativo ID ' . $id . ': ' . $e->getMessage(), [
                    'exception' => $e,
                ]);
                continue;
            }

            if (!$unpublished) {
                continue;
            }

            $this->notifyReaders($unpublished, 'Informativo despublicado',
                'O informativo #' . $unpublished->id . ' foi despublicado.');
            $despublicados++;
        }

        Log::info('Scheduler estatísticas: '
            . ' publicar candidatos: ' . $toPublishIds->count()
            . ', publicados: ' . $publicados
            . ', skipados: ' . $skipPublicar
            . ' | despublicar candidatos: ' . $toUnpublishIds->count()
            . ', despublicados: ' . $despublicados
        );
        Log::info('Scheduler finalizado em ' . now());
    }

    private function notifyReaders(Informativo $informativo, string $title, string $message): void
    {
        $readers = $informativo->relevantReadersQuery()->get();
        if ($readers->isEmpty()) {
            return;
        }

        NotifyUsersJob::dispatch($readers, [
            'informativo_id' => $informativo->id,
            'title' => $title,
            'message' => $message,
        ]);
    }
}
