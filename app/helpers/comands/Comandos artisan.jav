
//! ************************* BUGS *************************

    //?helps
        //memory limit
            C:\laragon\bin\php\php-7.4.30-Win32-vc15-x64\php.ini

        //permissions deined
            mv /home/aplicativoswebco/public_html/modulonom/bootstrap/cache /home/aplicativoswebco/public_html/modulonom/bootstrap/cache_2
            mkdir /home/aplicativoswebco/public_html/modulonom/storage/framework/cache/data
    // FIN helps


//! ************************* MODELS - MIDDLE - CORREO *************************

    //models //? RIGHT NOW IT'S USING "vistas pap.jav"
        // Php artisan make:model CentroTrabajo --all

    //middleware
        php artisan make:middleware IsAdmin


    //#--  correo - EXPORT AND IMPORTS
        php artisan make:mail ExampleMail

        //#-- excel
        php artisan make:export ExampleExport --model=Empresa



