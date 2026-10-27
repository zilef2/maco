<?php

declare(strict_types=1);

/**
 * Regla: una OP con al menos un producto recibido (aprobado, en reparación o
 * no recuperable) ya está «Recibiendo». Las OPs que tenían recepciones guardadas
 * como avance pero seguían «En taller» se reasignan aquí, porque «Guardar avance»
 * ahora cambia el estado solo.
 */
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE orden_produccions op
            JOIN estado_operacions actual ON actual.id = op.estado_op_id
            JOIN estado_operacions recibiendo ON recibiendo.nombre = 'Recibiendo'
            SET op.estado_op_id = recibiendo.id,
                op.recepcion_desde = COALESCE(
                    op.recepcion_desde,
                    (SELECT MAX(r.fecha_recepcion) FROM recepcion_calidads r WHERE r.orden_produccion_id = op.id),
                    NOW()
                )
            WHERE actual.nombre NOT IN ('Recibiendo', 'Cerrada')
              AND EXISTS (
                    SELECT 1
                    FROM recepcion_calidads r2
                    WHERE r2.orden_produccion_id = op.id
                      AND (
                            r2.cantidad_recibida > 0
                            OR r2.cantidad_aprobada > 0
                            OR r2.cantidad_en_reparacion > 0
                            OR r2.cantidad_no_recuperable > 0
                      )
                  )
        ");
    }

    public function down(): void
    {
        // Irreversible: no se conserva el estado anterior de cada OP.
    }
};
