<?php

use App\Models\parametro;
use Illuminate\Support\Facades\Lang;

/*
 php artisan tinker --execute="include 'app/tinker/traduc.php';"
 */

// echo app()->getLocale();          // ¿qué devuelve?
// echo config('app.locale');
// echo config('app.fallback_locale');


echo "Locale actual: " . app()->getLocale() . "\n";
echo "Fallback: " . config('app.fallback_locale') . "\n";
echo "lang_path(): " . lang_path() . "\n";
echo "Existe es/app.php: " . (file_exists(lang_path('es/app.php')) ? 'SÍ' : 'NO') . "\n";
echo "Existe en/app.php: " . (file_exists(lang_path('en/app.php')) ? 'SÍ' : 'NO') . "\n";

if (file_exists(lang_path('es/app.php'))) {
    $data = require lang_path('es/app.php');
    echo "¿Tiene label.User?: " . (isset($data['label']['User']) ? 'SÍ → ' . $data['label']['User'] : 'NO') . "\n";
}

echo "has() con es: " . (Lang::has('app.label.User', 'es') ? 'SÍ' : 'NO') . "\n";
echo "trans() con es: " . trans('app.label.User', [], 'es') . "\n";

echo "\n";
echo "========================================\n";
echo "  traduccion correcta \n";
echo "========================================\n\n";

