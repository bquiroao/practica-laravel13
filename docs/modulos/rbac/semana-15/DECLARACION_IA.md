# Declaración de uso de inteligencia artificial — Semana 15

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | RBAC: roles, permisos y protección de rutas |
| **Entrega** | Semana 15 — Pruebas unitarias, caja blanca y caja negra |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local. No tuvo acceso a GitHub ni hizo commits. |

## 2. Propósito del uso

- Revisar el proyecto principal para decidir dónde probar. Conclusión: aún no tiene RBAC; solo tiene código de módulos ajenos.
- Diseñar y escribir las pruebas:
  - 9 unitarias, 4 de caja blanca y 6 de caja negra;
  - 5 de defectos abiertos.
- Medir la cobertura de líneas de la caja blanca con `phpdbg`.
- Hacer pruebas exploratorias de casos límite; de ahí salió un defecto nuevo, DEF-01.
- Agregar las opciones `--solo` y `--defectos` al ejecutor de pruebas.
- Generar las capturas a partir de las salidas reales.
- Redactar la matriz, el registro de defectos, el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Diseñe y ejecute pruebas de RBAC: roles, permisos y protección de rutas trazadas a RF/RNF: unitarias para reglas de roles, permisos, rutas protegidas, decisiones de autorización y denegaciones auditables; caja blanca con ramas/condiciones del flujo «asignación de rol, autorización de una operación y denegación trazable»; caja negra con equivalencia, límites, permisos y errores, sin probar módulos ajenos. Entregable/Evidencia: Matriz RF/RNF→prueba, mínimo 3 unitarias, 2 de caja blanca y 4 de caja negra, comandos/resultados, capturas y defectos registrados.
2. Indicación permanente nueva del estudiante: el código puede ir en el proyecto principal cuando haga falta, y `docs/` es solo para documentación.
3. Indicaciones permanentes: agregar `DECLARACION_IA.md` y `GUIA_DEFENSA_ORAL.md` en cada semana.

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `micro-his-rbac/tests/Dominio/UnitariasReglasPrueba.php`, `tests/Aplicacion/CajaBlancaPrueba.php`, `tests/Presentacion/CajaNegraPrueba.php`, `tests/Defectos/DefectosPrueba.php` | Borrador completo |
| `micro-his-rbac/tests/ejecutar.php` (opciones `--solo` y `--defectos`), `micro-his-rbac/README.md` | Modificación |
| `semana-15/herramientas/cobertura-caja-blanca.php` | Borrador completo |
| `semana-15/evidencia/*.txt` | Salidas reales (PHP 8.4.26, phpdbg) |
| `semana-15/capturas/*.png` | Generadas a partir de esas salidas (Pillow) |
| `semana-15/diagramas/semana-15-grafo-autorizar.*` | PlantUML 1.2024.7 |
| Documentos `.md` de la semana | Borradores |

**Notas de honestidad:**

- **Las capturas no son capturas de pantalla.** Son imágenes generadas a partir del texto real que imprimió cada comando, guardado también en `evidencia/`.
- **Dos pruebas de caja negra fallaron al principio por errores del propio diseño**, no del producto:
  - CN-03 esperaba el texto de la API simulada, y luego usó nombres de permisos con dígitos.
  - Se corrigieron y están registrados en el [registro de defectos](registro-defectos.md#errores-en-el-diseño-de-las-pruebas-no-son-defectos-del-producto).
- **Algunas referencias a pruebas existentes en la matriz eran incorrectas** y se corrigieron después de revisar sus descripciones.
- **Los 5 defectos no se corrigieron:** quedan abiertos con su prueba.
- **Las pruebas no tocan el proyecto Laravel principal**, porque todavía no tiene código de RBAC.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Pruebas unitarias UNI-01..09 | | |
| Caja blanca CB-01..04 y medición de cobertura | | |
| Caja negra CN-01..06 | | |
| Defectos DEF-01..05 y OBS-01 | | |
| Matriz RF/RNF → prueba | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté `php tests/ejecutar.php` (99 · 97 · 0 · 2) y `--defectos --solo=DEF` (5 fallan).
- [ ] Ejecuté `phpdbg -qrr herramientas/cobertura-caja-blanca.php` (98.7 %).
- [ ] Reproduje DEF-01 a mano (asignar con `valido_desde = 2026-00-10`).
- [ ] Puedo explicar la diferencia entre CB-03 (MC/DC) y CN-02 (valores límite).

**Fecha de validación:** ____________
