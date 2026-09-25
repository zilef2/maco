<?php

namespace App\Jobs;

use App\Mail\MailDesarrolloDelay;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class EnviarViaticoJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected array $datos;
    protected string $correo;

    public function __construct(string $correo, array $datos)
    {
        $this->correo = $correo;
        $this->datos = $datos;
    }

    public function handle(): void
    {
        Mail::to($this->correo)->send(new MailDesarrolloDelay($this->datos));
    }
}
