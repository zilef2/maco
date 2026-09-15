# Primera entrega – Sistema de Producción, Inventarios y Costos Macondas

---

## 🎯 Objetivo

El objetivo inicial del software es permitir a Macondas registrar y controlar las materias primas utilizadas en producción, conocer sus costos, controlar el inventario general de materia prima y producto terminado, registrar los consumos de producción y calcular el costo estimado y real de cada par de zapatos.

El sistema deberá permitir conocer en cualquier momento qué materiales tiene Macondas, cuánto cuestan, cuánto material está en proceso, cuánto se ha utilizado, cuánto producto terminado existe y cuánto está costando producir cada referencia.

---

## 📦 Alcance de la primera versión

El sistema deberá incluir:

- Registro de materias primas: tela, sesgos, suelas, forros, badana, pines, hebillas, marquillas y demás insumos.
- Registro de proveedores.
- Registro de compras con fecha, cantidad, unidad de medida, valor total y costo unitario.
- Manejo de costo promedio ponderado cuando un mismo material se compre a diferentes precios.
- Control de inventario general de materias primas.
- Registro automático de entradas y salidas de inventario.
- Historial de movimientos para conocer por qué aumentó o disminuyó una existencia.
- Registro de materias primas que se encuentren temporalmente fuera de Macondas por procesos externos.
- Registro de servicios externos como embarretado, sublimación y fabricación en taller.
- Acumulación de costos sobre materiales procesados. Por ejemplo:

  > Tela + embarretado + sublimación = costo de la tela procesada.

- Creación de una ficha de consumo para cada referencia.
- Registro del consumo promedio por par de cada materia prima.
- Cálculo del consumo estándar mediante:

  > Cantidad de material utilizada ÷ cantidad de pares producidos.

- Posibilidad de incluir un porcentaje de merma en determinados materiales.
- Registro de producciones realizadas.
- Registro del consumo real de materiales de cada producción.
- Comparación entre consumo estándar y consumo real.
- Cálculo automático del costo estimado por par.
- Cálculo del costo real de producción.
- Registro de entrada de producto terminado cuando el taller entrega los zapatos.
- Control de inventario de producto terminado por referencia y talla.
- Registro de salidas de producto terminado.
- Historial de movimientos del producto terminado.
- Reporte mensual de consumos y desviaciones.
- Reporte mensual que permita comparar consumo estándar frente a consumo real, indicando diferencias en cantidad, porcentaje y valor económico.

---

## 🗃️ Manejo del inventario

El sistema deberá manejar inicialmente tres estados principales:

### 1. Materia prima disponible

Material que se encuentra disponible para producción en Macondas.

### 2. Materia prima en proceso

Material propiedad de Macondas que se encuentra temporalmente donde un proveedor realizando algún proceso.

> **Ejemplo:**
>
> Tela disponible → embarretado → sublimación → tela procesada disponible para producción.

Aunque el material se encuentre físicamente donde un proveedor, deberá continuar apareciendo dentro del inventario de Macondas con el estado correspondiente.

### 3. Producto terminado

Zapatos recibidos del taller y disponibles como producto terminado.

El inventario de producto terminado deberá manejar como mínimo:

> **Referencia + talla + cantidad.**

---

## 🔄 Movimientos de inventario

El sistema no deberá permitir simplemente modificar manualmente una cantidad existente sin dejar trazabilidad.

Cada cambio deberá generar un movimiento.

**Ejemplos de movimientos de materia prima:**

> Compra · salida a proceso externo · regreso de proceso externo · entrega a producción · devolución · pérdida · ajuste de inventario.

**Ejemplos de movimientos de producto terminado:**

> Entrada de producción · venta/salida · devolución · cambio · muestra · avería · ajuste de inventario.

Cada movimiento deberá registrar como mínimo:

> **Fecha · material o producto · cantidad · tipo de movimiento · responsable · observaciones.**

---

## 💰 Costeo

En esta primera etapa el costeo se realizará utilizando consumos promedio por par, sin diferenciar inicialmente el consumo por talla.

