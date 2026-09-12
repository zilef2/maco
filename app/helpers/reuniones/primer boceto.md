# 📦 Sistema de Producción, Inventarios y Costos — Macondas

> **Primera entrega funcional**
> Documento maestro de alcance, módulos, reglas de negocio y resultados esperados.

---

## 🎯 Objetivo

El objetivo inicial del software es permitir a **Macondas**:

- Registrar y controlar las **materias primas** utilizadas en producción.
- Conocer sus **costos**.
- Controlar el **inventario general** de materia prima y producto terminado.
- Registrar los **consumos de producción**.
- Calcular el **costo estimado y real** de cada par de zapatos.

> 💡 **En cualquier momento** el sistema deberá responder:
> qué materiales hay, cuánto cuestan, cuánto material está en proceso, cuánto se ha utilizado, cuánto producto terminado existe y cuánto cuesta producir cada referencia.

---

## 🧭 Alcance de la primera versión

### 🧱 Registros base

- [x] Registro de **materias primas**: tela, sesgos, suelas, forros, badana, pines, hebillas, marquillas y demás insumos.
- [x] Registro de **proveedores**.
- [x] Registro de **compras** con:
  - Fecha
  - Cantidad
  - Unidad de medida
  - Valor total
  - Costo unitario

### 💰 Costeo de materiales

- [x] Manejo de **costo promedio ponderado** cuando un mismo material se compra a diferentes precios.
- [x] **Acumulación de costos** sobre materiales procesados.

> **Ejemplo de acumulación:**
> `Tela + embarretado + sublimación = costo de la tela procesada`

### 📊 Inventario

- [x] Control de **inventario general** de materias primas.
- [x] **Registro automático** de entradas y salidas.
- [x] **Historial de movimientos** para conocer por qué aumentó o disminuyó una existencia.
- [x] Registro de materias primas **temporalmente fuera** de Macondas por procesos externos.
- [x] Registro de **servicios externos**: embarretado, sublimación y fabricación en taller.

### 🧵 Consumos y producción

- [x] Creación de una **ficha de consumo** para cada referencia.
- [x] Registro del **consumo promedio por par** de cada materia prima.
- [x] Cálculo del **consumo estándar**:

```text
Consumo estándar = Cantidad de material utilizada ÷ Cantidad de pares producidos
```

- [x] Posibilidad de incluir un **porcentaje de merma** en determinados materiales.
- [x] Registro de **producciones realizadas**.
- [x] Registro del **consumo real** de materiales de cada producción.
- [x] **Comparación** entre consumo estándar y consumo real.

### 🏷️ Producto terminado

- [x] Registro de **entrada de producto terminado** cuando el taller entrega los zapatos.
- [x] Control de inventario de producto terminado por **referencia y talla**.
- [x] Registro de **salidas** de producto terminado.
- [x] **Historial de movimientos** del producto terminado.

### 📈 Reportes

- [x] **Reporte mensual de consumos y desviaciones**.
- [x] Comparación **consumo estándar vs. consumo real**, indicando:
  - Diferencias en cantidad
  - Porcentaje
  - Valor económico

---

## 🗂️ Manejo del inventario

El sistema deberá manejar inicialmente **tres estados principales**:

| # | Estado | Descripción |
|---|--------|-------------|
| 1 | 🟢 **Materia prima disponible** | Material disponible para producción en Macondas. |
| 2 | 🟡 **Materia prima en proceso** | Material propiedad de Macondas que está temporalmente donde un proveedor realizando algún proceso. |
| 3 | 🔵 **Producto terminado** | Zapatos recibidos del taller y disponibles como producto terminado. |

### 🔄 Flujo de proceso externo

```text
Tela disponible → embarretado → sublimación → tela procesada disponible para producción
```

> ⚠️ **Importante:** aunque el material se encuentre físicamente donde un proveedor, **deberá continuar apareciendo dentro del inventario de Macondas** con el estado correspondiente.

### 👟 Inventario de producto terminado

Deberá manejar como mínimo:

```text
Referencia + Talla + Cantidad
```

---

## 🔁 Movimientos de inventario

