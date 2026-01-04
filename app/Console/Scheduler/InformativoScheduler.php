<?php

namespace App\Console\Scheduler;

use App\Models\Informativo;
use App\Models\User;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InformativoScheduler
{
    public function __invoke()
    {
        try {
            DB::transaction(function () {
                $now = now()->startOfMinute();
                Log::info('Scheduler iniciado em ' . $now);

                $publicar = Informativo::whereIn('status', ['agendado','aprovado'])
                    ->whereDate('publish_at', $now->toDateString())
                    ->get();
                $totalPublicar = $publicar->count();
                $publicados = 0;
                $skipPublicar = 0;
                foreach ($publicar as $info) {
                    if ($info->status === 'agendado') {
                        Log::info('Publicando informativo ID ' . $info->id . ' (publish_at: ' . $info->publish_at . ')');
                        $info->update(['status' => 'publicado', 'published_at' => $now]);
                        // Notifica todos os usuários sobre a publicação
                        $allUsers = User::all();
                        foreach ($allUsers as $user) {
                            Notification::create([
                                'user_id' => $user->id,
                                'informativo_id' => $info->id,
                                'title' => 'Novo informativo publicado',
                                'message' => 'O informativo #' . $info->id . ' foi publicado.',
                                'created_at' => now(),
                            ]);
                        }
                        $publicados++;
                    } else {
                        $skipPublicar++;
                    }
                }

                $despublicar = Informativo::where('status', 'publicado')
                    ->whereDate('unpublished_at', $now->toDateString())
                    ->get();
                $totalDespublicar = $despublicar->count();
                $despublicados = 0;
                $skipDespublicar = 0;
                foreach ($despublicar as $info) {
                    if ($info->status === 'publicado') {
                        Log::info('Despublicando informativo ID ' . $info->id . ' (unpublished_at: ' . $info->unpublished_at . ')');
                        $info->update(['status' => 'despublicado']);
                        // Notifica todos os usuários sobre a despublicação
                        $allUsers = User::all();
                        foreach ($allUsers as $user) {
                            Notification::create([
                                'user_id' => $user->id,
                                'informativo_id' => $info->id,
                                'title' => 'Informativo despublicado',
                                'message' => 'O informativo #' . $info->id . ' foi despublicado.',
                                'created_at' => now(),
                            ]);
                        }
                        $despublicados++;
                    } else {
                        $skipDespublicar++;
                    }
                }

                Log::info('Scheduler estatísticas: '
                    . ' Publicar encontrados: ' . $totalPublicar
                    . ', publicados: ' . $publicados
                    . ', skipados: ' . $skipPublicar
                    . ' | Despublicar encontrados: ' . $totalDespublicar
                    . ', despublicados: ' . $despublicados
                    . ', skipados: ' . $skipDespublicar
                );
                Log::info('Scheduler finalizado em ' . now());
            }, attempts: 2);
        } catch (\Throwable $e) {
            Log::error('Erro na transação do scheduler: ' . $e->getMessage(), ['exception' => $e]);
        }
    }
}
