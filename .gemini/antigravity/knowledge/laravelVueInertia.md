Laravel + PHP 8, Vue 3 + Inertia.js, Tailwind CSS, Vite.

## Reglas generales
- Solo Tailwind, nunca CSS suelto.
- Sin comentarios en el código.
- Ante dudas de arquitectura, preguntar antes de asumir.

## Laravel
- **Service/Repository pattern obligatorio**: `app/Services/` (negocio), `app/Repositories/` (Eloquent encapsulado).
- Controladores delgados (≤5 líneas por método), solo llaman servicios.
- Sin consultas Eloquent fuera del Repository.
- no comentar cada linea, pero si un metodo entero

## Vue 3
- Siempre `<script setup>` + Composition API.
- **Nunca `ref()`** — usar `reactive()` para todo el estado.
- Props/emits siempre explícitos con `defineProps` / `defineEmits`.
- Un componente = una responsabilidad.
- Inertia:
  - `useForm()` solo si hay submit a Laravel; si no, `reactive()` puro.
  - Navegación con `<Link>` o `router.visit()`, nunca `<a href>`.
- Estructura: páginas en `resources/js/Pages/`, componentes en `resources/js/Components/`, PascalCase.

## Prohibido
- Eloquent fuera de Repositories.
- Componentes sin `<script setup>`.