//ver rutas que coincidan con user
grep -rn "user" routes/web.php


//ver columnas
php artisan tinker --execute="print_r(Illuminate\Support\Facades\Schema::getColumnListing('orden_produccions'));"