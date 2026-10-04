<?php

namespace Alejo\Notificaciones\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;

class NotificacionSetupCommand extends Command
{
    protected $signature = 'notificacion:setup';
    protected $description = 'Configura el sistema de notificaciones persistentes';

    public function handle(): int
    {
        $this->info('=== Configurando notificaciones persistentes ===');

        // 1. Asegurar que la tabla existe (migración)
        $this->command->info('1. Ejecutando migración...');
        DB::statement('CREATE TABLE IF NOT EXISTS notificaciones (
            id INT AUTO_INCREMENT PRIMARY KEY,
            mensaje TEXT NOT NULL,
            prioridad ENUM("baja", "media", "alto") NOT NULL DEFAULT "baja",
            leida BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )');
        $this->command->info('   Tabla notificaciones asegurada.');

        // 2. Registrar rutas API
        $this->command->info('2. Registrando rutas API...');
        $routesPath = base_path('routes/notificaciones.php');
        if (!file_exists($routesPath)) {
            file_put_contents($routesPath, $this->getRoutesContent());
            $this->command->info('   Rutas guardadas en routes/notificaciones.php');
        } else {
            $this->command->info('   Archivo routes/notificaciones.php ya existe.');
        }

        // 3. Crear controlador si no existe
        $this->command->info('3. Verificando controlador...');
        $controllerPath = app_path('Http/Controllers/NotificacionController.php');
        if (!file_exists($controllerPath)) {
            file_put_contents($controllerPath, $this->getControllerContent());
            $this->command->info('   Controlador creado en app/Http/Controllers/NotificacionController.php');
        } else {
            $this->command->info('   Controlador ya existe.');
        }

        // 4. Publicar recursos del paquete
        $this->command->info('4. Publicando recursos del paquete...');
        $this->call('vendor:publish', [
            '--tag' => 'notificaciones-assets',
            '--force' => true,
        ]);
        $this->command->info('   Recursos publicados.');

        $this->command->info('=== Configuración completada ===');
        $this->command->info('Ejecute "php artisan migrate" si la tabla no fue creada automáticamente.');
        $this->command->info('Las notificaciones aparecerán en la esquina superior derecha del dashboard.');

        return Command::SUCCESS;
    }

    private function getRoutesContent(): string
    {
        return '<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotificacionController;

Route::middleware(["auth", "verified"])->group(function () {
    Route::get("/notificaciones", [NotificacionController::class, "index"])->name("notificaciones.index");
    Route::post("/notificaciones/marcar/{id}", [NotificacionController::class, "marcarComoLeida"])->name("notificaciones.marcar-como-leida");
    Route::post("/notificaciones/marcar-todas", [NotificacionController::class, "marcarTodas"])->name("notificaciones.marcar-todas");
});
';
    }

    private function getControllerContent(): string
    {
        return '<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use DB;

class NotificacionController extends Controller
{
    public function index(Request $request)
    {
        $usuarioId = $request->user()?->id;
        $notificaciones = DB::table("notificaciones")
            ->where("leida", false)
            ->where(function ($query) use ($usuarioId) {
                if ($usuarioId) {
                    $query->whereNull("usuario_id") || $query->where("usuario_id", $usuarioId);
                }
            })
            ->orderByRaw("FIELD(prioridad, "alta", "media", "baja")")
            ->take(5)
            ->get();

        return response()->json([
            "notificaciones" => $notificaciones->map(function ($n) {
                return [
                    "id" => $n->id,
                    "mensaje" => $n->mensaje,
                    "prioridad" => $n->prioridad,
                    "creado_en" => $n->created_at->toDateTimeString(),
                ];
            }),
        ]);
    }

    public function marcarComoLeida(Request $request, $id)
    {
        DB::table("notificaciones")
            ->where("id", $id)
            ->update(["leida" => true]);

        return response()->json(["success" => true]);
    }

    public function marcarTodas(Request $request)
    {
        DB::table("notificaciones")
            ->where("leida", false)
            ->update(["leida" => true]);

        return response()->json(["success" => true]);
    }
}
';
    }
}