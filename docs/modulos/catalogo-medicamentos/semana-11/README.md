# Semana 11 — Catálogo de medicamentos: prototipo navegable desktop y móvil

| Entregable | Archivo |
|---|---|
| Prototipo (archivo) | `prototipo/index.html` (abrir con doble clic; no necesita servidor) |
| Mapa de navegación | `diagramas/mapa-navegacion.png` |
| Capturas desktop (1280×800) | `capturas/desktop-01-buscar.png` … `desktop-08-eliminar-bloqueado.png` |
| Capturas móvil (375×760) | `capturas/movil-01-buscar.png` … `movil-08-eliminar-bloqueado.png` |

## 1. Recorridos

**Camino feliz (QF_CATALOGO):** buscar «lorat» → estado vacío con «Registrar medicamento» →
formulario → enviar vacío (422: 7 errores en el resumen, foco en el resumen) → completar
Loratadina 10 mg tableta → «Registrado MED-000107» en el detalle → buscar «loratadina» lo
encuentra. Capturas 01 a 06.

**Error crítico (RN-04, unicidad):** registrar «amoxicilina 500 mg cápsula oral» → 409
«Ya existe un medicamento equivalente vigente» con «Ver MED-000102» y «Corregir datos»; el foco
va al mensaje y los datos escritos se conservan. Captura 07.

**Protección RN-02:** eliminar MED-000101 (usado en una prescripción ficticia) queda bloqueado y
ofrece «Ir a dar de baja». Captura 08 (en móvil, como hoja inferior).

## 2. Consistencia con las semanas anteriores

| Tema | Cómo se mantiene |
|---|---|
| Roles | Selector «Rol de prueba»: CONSULTA_CLINICA no ve Registrar, historial ni acciones; en `#/nuevo` ve «Sin permiso» (como el 403 de la API). AUDITOR_CALIDAD ve el historial. |
| Estados | Vacío, cargando («Buscando…», botón deshabilitado), éxito (`role="status"`) y error (`role="alert"`), como en W1–W6. |
| Reglas | Vigencia `[desde, hasta)`, clave de equivalencia, códigos `MED-NNNNNN` y datos ficticios iguales al Micro-HIS. |
| Móvil | Tarjetas (M1), detalle con la versión vigente primero (M2), confirmaciones en hoja inferior (M4). |

## 3. Correcciones del backlog de la semana 9 aplicadas

| Backlog | En el prototipo |
|---|---|
| B-01 confirmar eliminación | Hay que escribir el código; si fue usado, se bloquea. |
| B-02 todos los errores a la vez | Concentración se valida aunque falte la unidad. |
| B-03 foco y enlaces en errores | Foco en el resumen; cada error es un enlace al campo. |
| B-04 foco con contraste | `#1d4ed8` (6,70:1). |
| B-05 búsqueda sin acentos | «capsula» encuentra «cápsula» (en el prototipo; en el Micro-HIS sigue pendiente). |
| B-06 baja el mismo día | El mensaje sugiere «Eliminar registro erróneo». |
| B-07 obligatorios | «*», leyenda y `aria-required`. |
| B-08 quién registró | Columna «Registró» en el historial (propuesta de API). |
| B-09 etiquetas legibles | «Solución inyectable» en lugar de `SOLUCION_INYECTABLE`. |
| B-10 saltar al contenido | Primer elemento con Tab. |
| B-11 tarjetas en móvil | Resultados en tarjetas e historial apilado. |

## 4. Verificación hecha al preparar la entrega

Recorrido automático con Chromium (Playwright) en 1280×800 y 375×760:

| Comprobación | Desktop | Móvil |
|---|---|---|
| Errores de JavaScript (consola y página) | 0 | 0 |
| Desplazamiento horizontal en las 8 pantallas | ninguno | ninguno |
| Elemento con foco tras el 422 y tras el 409 | `resumen` | `resumen` |
| Errores listados al enviar vacío | 7 | 7 |
| Primer Tab al abrir | «Saltar al contenido»; Enter lleva el foco al contenido | — |
| «capsula» encuentra MED-000102 | sí | — |

Roles: con CONSULTA_CLINICA, el detalle no muestra historial ni acciones y `#/nuevo` muestra
«Sin permiso».

## 5. Limitaciones

- Es un prototipo sin servidor: los datos viven en la memoria del navegador y se reinician al recargar.
- La autenticación y el tenant se simulan; el selector de rol existe solo para la demostración.
- El «Código de soporte» del 409 es simulado (`proto-…`); en el sistema real es el `request_id` de la API.