El costo estimado del par deberá contemplar:

> Materias primas + procesos externos + servicio de fabricación en taller = costo estimado de producción.

Posteriormente el sistema deberá poder compararlo con el costo real obtenido a partir de los consumos registrados en producción.

---

## 📊 Reporte mensual de eficiencia

Desde esta primera versión se deberá poder consultar mensualmente:

- Pares producidos.
- Material consumido.
- Consumo estándar esperado.
- Consumo real.
- Diferencia de consumo.
- Diferencia porcentual.
- Valor económico de la diferencia.
- Materiales con mayores desviaciones.

Esto permitirá identificar desperdicios, diferencias de producción o consumos superiores a los establecidos.

---

## ✅ Resultado esperado de esta primera entrega

Al finalizar esta primera etapa, Macondas deberá poder responder fácilmente:

- ¿Qué materias primas tenemos?
- ¿Cuánto nos costaron?
- ¿Dónde se encuentran actualmente?
- ¿Qué materiales están en procesos externos?
- ¿Cuánto debería consumir cada referencia?
- ¿Cuánto consumió realmente una producción?
- ¿Cuánto cuesta producir cada par?
- ¿Cuántos pares terminados tenemos por referencia y talla?
- ¿Qué entradas y salidas ha tenido cada inventario?
- ¿Dónde se están presentando mayores desperdicios o desviaciones?

El sistema deberá quedar construido de forma modular y flexible para incorporar posteriormente funciones más avanzadas como órdenes de compra, planeación de producción, múltiples bodegas, códigos de barras, integración con Shopify, control de talleres y reportes financieros.


Fin Liliana M.
---
---
---
---
---
---
---
---
---
---
---
---
---
---
---
---
---
---

# 📅 Cronograma de Fechas Importantes – Primera Entrega  
## Sistema de Producción, Inventarios y Costos Macondas

> **Nota:** Las siguientes fechas son una propuesta estimada con base en un desarrollo típico de software. Se asume una duración total de **12 semanas** para la primera entrega, con reuniones semanales de seguimiento y entregas parciales por módulo.

---

## 🗓️ Resumen General del Proyecto

| Fase | Descripción | Duración estimada |
|------|-------------|-------------------|
| Fase 0 | Levantamiento de información y acuerdos iniciales | 1 semana |
| Fase 1 | Diseño y modelado de datos | 2 semanas |
| Fase 2 | Desarrollo de módulo de materias primas y proveedores | 2 semanas |
| Fase 3 | Desarrollo de compras, costos e inventario | 2 semanas |
| Fase 4 | Desarrollo de procesos externos y costos acumulados | 1.5 semanas |
| Fase 5 | Desarrollo de fichas de consumo y producción | 2 semanas |
| Fase 6 | Desarrollo de producto terminado y movimientos | 1.5 semanas |
| Fase 7 | Reportes mensuales y desviaciones | 1.5 semanas |
| Fase 8 | Pruebas integrales, ajustes y capacitación | 1.5 semanas |
| Fase 9 | Entrega final y cierre de primera versión | 1 semana |

---

## 📌 Fechas Clave (Asumiendo inicio el lunes 6 de octubre de 2025)

