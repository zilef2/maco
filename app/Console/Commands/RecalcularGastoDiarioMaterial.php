<?php

namespace App\Console\Commands;

use App\Models\Material;
use Illuminate\Console\Command;

class RecalcularGastoDiarioMaterial extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'material:recalcular-gasto-diario {--ventana=30 : Días de ventana para calcular el consumo}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Recalcula y persiste el gasto_diario de los materiales activos';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $ventana = (int) $this->option('ventana');

        $this->info("Recalculando gasto_diario de materiales activos (ventana: {$ventana} días)...");

        $resultado = Material::recalcularGastoDiarioDeTodos($ventana);

        $this->table(
            ['Resultado', 'Cantidad'],
            [
                ['Actualizados', $resultado['actualizados']],
                ['Sin consumo en la ventana', $resultado['sinConsumo']],
            ]
        );

        $this->info('Listo.');
    }
}
