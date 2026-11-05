### Archivo 1: Ficha Técnica de Materiales 

> **Relación:** `1 Producto : N Materiales`. Un producto aparece en tantas filas como materiales o insumos consuma en su fabricación.

#### Columnas del Archivo

| # | Columna | Obligatorio | Tipo de Dato | Descripción y Reglas de Negocio | Ejemplos |
|---|---|:---:|:---:|---|---|
| 1 | `codigo_interno_producto` | **Sí** | Texto | Código único del producto ya existente en el sistema. | `CAL-BOT-01`, `PROD-0045` |
| 2 | `codigo_material` | **Sí** | Texto | Código del material en el catálogo de inventario (formato `CAT-BASE-N`). Si no se cuenta con el código, se puede usar `nombre_material`, pero se recomienda el código para evitar ambigüedades. | `TEX-LFI-1`, `SUE-HUL-12`, `HER-HEB-3` |
| 3 | `consumo_estandar` | **Sí** | Decimal (`> 0`) | Cantidad exacta que consume **una unidad** (un par o prenda). Usar punto decimal. | `0.45`, `1.00`, `2`, `0.08` |
| 4 | `unidad_medida` | **Sí** | Texto | Unidad en la que se cuantifica el consumo. Debe coincidir con las unidades del sistema. | `Metros`, `Pares`, `Unidades`, `Gramos`, `Conos` |
| 5 | `costo_unitario_teorico` | No | Decimal | Costo unitario proyectado del insumo al momento de costear la ficha. Si se deja vacío, el sistema puede tomar automáticamente el costo vigente registrado en el módulo de materiales. | `15000`, `4200.50`, `800` |
| 6 | `aporta` | No | Texto | solo puede ir dos opciones Macondas o El nombre de la empresaexterna o taller. | `Macondas`, `EmpresaX` |
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
| 1 | `codigo_interno_producto` | **Sí** | Texto | Código único del producto al que se le asignará la operación. | `CAL-BOT-01`, `PROD-0045` |
| 2 | `nombre_proceso` | **Sí** | Texto | Nombre de la labor u operación técnica a realizar. | `Corte de piezas`, `Desbaste y doblado`, `Guarnición / Costura`, `Montado y Ensamble`, `Empaque y Limpieza` |
| 3 | `coste_proceso` | **Sí** | Decimal (`>= 0`) | Tarifa o costo de mano de obra pagado **por cada unidad/par** fabricado. Sin signos de moneda (`$`) ni separadores de miles. | `2500`, `4500`, `12000`, `800` |

estos 3 valores deben coincidir con el fillable del modelo

protected $fillable = ['nombre', 'producto_id', 'coste_proceso'];
