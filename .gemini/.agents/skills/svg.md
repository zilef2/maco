# Tailwind CSS — Skeleton Loaders

## Objetivo
Crear indicadores de carga (*skeleton loaders*) utilizando HTML y Tailwind CSS, sin depender de SVG, JavaScript ni librerías adicionales.

## Reglas de implementación

1. Utilizar elementos HTML (`div`) como placeholders.
2. Aplicar `animate-pulse` al contenedor principal para generar la animación.
3. Utilizar fondos grises (`bg-gray-200`, `bg-gray-300`) o gradientes.
4. Simular el contenido real mediante bloques de diferentes tamaños y dimensiones.
5. Utilizar `rounded-full` para líneas y avatares, y `rounded-lg` para tarjetas y botones.
6. Mantener las proporciones y estructura del contenido que se está cargando.
7. No utilizar SVG ni JavaScript para loaders sencillos.
8. No instalar dependencias adicionales si Tailwind ya está disponible.
9. solo usar esta skill en caso que se requiera especificamente "un indicador de carga"

## Ejemplo básico

```html
<div class="animate-pulse space-y-4">
  <!-- Heading -->
  <div class="h-5 w-3/4 rounded bg-gray-200"></div>

  <!-- Text -->
  <div class="space-y-2">
    <div class="h-3 w-full rounded bg-gray-200"></div>
    <div class="h-3 w-5/6 rounded bg-gray-200"></div>
    <div class="h-3 w-2/3 rounded bg-gray-200"></div>
  </div>

  <!-- Avatar -->
  <div class="flex items-center gap-3">
    <div class="h-10 w-10 rounded-full bg-gray-200"></div>
    <div class="h-3 w-24 rounded bg-gray-200"></div>
  </div>
</div>
```

## Tipos de skeleton

- **Texto:** líneas de diferentes anchos y alturas.
- **Avatar:** círculos con `rounded-full`.
- **Tarjetas:** bloques que replican la estructura de una card.
- **Listas:** repetir placeholders con la misma estructura del elemento.
- **Imágenes:** bloques con dimensiones equivalentes a la imagen real.

## Criterios UX

- El skeleton debe anticipar visualmente el contenido final.
- Evitar pantallas completamente vacías durante la carga.
- Mantener dimensiones similares para evitar saltos de layout (CLS).
- Usar animaciones sutiles, sin distraer.
- Respetar `prefers-reduced-motion` cuando sea posible.

## Instrucción para la IA

Cuando implementes estados de carga en un proyecto que utiliza Tailwind CSS, prioriza skeleton loaders construidos con HTML y clases utilitarias. Replica la estructura del contenido real y evita agregar dependencias innecesarias.

No reemplaces SVG en todos los casos: utiliza SVG cuando la complejidad visual o los requisitos del proyecto lo justifiquen.

## Referencia
https://dev.to/vic_makes_ai/you-dont-need-svg-creating-animated-loaders-for-content-with-tailwind-css-2cf0