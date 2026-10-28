# Declaración de uso de inteligencia artificial — Semana 15

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana |
| **Módulo** | Gobernanza y auditoría de movimientos del sistema |
| **Entrega** | Semana 15 — Pruebas unitarias, de caja blanca y de caja negra |

## 1. Herramienta utilizada

| Herramienta | Modelo | Proveedor | Modo de uso |
|---|---|---|---|
| Claude Code (extensión de VS Code) | Claude Opus 5.5 | Anthropic | Asistente con acceso de lectura y escritura al repositorio local y a la terminal. Con autorización del estudiante, también creó la rama, hizo los commits y abrió y mergeó el PR. |

## 2. Propósito del uso

- Diseñar y escribir las 18 pruebas (UNI, CB, CN) y las de defectos abiertos.
- Corregir DEF-07-01 con su prueba en rojo primero y agregar la mutación M27.
- Medir la cobertura de la caja blanca, generar las capturas y redactar la matriz, el registro de defectos, el documento, la guía y esta declaración.

## 3. Prompts relevantes

1. Instrucción de la tarea, entregada textualmente:
   > Tarea individual: Diseñe y ejecute pruebas de «registro inmutable y consulta filtrada de movimientos por usuario, módulo y fechas» en Gobernanza y auditoría de movimientos del sistema trazadas a RF/RNF: unitarias para reglas de audit logs inmutables, usuario, módulo, acción, tenant y filtros por rango de fechas; caja blanca con ramas/condiciones del flujo; caja negra con equivalencia, límites, permisos y errores, sin probar módulos ajenos. Entregable/Evidencia: Matriz RF/RNF→prueba, mínimo 3 unitarias, 2 de caja blanca y 4 de caja negra, comandos/resultados, capturas y defectos registrados.
2. «puedes continuar».

## 4. Contenido generado con IA

| Archivo | Generado por IA |
|---|---|
| `micro-his-auditoria/tests/{Dominio/UnitariasPrueba,Aplicacion/CajaBlancaPrueba,Presentacion/CajaNegraPrueba,Defectos/DefectosPrueba}.php` | Completo |
| `micro-his-auditoria/src/Aplicacion/Servicios/CursorPaginacion.php` (corrección de DEF-07-01), `tests/ejecutar.php` (`--solo`, `--defectos`), M27 | Completo |
| `semana-15/*` | Borradores; las evidencias y capturas son salida real |

**Hechos a tener en cuenta:**
- **Primera corrida de UNI-05:** falló por un error **de la prueba**. El movimiento por defecto usa `PACIENTE_REGISTRADO` y el catálogo de la prueba no lo tenía. Se corrigió la prueba, no el código.
- **CN-04 encontró un defecto real nuevo (DEF-04).** Se registró y sus dos celdas quedaron como no evaluadas en la tabla, con referencia al defecto.
- **La primera medición de cobertura dio 93,9 %.** Se agregaron dos salidas de `VerificarIntegridad::ejecutar()` a CB-03 y se llegó al 100 %.
- **Las capturas del primer intento llevaban el título de otro módulo** («micro-his-rbac»). Se regeneraron.
- **Se marcó AR-03 como cerrado** en el registro de hallazgos de la semana 13, porque DEF-07-01 se corrigió con su prueba y su mutación.

## 5. Contenido aceptado, modificado o descartado

> Esta sección la completa el estudiante después de revisar el material.

| Elemento | Decisión (aceptado / modificado / descartado) | Detalle del cambio |
|---|---|---|
| Pruebas UNI, CB y CN | | |
| Corrección de DEF-07-01 | | |
| Registro de defectos (DEF-01..04) | | |

## 6. Validación humana

> Esta sección la completa el estudiante.

- [ ] Ejecuté los comandos del §5 del documento.
- [ ] Revisé las severidades del registro de defectos.

**Fecha de validación:** ____________
