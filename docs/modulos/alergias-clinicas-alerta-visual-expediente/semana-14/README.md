# Semana 14 — Plan SQA: confiabilidad y aseguramiento de calidad

Módulo: **Alergias clínicas y alerta visual en expediente** · Flujo: «registro de alergia y emisión de alerta al consultar el expediente»

## 1. Objetivo y alcance

Asegurar que el registro de alergias y la alerta del expediente sean **correctos, confiables, seguros y trazables** mientras el módulo cambia. El plan cubre el Micro-HIS (`micro-his/`), el contrato API v1 y el diseño UX. No cubre módulos ajenos.

Roles:
- **Responsable del plan:** el autor del módulo (Erick Ramazzini).
- **Verificación independiente:** el líder que revisa los PR (Boris Quiroa). Es una **propuesta**.

## 2. Métricas (6)

| ID | Métrica | Fuente / cómo se mide | Umbral | Responsable | Acción si no se cumple |
|---|---|---|---|---|---|
| M-01 | Pruebas que pasan | `php pruebas/ejecutar.php` → línea `RESULTADO` | 100 % de las no marcadas `[DEFECTO]`; 0 fallidas; 0 omitidas en el equipo del autor | Autor | No se abre PR; se corrige primero |
| M-02 | Reglas de negocio con prueba | Matriz RN → prueba (semana 15) | RN-01 a RN-09 y RN-11: 10 de 10 con al menos una prueba. RN-10 se acepta sin prueba propia (CU-06 fuera del Micro-HIS) | Autor | Escribir la prueba que falta antes del siguiente PR |
| M-03 | Defectos Mayores abiertos | Registro de defectos (semana 15) y hallazgos RT (semana 13) | 0 sin acción decidida; ≤ 1 abierto al cierre del curso | Autor; el moderador verifica | Priorizar sobre trabajo nuevo |
| M-04 | Latencia de la alerta | `scripts/medir-frontera.php --n=200 --url=…` | p95 por HTTP < 300 ms (RNF-02) | Autor | Revisar índices y consultas; si persiste, es el gatillo G4 de la semana 5 |
| M-05 | Contrato coherente | Comparar los códigos de `contrato-api.md` con los que devuelve el código (T-35 a T-44 y CN de la semana 15) | 100 % de los códigos documentados se producen en alguna prueba | Revisor de API | Corregir el contrato o el código en el mismo PR |
| M-06 | Accesibilidad | `python semana-09/herramientas/contraste.py` + axe DevTools en las vistas | 0 fallas de contraste; 0 violaciones de axe; 0 hallazgos P1 abiertos de la semana 9 en el prototipo | Autor | Aplicar el backlog B-01 a B-05 antes de la defensa |

**Línea base**, medida en el entorno de preparación:
- M-01: 44/44.
- M-02: ver la semana 15.
- M-03: 2 Mayores identificados (RT-01, RT-04), con su acción.
- M-04: p95 por HTTP 82.97 ms en el equipo del estudiante (19.68 ms en el de preparación).
- M-05: los 9 códigos de 422 y los de 401, 403, 404, 409 y 503 aparecen en el contrato.
- M-06: contraste 12/12, axe con 0 violaciones.

## 3. Matriz de riesgos

Probabilidad (P) e impacto (I) de 1 a 3; exposición = P × I.

| ID | Riesgo | P | I | Exp. | Control |
|---|---|---|---|---|---|
| R-01 | Una alergia grave queda con un nivel bajo por cómo se escribió (tildes o sinónimos), como RT-01 | 3 | 3 | **9** | C-01, C-06 |
| R-02 | Se muestra «sin alergias» cuando la lectura falló | 1 | 3 | 3 | C-02, C-07 |
| R-03 | Un reconocimiento viejo valida una alerta nueva | 1 | 3 | 3 | C-03 |
| R-04 | Dos alergias activas de la misma sustancia (código vs nombre, RT-02) | 2 | 2 | 4 | C-04, C-06 |
| R-05 | Un usuario de otro hospital o sin permiso lee o escribe alergias | 1 | 3 | 3 | C-05, C-08 |
| R-06 | Datos a medias tras una falla de la base | 1 | 3 | 3 | C-09 |
| R-07 | Datos clínicos en registros o mensajes | 2 | 2 | 4 | C-10 |
| R-08 | El contrato API y el código se separan | 2 | 2 | 4 | C-11 |

## 4. Controles

| ID | Tipo | Control | Cubre |
|---|---|---|---|
| C-01 | Preventivo | Toda regla de nivel nueva entra con una prueba de variantes reales del texto (con y sin tilde, mayúsculas) | R-01 |
| C-02 | Preventivo | Las vistas tienen un estado de error distinto del vacío; está prohibido usar verde para `SIN_REGISTRO` | R-02 |
| C-03 | Preventivo | Reconocimiento ligado a la huella (RN-09) | R-03 |
| C-04 | Preventivo | La normalización del alérgeno vive en un solo lugar (`Alergeno::clave`) y se alinea con el índice | R-04 |
| C-05 | Preventivo | Tenant tomado del token; permiso revisado antes de cualquier lectura | R-05 |
| C-06 | Detectivo | Pruebas de caja negra con equivalencia y límites (semana 15) en cada PR del Micro-HIS | R-01, R-04 |
| C-07 | Detectivo | Revisión del PR con la pregunta 13 del checklist de la semana 13 | R-02 |
| C-08 | Detectivo | T-37 y T-38 corren en cada PR | R-05 |
| C-09 | Detectivo | T-12 y T-44 (escritura rechazada → 0 filas) en cada PR | R-06 |
| C-10 | Detectivo | T-43 verifica que el registro de acceso no lleva alérgenos ni códigos; ítem 12 de la RTF | R-07 |
| C-11 | Detectivo | M-05 en cada PR que toque `Presentacion/Api` | R-08 |

## 5. Confiabilidad, seguridad y trazabilidad

- **Confiabilidad:**
  - toda escritura es todo o nada (C-09);
  - un reintento de POST es seguro gracias al 409 (T-11);
  - un reconocimiento es idempotente (T-17).
- **Seguridad:** token, permiso por operación, tenant del token y SQL preparado. El ítem 11 de la RTF verificó que no hay `query(` ni `exec(` en `src/` ni en `scripts/` (búsqueda con `grep`: 0).
- **Trazabilidad:**
  - `verified_by_user_ref_id` dice quién registró;
  - el reconocimiento guarda usuario y huella;
  - `X-Request-Id` liga la petición con el registro de acceso;
  - IDs estables (CU, RF, RN, CA, T, H, B, RT, D) de la semana 1 a la 15.

## 6. Calendario de seguimiento

| Cuándo | Qué | Quién |
|---|---|---|
| Cada PR | M-01, M-05 (si toca la API) y controles C-06, C-08 a C-10 | Autor; el revisor lo comprueba |
| Cada semana (viernes) | M-03 y revisión de riesgos con exposición ≥ 4 | Autor |
| Antes de cada parcial | M-04 y M-06 | Autor |
| Semana 15 | M-02 completa (matriz RF/RNF → prueba) y registro de defectos | Autor |
| Cierre del curso | Informe: valores finales de M-01 a M-06, riesgos abiertos y defectos pendientes | Autor y moderador |
