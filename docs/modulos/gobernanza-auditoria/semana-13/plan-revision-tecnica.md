# Plan de la revisión técnica formal — Gobernanza y auditoría

**Autor del objeto revisado:** Boris Alexander Quiroa Orellana · **Objeto:** Micro-HIS Auditoría (`docs/modulos/gobernanza-auditoria/micro-his-auditoria`), contrato 1.2.0 · **Versión del plan:** 1.0, 26/10/2026

![Proceso de la revisión](diagramas/semana-13-proceso-revision.png)

## 1. Objetivo y tipo de revisión

**Objetivo:** encontrar defectos y riesgos, **no corregirlos en la reunión**, en las cinco áreas que pide la tarea:
1. las reglas de dominio del movimiento;
2. el contrato API;
3. los permisos y el tenant;
4. la integridad de los audit logs;
5. las dependencias estrictamente necesarias.

**Tipo:** **inspección formal** (Fagan, adaptada a IEEE 1028), con **lectura basada en perspectivas**. Cada revisor lee todo el paquete de sus sesiones, pero desde una perspectiva asignada (D, C, S, I, A). Así se evita que todos miren lo mismo.

**Por qué una inspección y no un walkthrough:**

| Criterio | Inspección | Walkthrough |
|---|---|---|
| Quién conduce | El moderador (no el autor) | El autor |
| Preparación individual | Obligatoria y medida | Opcional |
| Salida | Registro de hallazgos con severidad y decisión formal | Comentarios |
| Adecuado para | Código que custodia **evidencia** (inmutabilidad, sello, tenant) | Diseño temprano |

**Por qué inspección en este módulo:** la bitácora es lo que se presenta cuando hay una denuncia. Un defecto en la inmutabilidad o en el tenant no se descubre usando el sistema, sino cuando ya hace falta la evidencia.

## 2. Alcance y paquete de revisión

**Qué entra:** el paquete lo arma y congela [`herramientas/criterios-entrada.php`](herramientas/criterios-entrada.php), con un commit y el listado de archivos y líneas efectivas de cada sesión ([evidencia](evidencia/criterios-entrada-2-despues.txt)).

| Sesión | Contenido | Líneas efectivas | Perspectivas principales |
|---|---|---:|---|
| **S1** · Reglas de dominio del movimiento | `src/Dominio/**`, `database/schema.sql` | 919 | D, I |
| **S2** · Aplicación y persistencia | `src/Aplicacion/**`, `src/Persistencia/{Pdo,EnMemoria}/**`, `integracion-central.sql` | 936 | I, S |
| **S3** · Contrato, frontera, permisos/tenant y dependencias | `api/openapi.yaml`, `src/Presentacion/Http/**`, `bootstrap/contenedor.php`, `config/config.php`, `public/index.php` | 1091 | C, S, A |

**Fuera del alcance:**
- **Las pruebas (`tests/**`):** se dan por buenas porque la suite pasa (E-2). Pero los revisores **pueden** citar un caso que las pruebas no cubren.
- **El cliente** (`src/Cliente`, revisado en la semana 5) y **los prototipos de UX** (semanas 10–12).
- **Los módulos ajenos** (RBAC, JwtAuth): solo su **contrato** con Auditoría.

**Material de apoyo que se entrega con el paquete:**
- requisitos (semanas 1, 2, 6 y 12);
- matrices de decisión (semanas 6 y 12);
- [checklist](checklist-revision.md), [formato](formato-hallazgos.md) y [autorrevisión](autorrevision-autor.md).

## 3. Roles y responsabilidades

| Rol | Quién | Perspectiva | Responsabilidad |
|---|---|:-:|---|
| **Autor** | Boris Alexander Quiroa Orellana | — | Prepara el paquete; hace la autorrevisión (E-6); en la reunión **solo aclara**, no defiende; cierra los hallazgos después |
| **Moderador** | Integrante de otro módulo (por confirmar, E-8) | — | Verifica la entrada; conduce la reunión y el ritmo; corta las discusiones de solución; decide la severidad final y el resultado (§7); hace el seguimiento |
| **Registrador** | Integrante del equipo (por confirmar) | — | Llena el registro de hallazgos y el [acta](plantillas/acta-revision.md) en el momento |
| **Lector** | R3 (rota por sesión) | — | Recorre el código en voz alta, en el orden del flujo, para que el grupo lo vea |
| **R1** · Permisos y tenant | Revisor con experiencia en seguridad (por confirmar) | S | RT-05, RT-07, RT-08, RT-09 |
| **R2** · Contrato y frontera | Responsable de un módulo emisor, por ejemplo Pacientes (por confirmar) | C | RT-04, RT-05, RT-06, RT-13 |
| **R3** · Integridad y dominio | Revisor con base de datos (por confirmar) | I, D | RT-01..03, RT-10..12 |
| **R4** · Arquitectura y dependencias | Docente o par técnico (por confirmar) | A | RT-14, RT-15 |

**Reglas de independencia:**
- El autor **no** puede ser moderador ni registrador.
- Ningún revisor revisa un módulo propio en la misma sesión.

## 4. Proceso y calendario

