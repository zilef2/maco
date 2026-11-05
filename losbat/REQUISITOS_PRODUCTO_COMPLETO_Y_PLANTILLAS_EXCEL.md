# Guía de Definición de Producto Completo y Plantillas Excel de Carga

Este documento detalla qué componentes y datos son indispensables para que un producto se considere **100% completo y listo para producción** en Macondas, y especifica la estructura y columnas exactas que deben tener los **2 archivos de Excel** para cargar de forma masiva su **Ficha Técnica de Materiales** y sus **Procesos de Operación**.

---

## 1. ¿Qué hace falta para que un producto esté completo?

Tu apreciación es **completamente acertada**. En el flujo productivo de Macondas, un producto registrado solo con sus datos básicos (código, título de Shopify y categoría) es un "cascarón". Para poder planificar y costear una **Orden de Producción (OP)** real, el producto necesita tres capas integradas:

```
┌────────────────────────────────────────────────────────────────────────┐
│                          PRODUCTO COMPLETO                             │
└──────────────────────────────────┬─────────────────────────────────────┘
                                   │
      ┌────────────────────────────┼────────────────────────────┐
      ▼                            ▼                            ▼
┌──────────────────┐     ┌───────────────────┐     ┌─────────────────────┐
│ 1. Datos Base    │     │ 2. Ficha Técnica  │     │ 3. Procesos (Ruta)  │
│    y Tallas      │     │    de Materiales  │     │    de Producción    │
├──────────────────┤     ├───────────────────┤     ├─────────────────────┤
│ • Código interno │     │ • Materiales/BOM  │     │ • Etapas de trabajo │
│ • Título/Familia │     │ • Consumo estándar│     │ • Coste por prenda  │
│ • Rango de tallas│     │ • Unidad de medida│     │ • Secuencia de paso │
│ • Costo y precio │     │ • Costo teórico   │     │ • Taller o satélite │
└──────────────────┘     └───────────────────┘     └─────────────────────┘
```

### Capa 1: Datos Base y Tallas (Estado actual del catálogo)
* **Identificación:** `Codigo_interno` único y `Titulo_Shopify`.
* **Variantes/Tallas:** Selección del `Control_de_variante` (Alfabética como S, M, L o Numérica como 35–42) y el detalle de las tallas que aplican.
* **Costeo y Precios de Catálogo:** `costo_anticipado` (estimado de desarrollo) y `precio_venta_producto`.
* **Especificaciones:** `descripcion` e `instrucciones` de armado o cuidados.

### Capa 2: Ficha Técnica de Materiales (`ficha_tecnica_materiales`) — *Indispensable*
Es la **receta o Bill of Materials (BOM)** del producto por unidad (par de calzado o prenda).
* **Por qué es obligatoria:**
  * Sin ella, el sistema **no puede hacer la explosión de materiales** al crear una OP (no se sabe cuántos metros de tela, cuero, suelas, forros, herrajes o hilos se requieren ni qué descontar o apartar del inventario).
  * No se puede calcular el **costo real ni teórico de materiales** (`costo_material`) de la orden.
  * No se pueden armar los **kits de entrega** para despachar insumos a talleres satélite ni a empresas de procesos externos.

### Capa 3: Procesos de Operación (`procesoops`) — *Indispensable*
Es la **ruta de confección / ensamble** que define las etapas por las que pasa el producto y lo que cuesta cada labor.
* **Por qué es obligatoria:**
  * Define la mano de obra unitaria: Corte, Sublimación, Bordado, Desbaste, Embarretado, Ensamble, Empaque, etc.
  * Al ingresar el producto a una OP, la suma del `coste_proceso` de cada operación multiplicada por la cantidad de unidades define el **costo total de mano de obra/procesos** de la producción.
  * Sin los procesos, la OP no sabe a qué tipo de taller o empresa externa enviar cada etapa ni cuánto pagar por unidad producida.

---

## 2. Especificación de los 2 Archivos de Excel

