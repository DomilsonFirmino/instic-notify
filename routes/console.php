<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Informativo;
use Illuminate\Support\Facades\Log;


Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('informativo:schedule', function () {
    $now = now()->startOfMinute();
    Log::info('Comando informativo:schedule iniciado em ' . $now);

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
            $despublicados++;
        } else {
            $skipDespublicar++;
        }
    }

    Log::info('Comando informativo:schedule estatísticas: '
        . ' Publicar encontrados: ' . $totalPublicar
        . ', publicados: ' . $publicados
        . ', skipados: ' . $skipPublicar
        . ' | Despublicar encontrados: ' . $totalDespublicar
        . ', despublicados: ' . $despublicados
        . ', skipados: ' . $skipDespublicar
    );
    Log::info('Comando informativo:schedule finalizado em ' . now());
    $this->info('Agendamento de informativos executado. Veja o log para detalhes.');
})->purpose('Executa o agendamento e despublicação de informativos imediatamente');

Schedule::call(function () {
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
})->everyFiveMinutes();