| Fase | Quién | Cuándo | Salida |
|---|---|---|---|
| 1. Planificación | Moderador y autor | D-5 | Este plan; roles confirmados (E-8) |
| 2. Autorrevisión y congelación | Autor | D-3 | [autorrevision-autor.md](autorrevision-autor.md); `criterios-entrada.php` en verde |
| 3. Reunión de presentación (opcional, 20 min) | Todos | D-3 | Contexto: R-01..R-11 y las capas |
| 4. Envío del material | Moderador | **≥ 48 h** antes de la reunión (E-7) | Paquete y commit |
| 5. Preparación individual | Cada revisor | D-2 a D-1 | Hallazgos preliminares; minutos y líneas en `registro-preparacion.csv` |
| 6. Reuniones S1, S2 y S3 | Todos | D, D+1 y D+2 (≤ 90 min cada una) | Registro de hallazgos y acta |
| 7. Retrabajo | Autor | D+3 a D+10 | Correcciones con su prueba y su mutación |
| 8. Seguimiento | Moderador | D+10 | Verificación de cada cierre; decisión final |

**Ritmo de preparación:** 150 a 200 líneas efectivas por hora y revisor. Con unas 1000 líneas por sesión, cada revisor dedica de 5 a 7 h en total a las tres sesiones.

## 5. Reglas de la reunión

1. Se revisa el **producto**, no al autor.
2. **No se discuten soluciones**: se registra la propuesta y se sigue.
3. El autor solo responde preguntas de hecho («¿esto se llama también desde X?»).
4. Máximo **90 minutos**; si no alcanza, se agenda una sesión de continuación.
5. Cada hallazgo se registra **en el momento** con todos los campos obligatorios.
6. Las discrepancias de severidad las decide el moderador, y queda anotado el voto en contra.

## 6. Criterios de entrada

| ID | Criterio | Cómo se verifica |
|---|---|---|
| E-1 | Paquete congelado en un commit y sin cambios locales | `criterios-entrada.php` (automático) |
| E-2 | Suite automatizada sin fallas | Automático: ejecuta `tests/ejecutar.php` |
| E-3 | Guardas de contrato (API-01..03, REC-04) y de capas (ARQ-01..08) en verde | Automático |
| E-4 | Cada sesión ≤ 1600 líneas efectivas | Automático |
| E-5 | Checklist de 12–15 ítems y formato de hallazgos publicados | Automático |
| E-6 | Autorrevisión del autor registrada con el mismo formato | Automático: cuenta los AR en el CSV y en el documento |
| E-7 | Material distribuido ≥ 48 h antes | Manual: el moderador lo anota en el acta |
| E-8 | Roles asignados y revisores confirmados | Manual: §3 y el acta |

**Si un criterio no se cumple, la reunión no se hace.**

**Evidencia del «antes y después»:**
- **Antes:** E-5 y E-6 fallaban, porque no existían ni el checklist ni la autorrevisión ([antes](evidencia/criterios-entrada-1-antes.txt)).
- **Después:** los 6 criterios automáticos pasan ([después](evidencia/criterios-entrada-2-despues.txt)).

## 7. Criterios de salida y decisión

| ID | Criterio de salida |
|---|---|
| S-1 | Las 3 sesiones se hicieron con todos los roles, y cada revisor registró su preparación |
| S-2 | Los 15 ítems tienen un resultado anotado (✅ / hallazgo) en cada sesión que los cubre |
| S-3 | Cada hallazgo tiene todos los campos obligatorios y una severidad decidida |
| S-4 | Cada hallazgo **Crítico** está `Cerrado`, con su prueba y su mutación nuevas que lo vigilan |
| S-5 | Cada hallazgo **Mayor** está `Cerrado` o tiene responsable, plazo y verificación acordados |
| S-6 | La suite completa y las mutaciones siguen en verde después del retrabajo, y el contrato pasa el lint |

**Decisión** (la toma el moderador al final del seguimiento):

| Resultado | Cuándo |
|---|---|
| **Aceptado** | S-1..S-6 y ningún Crítico ni Mayor abierto |
| **Aceptado con condiciones** | Sin Críticos abiertos; Mayores con plazo (S-5) |
| **Re-revisión** | Algún Crítico sin cerrar; densidad > 15 hallazgos/KLOC en una sesión; o un cambio de retrabajo > 20 % de las líneas de una sesión |

**Estado al cerrar la autorrevisión:** hay un **Crítico abierto** (AR-01). Con lo conocido hoy, el resultado sería «Re-revisión». AR-01 tiene que cerrarse antes de la reunión o en el retrabajo.

## 8. Formato de hallazgos

Se usa el [formato de hallazgos](formato-hallazgos.md), una fila por hallazgo en [`plantillas/registro-hallazgos.csv`](plantillas/registro-hallazgos.csv). Los campos obligatorios son:
- `id`, `item`, `ubicacion`, `descripcion` y `evidencia`;
- `regla_afectada`, `severidad`, `tipo`, `responsable`, `estado` y `encontrado_en`.

## 9. Métricas

| Métrica | Umbral | Uso |
|---|---|---|
| Ritmo de preparación | 150–200 LOC/h | Por encima, se re-prepara |
| Densidad de hallazgos por sesión | > 15/KLOC → re-revisión | Decisión |
| Proporción de hallazgos nuevos en la reunión vs. la autorrevisión | Informativa | Calidad de la autorrevisión |
| Tiempo de cierre por severidad | Crítico ≤ 3 días; Mayor ≤ 7 | Plan SQA (semana 14) |

## 10. Riesgos de la propia revisión

| Riesgo | Mitigación |
|---|---|
| Los revisores confían en que «las pruebas pasan» | Cada ítem pide buscar el caso que las pruebas **no** cubren |
| El autor defiende en lugar de aclarar | Regla 3; el moderador corta |
| Todos miran lo mismo (tenant) y nadie las dependencias | Perspectivas asignadas; la métrica de hallazgos por perspectiva |
| El paquete cambia durante la revisión | E-1: commit congelado; cualquier cambio reinicia la entrada |
| Equipo pequeño: los revisores son también autores de otros módulos | Regla de independencia (§3); el docente como R4 |