Para completar masivamente los productos sin tener que entrar a editarlos uno a uno en la plataforma, se plantean dos plantillas de Excel independientes:

---

### Archivo 1: Ficha Técnica de Materiales (`Ficha_Tecnica_Materiales.xlsx`)

> **Relación:** `1 Producto : N Materiales`. Un producto aparece en tantas filas como materiales o insumos consuma en su fabricación.

#### Columnas del Archivo

| # | Columna | Obligatorio | Tipo de Dato | Descripción y Reglas de Negocio | Ejemplos |
|---|---|:---:|:---:|---|---|
| 1 | `codigo_interno` | **Sí** | Texto | Código único del producto ya existente en el sistema. | `CAL-BOT-01`, `PROD-0045` |
| 2 | `codigo_material` | **Sí** | Texto | Código del material en el catálogo de inventario (formato `CAT-BASE-N`). Si no se cuenta con el código, se puede usar `nombre_material`, pero se recomienda el código para evitar ambigüedades. | `TEX-LFI-1`, `SUE-HUL-12`, `HER-HEB-3` |
| 3 | `consumo_estandar` | **Sí** | Decimal (`> 0`) | Cantidad exacta que consume **una unidad** (un par o prenda). Usar punto decimal. | `0.45`, `1.00`, `2`, `0.08` |
| 4 | `unidad_medida` | **Sí** | Texto | Unidad en la que se cuantifica el consumo. Debe coincidir con las unidades del sistema. | `Metros`, `Pares`, `Unidades`, `Gramos`, `Conos` |
| 5 | `costo_unitario_teorico` | No | Decimal | Costo unitario proyectado del insumo al momento de costear la ficha. Si se deja vacío, el sistema puede tomar automáticamente el costo vigente registrado en el módulo de materiales. | `15000`, `4200.50`, `800` |
| 6 | `aporta` | No | Texto | Parte de la prenda/calzado donde se incorpora el material. Ayuda a producción y corte. | `Capellada`, `Forro interno`, `Suela`, `Vivo`, `Herraje frontal` |
| 7 | `estado_al_llegar_ensamble` | No | Texto | En qué condición debe encontrarse el componente al llegar a la mesa de ensamble. | `Cortado y desbastado`, `Sublimado`, `Listo para coser` |
| 8 | `proceso_previo` | No | Texto | Proceso o etapa anterior requerido antes de incorporarlo. | `Corte láser`, `Bordado`, `Ninguno` |
| 9 | `observaciones` | No | Texto | Instrucciones técnicas o precauciones particulares sobre el insumo. | `Material elástico al hilo`, `Revisar tono de lote`, `Usar pegante base solvente` |

#### Ejemplo de Filas en Excel

```csv
codigo_interno,codigo_material,consumo_estandar,unidad_medida,costo_unitario_teorico,aporta,estado_al_llegar_ensamble,proceso_previo,observaciones
CAL-BOT-01,CUE-VAC-1,0.35,Metros,32000,Corte exterior,Cortado,Corte,Tono miel uniforme
CAL-BOT-01,FOR-BAD-2,0.25,Metros,14000,Forro interior,Cortado,Corte,Sin arrugas
CAL-BOT-01,SUE-TRB-4,1.00,Pares,18500,Piso,Terminado,Ninguno,Talla según pedido
CAL-BOT-01,HER-CRE-1,2.00,Unidades,1200,Lateral interno,Listo,Ninguno,Cremallera 15cm
CAL-BOT-01,HIL-POL-5,0.02,Conos,4500,Pespunte,Listo,Ninguno,Hilo calibre 40
```

---

### Archivo 2: Procesos de Producción (`Procesos_Operacion.xlsx`)

> **Relación:** `1 Producto : N Procesos`. Un producto aparece en tantas filas como etapas de mano de obra/procesos requiera su ciclo productivo.

#### Columnas del Archivo

