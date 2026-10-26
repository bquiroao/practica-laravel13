# Semana 12 — Segunda evaluación parcial: defensa de componentes, UX, accesibilidad y movilidad

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** RBAC: roles, permisos y protección de rutas

**Proceso:** «asignación de rol, autorización de una operación y denegación trazable»

## Entregables

| Pedido | Dónde |
|---|---|
| Presentación breve | [presentacion/semana-12-defensa-rbac.pptx](presentacion/semana-12-defensa-rbac.pptx): 8 diapositivas con notas del orador. Versión [PDF](presentacion/semana-12-defensa-rbac.pdf). |
| Recorrido navegable | [prototipo/index.html](prototipo/index.html) (doble clic) + guion [recorrido-navegable.md](recorrido-navegable.md) + [mapa](diagramas/semana-12-mapa-navegacion.png). El mismo recorrido, automatizado: **42/42** ([evidencia](evidencia/recorrido.txt)). |
| Matriz decisión → RF/RNF → evidencia | [matriz-decision-rf-rnf-evidencia.md](matriz-decision-rf-rnf-evidencia.md): D-18 a D-32, más RNF-10..14 y RF-15 formalizados |
| Respuesta documentada a un cambio solicitado | [registro-cambio-solicitado.md](registro-cambio-solicitado.md): B-6, permisos efectivos. Pruebas primero, guardas, medición antes/después y costo. |

## 1. Qué se defiende, en una frase por tema

| Tema | Decisión central | Cómo se demuestra con el prototipo |
|---|---|---|
| **Componentes** | La interfaz solo muestra; el servidor decide cada operación. | B4: Ana ve «Guardar», pulsa y recibe 403, porque el servidor volvió a autorizar. |
| **Roles y estados** | Una sola fuente de nombres y estados (● Activo · ◌ Vencido · ⊘ Revocado), con icono y texto | A3 y la tabla o tarjetas de roles; iguales en escritorio y en móvil |
| **Errores** | Toda denegación da referencia y siguiente paso, nunca el motivo. Cada mensaje sale de un código real. | B1 (ruta), B4 (operación), B5 (el auditor ve el motivo) |
| **Riesgos** | Confirmar con el nombre (asignar a otra persona); diálogo destructivo (revocar por error); reintento solo si es seguro (red) | A5, C1–C3, C4 |
| **Accesibilidad** | WCAG 2.2 AA medido; teclado completo en el diálogo | axe = 0 en 32 pantallas; TEC-1/TEC-2 |
| **Movilidad** | Una app mobile-first; tabla → tarjetas; hoja inferior | 16 capturas móviles, 0 fallas |
| **Cambio solicitado** | Mostrar = permisos efectivos (sin evento); intentar = PDP (auditado) | Ruido 9 → 0; Ana con exactamente 2 eventos reales |

## 2. Cifras verificables

| Comando | Resultado |
|---|---|
| `php micro-his-rbac/tests/ejecutar.php` | **80 pruebas: 78 pasan, 0 fallan, 2 omitidas** (PostgreSQL). Antes del cambio eran 70: 68 pasan, 2 omitidas. |
| `npx @redocly/cli@1.34.3 lint micro-his-rbac/api/openapi.yaml` | Válido (v1.2.0) |
| `node semana-12/herramientas/paridad.cjs` | **38/38** iguales con el kernel PHP, incluido el número de eventos de la bitácora tras cada paso |
| `node semana-12/herramientas/recorrido.cjs` | **42 comprobaciones, 0 fallas** (escritorio + móvil, axe, 44 px, teclado, bitácora) |
| `node semana-12/herramientas/ruido-auditoria.cjs ../semana-11/prototipo` y `… prototipo` | **9 → 0** denegaciones «fantasma» |

## 3. Estructura de la carpeta

| Carpeta / archivo | Contenido |
|---|---|
| `prototipo/` | Prototipo de la S11 con el cambio aplicado: `permisosEfectivos()`, `intentar()` y la API simulada con la ruta nueva |
| `herramientas/` | `paridad.*` (ahora compara también la bitácora), `recorrido.cjs` (+4 comprobaciones de bitácora), `ruido-auditoria.cjs` (nuevo) |
| `capturas/{escritorio,movil}/` | 32 capturas del recorrido, regeneradas con el cambio |
| `evidencia/` | Rojo → guardas → verde, lint, acoplamiento antes/después, ruido antes/después, paridad, recorrido |
| `diagramas/` | Mapa de navegación actualizado (guard con permisos efectivos) |
| `presentacion/` | `.pptx` y `.pdf` |

**Cambios en el Micro-HIS** (`../micro-his-rbac/`):

| Tipo | Archivos |
|---|---|
| Nuevos | `ConsultarPermisosEfectivos`, `FormularioPermisosEfectivos`, `PermisosEfectivosVista`, `tests/Aplicacion/PermisosEfectivosPrueba.php` |
| Modificados | `AutorizacionControlador`, `Rutas`, `bootstrap/contenedor.php`, `api/openapi.yaml` (v1.2.0), ARQ-08, API-03 (+2 escenarios), README |

## 4. Limitaciones que se declaran en la defensa

- **Servidor simulado.** El prototipo usa un servidor simulado. La paridad está medida (38/38), pero falta probarlo contra el backend por red con PostgreSQL. CON-PDO e INT-00 siguen omitidas.
- **Sin lector de pantalla real.** La accesibilidad se verificó con axe y con teclado, no con NVDA, VoiceOver ni TalkBack.
- **Brechas abiertas.** B-1, B-4 y B-5 siguen abiertas. B-6 queda **cerrada** con este cambio.
- **RNF formalizados ahora.** Los RNF-10..13 se escribieron en esta entrega a partir de criterios que ya se aplicaban desde la S8. Se declara así, para no presentarlos como si hubieran existido desde el principio.
