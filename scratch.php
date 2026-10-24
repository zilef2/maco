<?php

echo json_encode(DB::select('SELECT id,nombre,puede_pasar_a,regla_clave FROM estado_operacions ORDER BY id'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
echo json_encode(DB::select('SHOW COLUMNS FROM compromisos_reparacion'), JSON_PRETTY_PRINT), PHP_EOL;
echo json_encode(DB::select('SHOW COLUMNS FROM notificaciones'), JSON_PRETTY_PRINT), PHP_EOL;
