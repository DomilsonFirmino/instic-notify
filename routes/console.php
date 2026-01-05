<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Console\Scheduler\InformativoScheduler;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('informativo', function () {
    (new InformativoScheduler())();
    $this->info('Agendamento de informativos executado pelo comando informativo. Veja o log para detalhes.');
});
