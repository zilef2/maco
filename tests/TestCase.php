<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Laravel ignora `.env.testing` y las variables `<env>` de `phpunit.xml` cuando
     * existe `bootstrap/cache/config.php`, por lo que la suite corría con el
     * entorno real (`APP_ENV=test`, base de datos `maco`), provocando errores 419
     * (CSRF) y escrituras en la base de datos equivocada.
     *
     * Se elimina esa caché antes de crear la aplicación para garantizar que cada
     * test arranque siempre en un entorno de pruebas limpio y aislado.
     */
    protected function refreshApplication()
    {
        $cachedConfig = dirname(__DIR__).'/bootstrap/cache/config.php';

        if (is_file($cachedConfig)) {
            @unlink($cachedConfig);
        }

        parent::refreshApplication();
    }
}