> 🚫 El sistema **no deberá permitir** modificar manualmente una cantidad existente sin dejar trazabilidad.
> ✅ **Cada cambio deberá generar un movimiento.**

### 🧾 Movimientos de materia prima

- Compra
- Salida a proceso externo
- Regreso de proceso externo
- Entrega a producción
- Devolución
- Pérdida
- Ajuste de inventario

### 👟 Movimientos de producto terminado

- Entrada de producción
- Venta / salida
- Devolución
- Cambio
- Muestra
- Avería
- Ajuste de inventario

### 📌 Datos mínimos de cada movimiento

| Campo | Descripción |
|-------|-------------|
| 📅 Fecha | Cuándo ocurrió el movimiento |
| 🧱 Material o producto | Qué se movió |
| 🔢 Cantidad | Cuánto se movió |
| 🔄 Tipo de movimiento | Naturaleza del movimiento |
| 👤 Responsable | Quién lo registró |
| 📝 Observaciones | Notas adicionales |

---

## 💵 Costeo

> En esta primera etapa el costeo se realizará utilizando **consumos promedio por par**, sin diferenciar inicialmente el consumo por talla.

### 🧮 Fórmula del costo estimado

```text
Materias primas + Procesos externos + Servicio de fabricación en taller = Costo estimado de producción
```

Posteriormente, el sistema deberá poder **compararlo con el costo real** obtenido a partir de los consumos registrados en producción.

---

## 📊 Reporte mensual de eficiencia

Desde esta primera versión se deberá poder consultar mensualmente:

- [x] Pares producidos
- [x] Material consumido
- [x] Consumo estándar esperado
- [x] Consumo real
- [x] Diferencia de consumo
- [x] Diferencia porcentual
- [x] Valor económico de la diferencia
- [x] Materiales con mayores desviaciones

> 🎯 Esto permitirá identificar **desperdicios**, **diferencias de producción** o **consumos superiores** a los establecidos.

---

## ✅ Resultado esperado de esta primera entrega

Al finalizar esta primera etapa, Macondas deberá poder responder fácilmente:

| Pregunta | ✔️ |
|----------|----|
| ¿Qué materias primas tenemos? | ✅ |
| ¿Cuánto nos costaron? | ✅ |
| ¿Dónde se encuentran actualmente? | ✅ |
| ¿Qué materiales están en procesos externos? | ✅ |
| ¿Cuánto debería consumir cada referencia? | ✅ |
| ¿Cuánto consumió realmente una producción? | ✅ |
| ¿Cuánto cuesta producir cada par? | ✅ |
| ¿Cuántos pares terminados tenemos por referencia y talla? | ✅ |
| ¿Qué entradas y salidas ha tenido cada inventario? | ✅ |
| ¿Dónde se están presentando mayores desperdicios o desviaciones? | ✅ |

---

## 🚀 Visión de futuro

> El sistema deberá quedar construido de forma **modular y flexible** para incorporar posteriormente funciones más avanzadas como:

- 🛒 Órdenes de compra
- 🗓️ Planeación de producción
- 🏬 Múltiples bodegas
- 📷 Códigos de barras
- 🛍️ Integración con Shopify
- 🏭 Control de talleres
- 💹 Reportes financieros

---

## 🧩 Resumen visual del flujo

```text
┌─────────────────────┐
│  Materia prima      │
│  disponible         │
└─────────┬───────────┘
          │
          ▼
┌─────────────────────┐
│  Proceso externo    │
│  (embarretado,      │
│   sublimación,      │
│   taller)           │
└─────────┬───────────┘
          │
          ▼
┌─────────────────────┐
│  Materia prima      │
│  procesada          │
└─────────┬───────────┘
          │
          ▼
┌─────────────────────┐
│  Producción         │
│  (consumo real)     │
└─────────┬───────────┘
          │
          ▼
┌─────────────────────┐
│  Producto terminado │
│  (referencia+talla) │
└─────────────────────┘
```

---

> 📌 **Nota final:** este documento corresponde a la **primera entrega** del sistema. Cualquier funcionalidad no listada aquí se considerará parte de **fases posteriores**.