<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Listado de Materiales
|--------------------------------------------------------------------------
|
| Los tamaños de la vista Materiales viven aquí, y se mueven por .env, porque
| son lo único que cambia cuando la bodega crece: hoy 1.100 materiales, mañana
| 5.000. Ningún archivo del proyecto debe volver a escribir estos números a
| mano; la vista los recibe ya resueltos en el prop `carga`.
|
*/

return [

    /*
    | De cuántos en cuántos se baja el listado en segundo plano.
    */
    'batch_size' => max(1, (int) env('MATERIALES_BATCH_SIZE', 30)),

    /*
    | Materiales que llegan en la primera respuesta y que se mantienen calientes
    | en caché: es lo que la vista pinta antes de pedir el resto.
    | Nunca puede ser menor que el batch_size, o la primera pantalla pediría un
    | bloque que ya debería tener.
    */
    'primera_carga' => max(
        max(1, (int) env('MATERIALES_BATCH_SIZE', 30)),
        max(1, (int) env('MATERIALES_PRIMERA_CARGA', 50)),
    ),

    /*
    | Segundos que se dejan acumular las escrituras antes de recalentar la
    | caché: diez guardados seguidos (un Excel, una carga de OPs) tienen que
    | costar una sola reconstrucción, no diez.
    */
    'reconstruccion_espera' => max(1, (int) env('MATERIALES_RECONSTRUCCION_ESPERA', 10)),

    /*
    | Cuánto vive cada trozo del listado en caché. La versión del listado ya lo
    | invalida al escribir, así que esto solo limita cuánto envejece un listado
    | que nadie tocó.
    */
    'cache_minutos' => max(1, (int) env('MATERIALES_CACHE_MINUTOS', 10)),

];
