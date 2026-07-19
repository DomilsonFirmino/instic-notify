<?php

namespace App\Console\Scheduler;

use App\Jobs\NotifyUsersJob;
use App\Models\Informativo;
use App\Models\User;
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

                        // Notifica apenas os leitores relevantes por audiência
                        $relevantReaders = $this->getRelevantReaders($info);
                        NotifyUsersJob::dispatch($relevantReaders, [
                            'informativo_id' => $info->id,
                            'title' => 'Novo informativo publicado',
                            'message' => 'O informativo #' . $info->id . ' foi publicado.',
                        ]);
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

                        // Notifica apenas os leitores que receberam a publicação originalmente
                        $relevantReaders = $this->getRelevantReaders($info);
                        NotifyUsersJob::dispatch($relevantReaders, [
                            'informativo_id' => $info->id,
                            'title' => 'Informativo despublicado',
                            'message' => 'O informativo #' . $info->id . ' foi despublicado.',
                        ]);
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

    private function getRelevantReaders(Informativo $informativo)
    {
        $query = User::query()->where('role', 'leitor');

        if (is_null($informativo->course_id)
            && is_null($informativo->year_id)
            && is_null($informativo->department_id)
        ) {
            return $query->get();
        }

        return $query->where(function ($subQuery) use ($informativo) {
            if (!is_null($informativo->course_id)) {
                $subQuery->orWhere('course_id', $informativo->course_id);
            }

            if (!is_null($informativo->year_id)) {
                $subQuery->orWhere('year_id', $informativo->year_id);
            }

            if (!is_null($informativo->department_id)) {
                $subQuery->orWhere('department_id', $informativo->department_id);
            }

            $subQuery->orWhere(function ($nested) {
                $nested->whereNull('course_id')
                    ->whereNull('year_id')
                    ->whereNull('department_id');
            });
        })->get();
    }
}
