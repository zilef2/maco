<?php

use App\Models\Material;
use App\Models\MaterialPrice;

$materiales = Material::doesntHave('prices')->get();
$total = 0;

foreach ($materiales as $material) {
    MaterialPrice::create([
        'material_id' => $material->id,
        'precio' => rand(1000, 5000),
        'fecha' => now()->toDateString(),
    ]);
    $total++;
}

echo "Precios generados para {$total} materiales.\n";
