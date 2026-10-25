

onBeforeMount: Similar to beforeMount.
onMounted: Similar to mounted.
onBeforeUpdate: Similar to beforeUpdate.
onUpdated: Similar to updated.
onBeforeUnmount: Similar to beforeDestroy.
onUnmounted: Similar to destroyed.

//! ************************* DESPLIEGUE () *************************

    composer dump-autoload
    php artisan key:generate

    rm -r /public_html/modulonom/moduloNomina
    rm -r /home/aplicativoswebco/public_html/modulonom/moduloNomina
    rm -r /home/aplicativoswebco/public_html/modulonom/storage/logs/laravel.log

    // <!-- borrar -->
        rm -r "direccion"
    // <!-- permisos recursivamente -->
        chmod a+rwx folder_name -R
        chmod -R 555 /home/aplicativoswebco/public_html/modulonom/config
        chmod -R 555 /home/aplicativoswebco/public_html/modulonom/app
        chmod -R 707 /home/aplicativoswebco/public_html/modulonom/storage
        chmod -R 775 /home/aplicativoswebco/public_html/modulonom/bootstrap
        sudo chmod -R ugo+rw /home/aplicativoswebco/public_html/modulonom/storage
        sudo chmod -R ugo+rw /home/aplicativoswebco/public_html/modulonom/bootstrap
        chmod -R 555 /home/aplicativoswebco/public_html/modulonom/*
    */

    mv /home/aplicativoswebco/public_html/modulonom/bootstrap/cache /home/aplicativoswebco/public_html/modulonom/bootstrap/cache_2
    mkdir /home/aplicativoswebco/public_html/modulonom/storage/framework/cache/data


//! ************************* PERMISOS *************************

app\helpers\MyModels.php