| # | Hito / Reunión | Fecha estimada | Responsable | Entregable |
|---|----------------|----------------|-------------|------------|
| 1 | **Reunión de kickoff** | Lunes 6 de octubre de 2025 | Líder de proyecto + Macondas | Acta de inicio y acuerdos |
| 2 | **Entrega de levantamiento de requerimientos** | Viernes 10 de octubre de 2025 | Analista funcional | Documento de requerimientos validado |
| 3 | **Reunión de validación de modelo de datos** | Viernes 17 de octubre de 2025 | Arquitecto de software | Diagrama entidad-relación aprobado |
| 4 | **Demo módulo de materias primas y proveedores** | Viernes 31 de octubre de 2025 | Desarrollador | Prototipo funcional |
| 5 | **Demo módulo de compras, costos e inventario** | Viernes 14 de noviembre de 2025 | Desarrollador | Prototipo funcional |
| 6 | **Reunión de revisión de procesos externos** | Miércoles 19 de noviembre de 2025 | Analista + Macondas | Validación de flujo de procesos externos |
| 7 | **Demo módulo de fichas de consumo y producción** | Viernes 28 de noviembre de 2025 | Desarrollador | Prototipo funcional |
| 8 | **Demo módulo de producto terminado y movimientos** | Viernes 5 de diciembre de 2025 | Desarrollador | Prototipo funcional |
| 9 | **Demo reportes mensuales y desviaciones** | Viernes 12 de diciembre de 2025 | Desarrollador | Reportes funcionales |
| 10 | **Reunión de pruebas integrales (QA)** | Miércoles 17 de diciembre de 2025 | QA + Macondas | Plan de pruebas ejecutado |
| 11 | **Capacitación a usuarios de Macondas** | Lunes 22 de diciembre de 2025 | Capacitador | Manual de usuario + sesión |
| 12 | **Entrega final primera versión** | Viernes 26 de diciembre de 2025 | Líder de proyecto | Sistema en producción |

---

## 🔁 Reuniones de Seguimiento

| Tipo de reunión | Frecuencia | Día | Duración estimada | Participantes |
|-----------------|------------|-----|-------------------|---------------|
| Daily interno (equipo desarrollo) | Diaria | Lunes a viernes | 15 min | Equipo técnico |
| Seguimiento con Macondas | Semanal | Viernes | 1 hora | Líder proyecto + Macondas |
| Revisión de avances por módulo | Quincenal | Miércoles | 1.5 horas | Analista + Macondas |
| Comité de cambios | Según necesidad | — | 1 hora | Líder proyecto + Macondas |

---

## 📆 Calendario Visual (Resumen)

```
Octubre 2025
├── Semana 1 (6–10 oct)   → Kickoff + Levantamiento
├── Semana 2 (13–17 oct)  → Modelo de datos
├── Semana 3 (20–24 oct)  → Desarrollo materias primas
└── Semana 4 (27–31 oct)  → Demo materias primas y proveedores

Noviembre 2025
├── Semana 5 (3–7 nov)    → Desarrollo compras y costos
├── Semana 6 (10–14 nov)  → Demo compras, costos e inventario
├── Semana 7 (17–21 nov)  → Procesos externos y costos acumulados
├── Semana 8 (24–28 nov)  → Demo fichas de consumo y producción
└── Semana 9 (1–5 dic)    → Producto terminado y movimientos

Diciembre 2025
├── Semana 10 (8–12 dic)  → Reportes mensuales y desviaciones
├── Semana 11 (15–19 dic) → Pruebas integrales y ajustes
├── Semana 12 (22–26 dic) → Capacitación + Entrega final
```

---

## ⚠️ Supuestos y Consideraciones

- El proyecto inicia el **lunes 6 de octubre de 2025**.
- Se asume disponibilidad de Macondas para reuniones semanales los viernes.
- Los tiempos pueden variar según la complejidad de los ajustes solicitados.
- Las fechas de diciembre pueden verse afectadas por la temporada de fin de año; se recomienda validar disponibilidad del equipo.
- Cualquier cambio de alcance será gestionado a través del **comité de cambios**.

---

## 📌 Hitos Críticos (No negociables)

| Hito | Fecha límite | Impacto si se retrasa |
|------|--------------|------------------------|
| Validación de modelo de datos | 17 de octubre de 2025 | Retrasa todos los módulos |
| Demo de compras e inventario | 14 de noviembre de 2025 | Afecta costeo y reportes |
| Demo de fichas de consumo | 28 de noviembre de 2025 | Afecta producción y producto terminado |
| Entrega final | 26 de diciembre de 2025 | Cierre de primera versión |

---

¿Quieres que agregue **responsables por nombre**, **dependencias entre tareas** o un **diagrama de Gantt** en formato texto?