# Declaración de uso de inteligencia artificial — Semana 7

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | RBAC: roles, permisos y protección de rutas |
| **Entrega** | Semana 7 — Diseño de componentes y refactorización |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local. No tuvo acceso a GitHub ni hizo commits. |

## 2. Propósito del uso

- Escribir el medidor de acoplamiento (`bin/acoplamiento.php`) y usarlo para elegir con datos el punto a refactorizar.
- Escribir pruebas de caracterización (REF-01..03) y verificarlas también sobre el código anterior (`git archive HEAD`).
- Refactorizar `AsignarRol` y `RevocarRol`:
  - `CambioDeMembresia` y el formato de eventos en `EventoAuditoria`;
  - `RegistradorDeCambios` y `Desenlace`;
  - políticas fail-closed y `AutorizarOperacion::exigir()`.
- Revisar el frontend Vue existente para diseñar los componentes de RBAC.
- Escribir los diagramas, los contratos de entrada/salida, el documento, la guía de defensa y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Diseñe los componentes backend y frontend propios de RBAC: roles, permisos y protección de rutas para «asignación de rol, autorización de una operación y denegación trazable». Refactorice conceptualmente el punto con mayor acoplamiento entre roles, permisos, rutas protegidas, decisiones de autorización y denegaciones auditables, separando UI, aplicación, dominio y persistencia sin ampliar el módulo. Entregable/Evidencia: Diagrama de componentes, contratos de entrada/salida y comparación antes/después de un refactor concreto con justificación.
2. Indicaciones permanentes: agregar `DECLARACION_IA.md` y `GUIA_DEFENSA_ORAL.md` en cada semana.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| Refactor en `micro-his-rbac/src/` (5 archivos nuevos, 7 modificados), `bin/acoplamiento.php`, `tests/Aplicacion/RefactorPrueba.php` | Borrador completo |
| `semana-07-componentes-refactor.md`, `contratos-entrada-salida.md` | Borrador completo |
| `diagramas/*.puml` y `*.png` | Borrador completo (PlantUML 1.2024.7) |
| `refactor/antes/*.php` | Copia literal del código anterior (no generado) |
| `evidencia/*.txt` | Salidas reales de los comandos (PHP 8.4.26) |
| `GUIA_DEFENSA_ORAL.md` y `DECLARACION_IA.md` | Borradores |

**Notas de honestidad:**

- El primer paso del refactor solo bajó el Ce de 13 a 10. Por eso se hizo un segundo paso: las políticas pasaron a ser fail-closed y se agregó `exigir()`, y con eso se llegó a 8.
- El detector de duplicación mostró una baja pequeña (33 → 29). Se reporta tal cual y se explica qué tipo de líneas quedan.
- El frontend es **diseño**, no implementación.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Medidor de acoplamiento y elección del punto | | |
| Refactor (`CambioDeMembresia`, `RegistradorDeCambios`, políticas fail-closed) | | |
| Pruebas de caracterización | | |
| Diseño de componentes frontend | | |
| Contratos de entrada/salida | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `php bin/acoplamiento.php` y `php tests/ejecutar.php` en mi equipo.
- [ ] Comparé `refactor/antes/AsignarRol.php` con `src/Aplicacion/CasosDeUso/AsignarRol.php` y puedo explicar cada cambio.
- [ ] Puedo explicar por qué el refactor no amplía el módulo (pruebas de caracterización).
- [ ] Revisé que el diseño frontend sea coherente con `resources/js` del proyecto.

**Fecha de validación:** ____________