| # | Columna | Obligatorio | Tipo de Dato | Descripción y Reglas de Negocio | Ejemplos |
|---|---|:---:|:---:|---|---|
| 1 | `codigo_interno` | **Sí** | Texto | Código único del producto al que se le asignará la operación. | `CAL-BOT-01`, `PROD-0045` |
| 2 | `nombre_proceso` | **Sí** | Texto | Nombre de la labor u operación técnica a realizar. | `Corte de piezas`, `Desbaste y doblado`, `Guarnición / Costura`, `Montado y Ensamble`, `Empaque y Limpieza` |
| 3 | `coste_proceso` | **Sí** | Decimal (`>= 0`) | Tarifa o costo de mano de obra pagado **por cada unidad/par** fabricado. Sin signos de moneda (`$`) ni separadores de miles. | `2500`, `4500`, `12000`, `800` |
| 4 | `secuencia` | Recomendado | Entero (`1, 2, ...`) | Orden cronológico de la operación dentro de la línea de producción. Evita desorden en hojas de ruta. | `1`, `2`, `3`, `4`, `5` |
| 5 | `tipo_ejecucion` | No | Texto (`Interno` / `Externo`) | Indica si el proceso se ejecuta en taller propio/satélite de ensamble o si sale a un tercero especializado. | `Interno`, `Externo` |
| 6 | `taller_o_empresa_sugerida` | No | Texto | Nombre del taller satélite o proveedor externo habitual para este proceso (referencia informativa). | `Taller Don Pedro`, `Bordados Industriales S.A.S.` |
| 7 | `tiempo_estimado_minutos` | No | Entero / Decimal | Minutos promedio que toma elaborar el proceso por unidad (útil para balanceo de línea). | `15`, `45`, `10` |
| 8 | `observaciones` | No | Texto | Instrucciones de calidad o especificaciones técnicas para el operario o taller. | `Puntada 8 por pulgada`, `Curado térmico mínimo 10 min`, `Verificar simetría de puntera` |

#### Ejemplo de Filas en Excel

```csv
codigo_interno,nombre_proceso,coste_proceso,secuencia,tipo_ejecucion,taller_o_empresa_sugerida,tiempo_estimado_minutos,observaciones
CAL-BOT-01,Corte de cuero y forro,3500,1,Interno,Maco Planta,15,Corte manual siguiendo dirección del poro
CAL-BOT-01,Desbaste y pegado de refuerzos,2000,2,Interno,Maco Planta,10,Desbaste perimetral a 1.2mm
CAL-BOT-01,Guarnición y costura,8500,3,Externo,Taller Hermanos Gómez,40,Hilo al tono, pespunte doble
CAL-BOT-01,Montado y fijado de suela,9000,4,Externo,Taller Hermanos Gómez,35,Reactivación térmica previa
CAL-BOT-01,Acabado, limpieza y empaque,1500,5,Interno,Maco Planta,8,Limpiar exceso de adhesivo y colocar caja
```

---

## 3. Beneficios Inmediatos de Contar con Estos 2 Archivos

1. **Costeo Exacto del Producto:**
   $$\text{Costo Total Producto} = \sum (\text{Consumo} \times \text{Costo Material}) + \sum (\text{Coste Proceso})$$
   El sistema puede comparar este costo contra el `costo_anticipado` y contra el `precio_venta_producto` para calcular el margen bruto real de cada referencia.

2. **Creación Automática y Blindada de Órdenes de Producción (OP):**
   Al lanzar una OP de 200 pares del producto `CAL-BOT-01`:
   * Se multiplica el consumo estándar por 200 para reservar exactamente los metros de cuero, suelas y cremalleras.
   * Se calculan al instante los costos totales de talleres y maquilas.
   * Se pueden imprimir y entregar las fichas de ensamble y kits de materiales sin errores manuales.

3. **Bloqueo de Seguridad:**
   En el sistema Macondas, una vez que una OP inicia operaciones, la ficha técnica y los procesos se **bloquean para edición**. Cargar estos archivos de manera completa y previa garantiza que las órdenes de producción no queden con vacíos de costeo o recetas incompletas.

