<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Las importaciones de Excel se procesan en cola. Sin un worker activo, un
// archivo podía quedarse esperando indefinidamente aunque el job tardara
// menos de un segundo. Se revisa la cola cada minuto y no se solapan workers.
Schedule::command('queue:work --stop-when-empty --tries=1')
    ->everyMinute()
    // Si un worker muere, el bloqueo expira en 5 minutos y no deja la cola
    // detenida hasta el día siguiente.
    ->withoutOverlapping(5);
