<?php

use App\Models\Procesoop;

$procesos = [
    'Artesanias',
    'Bordado a mano',
    'Embarretado de tela',
    'Ensable de bolsos',
    'Ensable de zapatos',
    'Ensamble de correas',
    'Pegado de piedras',
    'Sublimacion tela',
];

foreach ($procesos as $nombre) {
    Procesoop::firstOrCreate(['nombre' => $nombre]);
}

echo "Procesos cargados correctamente.\n";