# Semana 13 — Catálogo de medicamentos: plan de revisión técnica formal

| Entregable | Archivo |
|---|---|
| Plan de revisión (este documento) | `README.md` y `diagramas/proceso-revision.png` |
| Checklist de 15 ítems | `checklist-revision.md` |
| Roles de autor, moderador y revisores | Sección 3 |
| Criterios de entrada y salida | Sección 4 |
| Formato de hallazgos | `formato-hallazgos.md` |

## 1. Objetivo

Encontrar defectos en lo estrictamente necesario para «alta, búsqueda y control de vigencia de un
medicamento» antes de llevarlo al HIS: reglas de dominio, contrato API, permisos y tenant,
integridad de medicamentos, presentaciones, vigencia, búsqueda y unicidad, y dependencias.

## 2. Alcance (paquete de revisión)

| Artefacto | Ubicación | Tamaño aprox. |
|---|---|---|
| Dominio (modelo, reglas, interfaces) | `micro-his/src/Domain/` | ~770 líneas |
| Casos de uso | `micro-his/src/Application/` | ~350 líneas |
| Persistencia PDO e InMemory, esquema | `micro-his/src/Persistence/`, `scripts/crear-esquema.php` | ~620 líneas |
| API (kernel, permisos, controlador) | `micro-his/src/Presentation/Api/` | ~320 líneas |
| Contrato API y frontera | `semana-05/contrato-api.md`, `frontera-microservicio.md` | 2 documentos |
| Requisitos y reglas | `semana-02/README.md` | 1 documento |

**Fuera de alcance:** vistas web y prototipo (ya evaluados en la semana 9), estilos, módulos vecinos.
La versión revisada es el commit de `develop` que contiene el PR de la semana 5 (parte D); se anota en el acta.

## 3. Roles (propuesta)

| Rol | Persona | Responsabilidad |
|---|---|---|
| Autor | Erick Rolando Ramazzini Muralles | Prepara el paquete, responde preguntas, corrige. No defiende ni discute en la reunión. |
| Moderador | Boris Quiroa (líder, @bquiroao) | Verifica criterios de entrada, conduce la reunión, decide la salida. |
| Revisor 1 — dominio e integridad | Josué Morales | Ítems C-01 a C-05, C-12 y C-13. |
| Revisor 2 — API, permisos y tenant | Joshua García | Ítems C-06 a C-11. |
| Registrador | Moderador (o un revisor) | Anota hallazgos en el formato durante la reunión. |
| Dependencias (C-14, C-15) | Ambos revisores | Revisión cruzada. |

Las asignaciones son una **propuesta**: dependen de que cada persona acepte y de su disponibilidad.

## 4. Criterios

**Entrada** (si falta uno, el moderador devuelve el paquete):
1. Todas las pruebas en verde: `php pruebas/ejecutar.php` con evidencia en `evidencia/pruebas-antes-de-revision.txt`.
2. PR de las semanas 3–5 mergeados en `develop`; versión congelada anotada.
3. Contrato API y RF/RNF actualizados con el código.
4. Paquete y checklist enviados 48 h antes; cada revisor dedica 60–90 minutos a prepararse.

**Salida** (para cerrar el acta):
1. Cero hallazgos **críticos** abiertos.
2. Todo hallazgo **mayor** cerrado con commit o PR enlazado, o con fecha y responsable aceptados por el moderador.
3. Pruebas en verde después de las correcciones.
4. Acta con decisión: aceptado · aceptado con cambios · nueva revisión.

## 5. Agenda de la reunión (90 minutos máximo)

| Min. | Actividad |
|---|---|
| 0–5 | Moderador: objetivo, versión revisada y reglas (se buscan defectos, no soluciones) |
| 5–35 | Ítems C-01 a C-05 y C-12, C-13 (revisor 1) |
| 35–65 | Ítems C-06 a C-11 (revisor 2) |
| 65–75 | C-14, C-15 y hallazgos traídos de la preparación |
| 75–85 | Severidades y decisión |
| 85–90 | Responsables y fechas |

## 6. Métricas de la revisión

Se registran en el acta y alimentan el plan SQA (semana 14): tiempo de preparación por revisor,
hallazgos por severidad, densidad (hallazgos por cada 100 líneas revisadas) y porcentaje cerrado
a las dos semanas.

## 7. Estado

El plan y los ejemplos de `formato-hallazgos.md` §4 están **preparados**; la revisión todavía no se
realizó. El acta y los hallazgos reales se agregan cuando ocurra la reunión.
