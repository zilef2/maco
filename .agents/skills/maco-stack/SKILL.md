---
name: maco-stack
description: >-
  Stack de este proyecto: Laravel + Vue 3 + Inertia.js + Tailwind + Vite.
  Usar cuando se genera codigo nuevo (controladores, servicios, repos, vistas, tests).
  Contiene reglas de arquitectura, patrones, convenciones y ejemplos concretos de este proyecto.
---

# Stack MACO — Guia rapida

## Stack
- **Backend:** Laravel (ultima estable), PHP 8.2+
- **Frontend:** Vue 3 + Inertia.js, `<script setup>`, `reactive()`
- **Estilos:** Tailwind CSS (no CSS plano)
- **Auth/Roles:** RBAC con `Myhelp::EscribirEnLog()` + Spatie Permissions

---

## Arquitectura backend

```
app/Http/Controllers/   -> delgado, max 5 lineas/metodo
app/Services/           -> logica de negocio
app/Repositories/       -> acceso a datos (Eloquent encapsulado)
app/Http/Requests/      -> toda validacion va aqui
app/helpers/Myhelp.php  -> autorizacion, logging, utilidades
```

### Flujo estandar
```php
// Controller
public function store(StoreXxxRequest $request): RedirectResponse
{
    $this->xxxService->create($request->validated());
    return back()->with('success', __('app.label.created_successfully', ['name' => ...]));
}

// Service
public function create(array $data): Xxx
{
    $model = $this->xxxRepository->create($data);
    return $model;
}

// Repository
public function create(array $data): Xxx
{
    return Xxx::create($data);
}
```

### Autorizacion con Myhelp
```php
// SIEMPRE en primer lugar del metodo con $returnPermission=true
$permissions = Myhelp::EscribirEnLog($this, 'descripcion');

// Logs informativos (no verifican permisos) usan false
Myhelp::EscribirEnLog($this, 'STORE EXITOSO', 'id:'.$model->id, false);

// IMPORTANTE: cuando $returnPermission=false, $permissions=null, NO abortar
// Bug corregido: se agrego "$returnPermission &&" en la condicion de abort
```

---

## Patrones Vue 3

### Estado siempre con reactive(), NUNCA ref()
```vue
<script setup>
import { reactive } from 'vue'
const state = reactive({
    loading: false,
    form: { nombre: '', estado: '' },
})
</script>
```

### Formularios Inertia
```vue
const form = useForm({ nombre: '', estado: '' })
form.post(route('xxx.store'), {
    preserveScroll: true,
    onSuccess: () => { form.reset(); emit('close') },
})
```

### Props siempre tipadas
```vue
const props = defineProps({
    items: { type: Array, required: true },
    isSuperadmin: { type: Boolean, default: false },
})
```

---

## Convenciones

| Cosa | Convencion |
|------|-----------|
| Clases PHP | PascalCase |
| Metodos PHP | camelCase |
| Rutas URL | kebab-case |
| Columnas BD | snake_case |
| Componentes Vue | PascalCase (UserCard.vue) |
| Paginas | resources/js/pages/<Modulo>/Index.vue |
| Componentes | resources/js/components/ |

---

## Estructura de paginas

```
resources/js/pages/<Modulo>/
├── Index.vue      <- lista principal + modales integrados
├── Create.vue     <- modal de creacion
├── Edit.vue       <- modal de edicion
├── Delete.vue     <- modal de confirmacion
└── DeleteBulk.vue <- modal borrado masivo
```

---

## Listas desplegables (ListaDesplegable)

Tabla `listas_desplegables`: campos `nombre`, `categoria`, `subcategoria`.
Solo superadmin puede crear. Solo lectura para los demas.
Se muestran en la vista de Parametros agrupadas por categoria->subcategoria.

```php
$listasDesplegables = $this->listaDesplegableService->getOptionsByCategoriaGrouped('presupuesto');
// Resultado: ['estado' => [...], 'motivo_reajuste' => [...]]
```

---

## Tests

- Unit tests en tests/Unit/ para Services y Repositories
- Usar RefreshDatabase + factories
- Nombres en snake_case describiendo comportamiento
- SIEMPRE generar test al crear clase backend
- Generar o actualizar el archivo ResumenTestImplementados.md con la explicacion que hace cada test en pocas palabras

```php
it('creates xxx and dispatches job', function () {
    Queue::fake();
    $data = Xxx::factory()->make()->toArray();
    // assertions...
});
```

---

## Rutas tipicas

```php
Route::resource('/Modulo', ModuloController::class);
Route::post('/Modulo/destroy-bulk', [ModuloController::class, 'destroyBulk'])->name('modulo.destroy-bulk');
```

---

## Permisos y roles

Roles principales: superadmin, admin, usuario normal.

```php
$permissions = Myhelp::EscribirEnLog($this, 'contexto');
abort_unless($permissions === 'superadmin', 403, 'Solo superadmin.');
```

En Vue usar can(['create xxx']) para mostrar/ocultar elementos.
