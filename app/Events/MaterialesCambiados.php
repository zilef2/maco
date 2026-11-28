<?php

namespace App\Events;

/**
 * Aviso de que algo del listado de Materiales cambió.
 *
 * Lo dispara el propio modelo (creado, actualizado, eliminado) y el borrado
 * masivo, que borra por consulta y por eso no suelta el evento de Eloquent. El
 * evento no hace nada por sí solo: lo escucha ReconstruirListadoMateriales, que
 * invalida el listado y programa la recarga en la cola.
 */
class MaterialesCambiados
{
    public function __construct(
        public string $origen = 'material',
        public ?int $materialId = null,
    ) {
    }
}
