php artisan test --filter=OrdenProduccionE2ETest

# Nueva suite creada
SeedsOrdenesProduccion.php
 — trait con fixtures deterministas (roles/usuario, estados Borrador→…→Cerrada, EstadoOpMaterial, taller/empresa externa, materiales genéricos y procesados con ficha, productos con ficha, distribuciones). No usa seeders ni los factories vacíos del proyecto.
OrdenProduccionE2ETest.php
 — 12 tests que recorren el ciclo de vida completo por las rutas reales:
Creación: OP de producto (kit desde ficha), OP de material procesado, borrador.
Transiciones: flujo hasta En taller con descuento de kit, bloqueo de Lista para taller con kit incompleto, reenvío de estado en Recibiendo.
Materiales: compra que promueve la OP a Lista para taller, entrada/salida de inventario con material_movimientos.
Validaciones: transición no permitida, cierre con pares en reparación, recepción que supera lo esperado.
Cierre: cierre automático con cierre_orden_produccions y producto_movimientos.
Resultado

Aislada: 12 passed (58 assertions).
Suite existente: mantiene su resultado base (~122 passed / 16 failed en Feature). Las fallas preexistentes (DashboardGraficasTest, FichaTecnicaMaterialesTest, KitProcesoTest, MaterialCompraTest, MaterialFichaTest, MaterialVariantesTest, ProductoTest, y OrdenProduccionTest > se puede guardar una nueva op por estado_op_id nulo) son anteriores a este trabajo y no las introduce la nueva suite.

Nota sobre la corrida completa
En php artisan test (Unit+Feature juntos) aparecen además deadlocks MySQL intermitentes (SQLSTATE[40001]) y un cuelgue de refactor en curso (tabla orden_produccion_material_concreto aún referenciada por MaterialConcretoOpService pese a la migración que la elimina). Son problemas de la base de maco_tests y del código en desarrollo, no de la suite nueva, que es autónoma y pasa de forma aislada.

Para correrla sola: php artisan test --filter=OrdenProduccionE2ETest