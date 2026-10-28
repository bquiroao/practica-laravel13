# Declaración de uso de inteligencia artificial — Semana 10

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | RBAC: roles, permisos y protección de rutas |
| **Entrega** | Semana 10 — Diseño para movilidad |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local. No tuvo acceso a GitHub ni hizo commits. |

## 2. Propósito del uso

- Adaptar a móvil (320–430 px) las pantallas W1–W6 de la semana 8, aplicando el backlog de la semana 9.
- Escribir el prototipo HTML/CSS mobile-first (5 pantallas con varios estados).
- Escribir el verificador de reglas de breakpoint (`verificar-responsive.cjs`, con puppeteer-core y Chrome) y generar las 31 capturas.
- Escribir `reintento.php`, que mide sobre el kernel real del Micro-HIS si es seguro reintentar después de un corte.
- Armar las tiras de los dos escenarios y redactar el documento, la guía de defensa y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Adapte «asignación de rol, autorización de una operación y denegación trazable» a móvil (320–430 px): jerarquice los datos y acciones de roles, permisos, rutas protegidas, decisiones de autorización y denegaciones auditables, reduzca carga cognitiva y defina navegación, tablas/formularios, confirmaciones y recuperación ante conexión limitada. Entregable/Evidencia: Propuesta responsive de 3–5 pantallas, reglas de breakpoint y dos escenarios móviles con decisiones de contenido y error.
2. Indicaciones permanentes: agregar `DECLARACION_IA.md` y `GUIA_DEFENSA_ORAL.md` en cada semana.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `prototipo/*.html`, `estilos.css`, `estado.js` | Borrador completo |
| `herramientas/verificar-responsive.cjs`, `herramientas/reintento.php` | Borrador completo |
| `capturas/*.png`, `escenarios/*.png` | Generados con Chrome (viewport emulado) y Pillow |
| `evidencia/*.txt` | Salidas reales de los scripts (Node 24, PHP 8.4) |
| `semana-10-movil-rbac.md`, `GUIA_DEFENSA_ORAL.md`, `DECLARACION_IA.md` | Borradores |

**Notas de honestidad:**

- La primera captura salió a más de 360 px: Chrome en modo *headless* impone un ancho mínimo de ventana. Se cambió a emulación de viewport con puppeteer-core.
- El verificador encontró un fallo real: el botón «Reintentar» medía 40 px. Se corrigió a 44 px.
- La revisión visual encontró otros cuatro problemas, y se corrigieron:
  - texto rojo heredado en el aviso 403, por una clase CSS que coincidía;
  - una ayuda cortada en la hoja de M3;
  - un botón con texto en dos líneas;
  - márgenes en tableta.
- `reintento.php` mostró algo que el diseño asumía mal: reintentar una asignación **con fecha de fin** duplica la membresía. El texto del prototipo se corrigió y se definió la regla R-C1 con la brecha B-5.
- En la primera redacción del escenario 1, el PDP respondía PERMITIDO después de la revocación, y eso no es posible en el Micro-HIS. Se corrigió: el permiso visible viene de la caché de 30 s, anterior a la revocación.
- Las capturas son de un navegador emulado, no de teléfonos reales. No se probó con VoiceOver ni TalkBack.
- Los nombres de personas son ficticios.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Jerarquía de contenido P1/P2/P3 | | |
| Breakpoints XS/S/M/L/XL y reglas R-B1..R-B9 | | |
| Pantallas M1–M5 | | |
| Política de conexión limitada y regla R-C1 | | |
| Escenarios 1 y 2 | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Abrí el prototipo en el navegador con el modo de dispositivo y recorrí los estados con `?estado=`.
- [ ] Ejecuté `php herramientas/reintento.php` y obtuve los tres resultados.
- [ ] Ejecuté `verificar-responsive.cjs` (o revisé `evidencia/verificacion-responsive.txt`).
- [ ] Puedo explicar por qué el teléfono no guarda en cola cambios de permisos.

**Fecha de validación:** ____________
