# Semana 10 — Diseño para movilidad del módulo QA (320–430 px)

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | QA funcional, pruebas manuales y matriz de regresión (adaptación: gestión de campañas de regresión y defectos) |
| **Tarea** | Adaptar el flujo a móvil (320–430 px): jerarquía, navegación, formularios, tablas, confirmaciones y conexión limitada |
| **Entregable** | 3–5 pantallas responsive, reglas de breakpoint y dos escenarios móviles |

## Entregables

| Pedido | Dónde |
|---|---|
| 3–5 pantallas responsive | **4 pantallas HTML** en [prototipo/](prototipo/): m1 campañas, m2 ejecutar, m3 registro y bandeja, m4 trazabilidad. Cada estado se ve con `?estado=` |
| Reglas de breakpoint | [§3](#3-reglas-de-breakpoint) y [estilos.css](prototipo/estilos.css); 8 reglas transversales **verificadas** en 32 vistas |
| Dos escenarios móviles | [§6](#6-escenario-1--el-tester-registra-una-falla-con-señal-débil-360-px) y [§7](#7-escenario-2--el-líder-qa-revisa-qué-bloquea-el-go-camino-a-la-reunión-390-px) |

## 1. Por qué QA en el móvil

Un tester prueba el build **donde se usa**: en la estación de enfermería o junto al equipo del laboratorio, lejos de su escritorio. Allí la señal es débil. Por su parte, el Líder QA decide la liberación camino a una reunión.

Lo que el móvil debe resolver:
- registrar un resultado con una **foto** como evidencia;
- **no perderlo** sin red;
- ver **qué bloquea el GO** sin abrir una tabla de 5 columnas.

## 2. Jerarquía de contenido (de mayor a menor prioridad)

| Pantalla | 1 · siempre visible | 2 · en el primer pantallazo | 3 · desde 431 px o plegado |
|---|---|---|---|
| m1 Campañas | Hospital (alcance) y campaña en curso con sus pendientes | [Ejecutar el siguiente caso] | Funcionalidades, umbral |
| m2 Ejecutar | **Caso, versión y build**, fijos bajo la barra (R-P1) | Pasos con su esperado; resultado | Ayuda de severidad (plegable) |
| m3 Registro | Qué pasó: registrado / pendiente / rechazado | Bandeja de pendientes y [Reenviar ahora] | — |
| m4 Trazabilidad | Decisión que tendría hoy y sus causas | Casos con `⛔ Bloquea` / `No bloquea` y el motivo | Tabla completa desde 1024 px |

## 3. Reglas de breakpoint

Mobile-first: la base es **320 px** y cada breakpoint **agrega**.

| Breakpoint | Ancho | Qué cambia |
|---|---|---|
| **Base** | 320–359 | Una columna; el hospital en forma corta («H. Norte»); el resultado, en 2×2; acción principal fija abajo, al alcance del pulgar; navegación inferior solo con las secciones del rol |
| **S** | 360–430 | El nombre largo del hospital; «¿datos de pacientes?» en dos columnas |
| **M** | 431–767 | Las 4 opciones del resultado en una fila; aparecen los datos de prioridad 3 y el rol actual |
| **L** | 768–1023 | Navegación lateral; la hoja inferior pasa a ser un diálogo centrado |
| **XL** | ≥ 1024 | La trazabilidad vuelve a ser una tabla (W5 de la semana 8) |

### 3.1 Reglas transversales (verificadas)

[herramientas/verificar-responsive.cjs](herramientas/verificar-responsive.cjs) abre cada pantalla en **32 vistas** (320, 360, 390, 430, 768 y 1024 px; todos los estados), toma la captura y mide:

| Regla | Qué exige | Origen |
|---|---|---|
| R-W1 | Sin desplazamiento horizontal ni controles fuera del borde | Tarea (320 px) |
| R-T1 | Todo objetivo táctil ≥ 44 × 44 px, y en radios y casillas, su **etiqueta** | WCAG 2.5.8 (24 px), endurecido por el uso en movimiento |
| R-T2 | Campos con fuente ≥ 16 px (iOS no hace zoom al enfocar) | Móvil |
| R-A1 | El hospital (alcance) siempre visible en la barra | Semana 8, tenant |
| R-D1 | Ni contenido de evidencia ni datos del defecto en la URL | Semana 8 §8 |
| R-P1 | En «ejecutar», el caso, la versión y el build quedan pegados bajo la barra en el 25 % superior, **aun después de desplazarse** | W2-1 |
| R-B1 | Ningún control `disabled` | Semana 9 B-03 |
| R-S1 | Cada chip de estado lleva texto, no solo color o icono | 1.4.1, semana 9 B-11 |

**Resultado:** **32/32** ([verificacion-responsive-2-final.txt](evidencia/verificacion-responsive-2-final.txt)).

**¿Las reglas detectan algo?** Contraprueba: sobre una copia del prototipo se sembraron 4 defectos:
- radios de 30 px;
- la cabecera del caso no fija;
- «Registrar» con `disabled`;
- el chip «Bloquea» solo con icono.

El instrumento marca **16 vistas en falla** ([verificacion-responsive-control.txt](evidencia/verificacion-responsive-control.txt)).

**Hueco encontrado en el propio instrumento.** La primera versión de R-P1 medía la cabecera **sin desplazar** la pantalla. Arriba de todo, una cabecera estática queda igualmente bajo la barra, así que la contraprueba solo la detectó en el estado `error`, que sí se desplaza ([control-1-sin-desplazar](evidencia/verificacion-responsive-control-1-sin-desplazar.txt)). Ahora la regla desplaza 400 px antes de medir, y la cabecera no fija se detecta en todas las vistas de m2.

## 4. Navegación

- **Inferior, solo con las secciones del rol.** El Tester ve Campañas, Pendientes y Trazabilidad; el Líder QA, Campañas y Trazabilidad. Lo que el rol no puede hacer no aparece.
- **«←» siempre vuelve a la lista.** Ninguna pantalla es un callejón sin salida.
- **«Pendientes» es una sección**, no un aviso que se cierra: el tester vuelve a ella cuando quiere.

## 5. Formularios, tablas, confirmaciones y conexión

| Tema | Decisión |
|---|---|
| **Formularios** | El resultado es un grupo de radios nativo con la etiqueta como objetivo (B-04). Cada paso tiene dos botones grandes con `aria-pressed`. La evidencia usa la cámara del teléfono (`input type=file`). Las etiquetas siempre están visibles |
| **Errores** | El campo con error tiene `aria-invalid`, el mensaje está asociado con `aria-describedby` y la pantalla se desplaza hasta él |
| **Tablas → tarjetas** | La trazabilidad (5 columnas) es una tarjeta por caso; cada defecto dice si bloquea y **por qué**. La tabla vuelve desde 1024 px |
| **Confirmaciones** | Hoja inferior con [Registrar] / [Volver], este último al alcance del pulgar y con el **foco inicial** (B-08). Cerrar la campaña dice la consecuencia: «Cerrar con No liberar» |
| **Conexión limitada** | Una barra «Sin conexión» bajo la cabecera. El resultado queda **PENDIENTE** y se reenvía con la misma clave; el reenvío se anuncia en una región viva (B-07) |
| **Evidencia clínica sin red** | **No se guarda en el teléfono** (B-01): queda en memoria, con el aviso «No cierre esta pantalla». Es lo que corrige H-08 de la semana 9 |
| **¿Es seguro reintentar?** | Sí, y está medido: la suite del cliente de la semana 5 (CLI-01..08) cubre red caída, respuesta perdida, 503 y reenvío tras el cierre, todos sin duplicar ([reintento-cliente.txt](evidencia/reintento-cliente.txt), 8/8) |

## 6. Escenario 1 · El tester registra una FALLA con señal débil (360 px)

Tito prueba el build 1.4.0 en la estación de enfermería del 3.er piso. Ve que la nota firmada se puede editar.

| Paso | Pantalla | Captura |
|---|---|---|
| 1 | Abre su campaña: «2 pendientes» → [Ejecutar el siguiente caso] | [m1-campanas-base-360](capturas/m1-campanas-base-360.png) |
| 2 | Arriba ve TC-107, versión 1 y build 1.4.0; marca el paso «✕ Distinto» y elige **Falla** | [m2-ejecutar-base-360](capturas/m2-ejecutar-base-360.png) |
| 3 | Olvida la foto y toca [Registrar…]: el error lo lleva al campo de evidencia | [m2-ejecutar-error-360](capturas/m2-ejecutar-error-360.png) |
| 4 | Toma la foto (contiene datos de un paciente: «Sí, datos clínicos») y confirma en la hoja | [m2-ejecutar-confirmar-360](capturas/m2-ejecutar-confirmar-360.png) |
| 5 | La señal se cae: **PENDIENTE**; la evidencia clínica queda solo en la pantalla | [m3-registro-pendiente-clinica-320](capturas/m3-registro-pendiente-clinica-320.png) |
| 6 | Vuelve la señal; la región viva anuncia «registrado» | [m3-registro-reenviado-360](capturas/m3-registro-reenviado-360.png) |

## 7. Escenario 2 · El Líder QA revisa qué bloquea el GO camino a la reunión (390 px)

La reunión de liberación de la 1.4.0 empieza en 10 minutos. El Líder QA la revisa desde el pasillo.

| Paso | Pantalla | Captura |
|---|---|---|
| 1 | Abre la trazabilidad: «Hoy daría No liberar (NO-GO) · 1 causa» | [m4-trazabilidad-base-390](capturas/m4-trazabilidad-base-390.png) |
| 2 | En TC-107 ve #12 Mayor · Abierto · **⛔ Bloquea**, y por qué; en TC-109, que #14 no bloquea | (misma) |
| 3 | Toca [Cerrar campaña…]: la hoja dice la consecuencia y el foco queda en [Volver] | [m4-trazabilidad-cerrar-390](capturas/m4-trazabilidad-cerrar-390.png) |
| 4 | Cierra: «Cerrada: No liberar (NO-GO)» con la causa, para llevarla a la reunión | [m4-trazabilidad-cerrada-390](capturas/m4-trazabilidad-cerrada-390.png) |

## 8. Backlog de la semana 9 aplicado

| Ítem | Estado | Cómo se comprueba |
|---|---|---|
| B-01 evidencia clínica fuera del teléfono | ✅ | m3 `pendiente-clinica`: «no se guarda en el teléfono» + bandeja «Solo en esta pantalla» |
| B-02 texto secundario #5F6670 | ✅ | Token `--secundario`; `contraste.py` (semana 9) en 0 |
| B-03 sin botones deshabilitados | ✅ | R-B1 en 32/32 vistas |
| B-04 resultado como radiogroup | ✅ | `fieldset` + radios nativos; R-T1 sobre las etiquetas |
| B-05 bordes, GO y chips | ✅ | Tokens `--borde`, `--ok`, `--chip-aviso` |
| B-07 región viva | ✅ | m3 `reenviado`: `role=status aria-live=polite` |
| B-08 foco inicial en «Volver» | ✅ | `autofocus` en [Volver] (captura m4 `cerrar`) |
| B-11 etiquetas para personas | ✅ | «No liberar (NO-GO)», «Pasa», «No aplica»; R-S1 |
| B-06, B-09, B-10 | ✅ | Críticos como texto; «1 causa»; el error solo tras enviar |
| **B-12 todo color como token** | ⚠️ **Pendiente** | Quedan **11** usos de 6 colores fuera de `:root`, heredados de la base común (`#E4E6EA` de los esqueletos, `#C9CDD2`…). Se termina en la semana 11 |

## 9. Verificación y limitaciones

```bash
# desde docs/modulos/qa-regresion-defectos/semana-10 (requiere Chrome y puppeteer-core)
node herramientas/verificar-responsive.cjs "$PWD/prototipo" "$PWD/capturas"     # 32/32
```

- **Prototipo estático:** cada estado es una URL. La navegación real entre estados, con la API simulada, es la semana 11.
- **Las reglas son medibles, no exhaustivas.** No se probó con lectores de pantalla reales; axe-core corre en la semana 11.
