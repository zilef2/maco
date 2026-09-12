<?php

//php artisan tinker --execute="require 'app/tinker/permisos_user.tinker';"
//php artisan tinker --execute="require 'app/tinker/permisos_user.php';"

$id = 1; // <-- cambia este ID o pásalo por variable de entorno

$user = \App\Models\User::find($id);

if (!$user) {
    echo "❌ No se encontró un usuario con ID: {$id}\n";
    return;
}

echo "\n";
echo "========================================\n";
echo "  USUARIO: {$user->name} (ID: {$user->id})\n";
echo "  Email:   {$user->email}\n";
echo "  name:   {$user->name}\n";
echo "========================================\n\n";

// -----------------------------------------
// ROLES
// -----------------------------------------
echo "🎭 ROLES:\n";
if (method_exists($user, 'getRoleNames')) {
    $roles = $user->getRoleNames();
    if ($roles->isEmpty()) {
        echo "   (sin roles asignados)\n";
    } else {
        foreach ($roles as $rol) {
            echo "   • {$rol}\n";
        }
    }
} else {
    echo "   ⚠️  El modelo User no usa el trait HasRoles (Spatie Permission)\n";
}

echo "\n";

// -----------------------------------------
// PERMISOS DIRECTOS (asignados al user)
// -----------------------------------------
echo "🔑 PERMISOS DIRECTOS:\n";
if (method_exists($user, 'getDirectPermissions')) {
    $directos = $user->getDirectPermissions();
    if ($directos->isEmpty()) {
        echo "   (sin permisos directos)\n";
    } else {
        foreach ($directos as $permiso) {
            echo "   • {$permiso->name}\n";
        }
    }
} else {
    echo "   ⚠️  Método no disponible\n";
}

echo "\n";

// -----------------------------------------
// PERMISOS VÍA ROLES
// -----------------------------------------
echo "🔐 PERMISOS VÍA ROLES:\n";
if (method_exists($user, 'getPermissionsViaRoles')) {
    $viaRoles = $user->getPermissionsViaRoles();
    if ($viaRoles->isEmpty()) {
        echo "   (sin permisos heredados por rol)\n";
    } else {
        foreach ($viaRoles as $permiso) {
            echo "   • {$permiso->name}\n";
        }
    }
} else {
    echo "   ⚠️  Método no disponible\n";
}

echo "\n";

// -----------------------------------------
// TODOS LOS PERMISOS (directos + vía roles)
// -----------------------------------------
echo "✅ TODOS LOS PERMISOS (efectivos):\n";
if (method_exists($user, 'getAllPermissions')) {
    $todos = $user->getAllPermissions();
    if ($todos->isEmpty()) {
        echo "   (sin permisos)\n";
    } else {
        foreach ($todos as $permiso) {
            echo "   • {$permiso->name}\n";
        }
    }
    echo "\n   Total: " . $todos->count() . " permisos\n";
} else {
    echo "   ⚠️  Método no disponible\n";
}

echo "\n========================================\n";

/*
🎭 ROLES:
   • superadmin

🔑 PERMISOS DIRECTOS:
   • create role
   • create permission
   • create user
   • create parametros
   • create elcore
   • update role
   • update permission
   • update user
   • update parametros
   • update elcore
   • read role
   • read permission
   • read user
   • read parametros
   • read elcore
   • delete role
   • delete permission
   • delete user
   • delete parametros
   • delete elcore

🔐 PERMISOS VÍA ROLES:
   • istrabajador
   • isadmin
   • issuperadmin
*/