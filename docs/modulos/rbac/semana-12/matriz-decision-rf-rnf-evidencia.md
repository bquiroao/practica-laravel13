# Matriz decisión → RF/RNF → evidencia (semanas 7–12)

**Estudiante:** Boris Alexander Quiroa Orellana · **Proceso:** «asignación de rol, autorización de una operación y denegación trazable»

Esta matriz continúa la de la primera evaluación ([semana 6](../semana-06/matriz-decision-evidencia.md), D-01 a D-17), que cubrió la arquitectura. Aquí van las decisiones de **componentes, UX, accesibilidad y movilidad** (semanas 7 a 11) y el **cambio solicitado** de esta semana (D-32).

Cada fila dice:
- qué se decidió;
- qué requisito la motiva;
- dónde vive;
- con qué evidencia **verificable** se comprueba, es decir, un comando o una prueba que fallaría si la decisión se rompiera.

## 1. Requisitos que se formalizan en esta evaluación

Los RF/RNF de la semana 2 (RF-01..RF-10, RNF-01..RNF-09) y de la semana 6 (RF-11..RF-14) eran de comportamiento del servidor. Las semanas 8 a 11 usaron criterios de UX, accesibilidad y movilidad que **nunca se escribieron como requisitos**. Se formalizan aquí, con el mismo criterio con que ya se venían verificando:

| ID | Tipo | Requisito | Criterio de aceptación | Desde |
|---|---|---|---|---|
| RNF-10 | Usabilidad de la denegación | Toda denegación muestra una referencia copiable y el siguiente paso, y nunca revela motivo, rol ni permiso. | El recorrido automático comprueba en EC-4: hay referencia, el foco está en el aviso y no aparecen «MEMBRESIA», «revoc» ni el nombre del rol. | S8 |
| RNF-11 | Accesibilidad | Las pantallas del flujo cumplen WCAG 2.2 AA y se pueden operar solo con teclado. | 0 infracciones de axe-core (A/AA) en las 32 pantallas del recorrido. Diálogo: foco inicial en «Cancelar», `Tab` atrapado, `Esc` devuelve el foco. Contraste medido ≥ 4.5:1 en texto y ≥ 3:1 en no texto. | S9 |
| RNF-12 | Movilidad | El flujo funciona de 320 px a escritorio. | Sin desplazamiento horizontal, objetivos ≥ 44 px, campos ≥ 16 px y hospital activo visible en todas las vistas verificadas. | S10 |
| RNF-13 | Conexión limitada | Sin red no se autoriza ni se encolan cambios de permisos. Una escritura sin respuesta se muestra como «resultado incierto». | Se reintenta solo si un segundo envío no repite el efecto: `reintento.php` muestra cuándo sí y cuándo no. | S10 |
| **RF-15** | Funcional (nuevo) | La interfaz puede saber qué permisos tiene la sesión, en el hospital activo, con una sola consulta. | PEF-01, PEF-07: `POST /api/v1/autorizaciones/permisos-efectivos` devuelve el mapa. | **S12** |
| **RNF-14** | Trazabilidad sin ruido (nuevo) | La bitácora registra intentos reales de acceso, no las consultas que la interfaz hace para decidir qué mostrar. | PEF-02 (0 eventos), PEF-10 (los intentos siguen auditados), `ruido-auditoria.cjs`: **9 → 0** denegaciones «fantasma». | **S12** |

## 2. Matriz

Comandos de referencia:
- `php tests/ejecutar.php` en `micro-his-rbac/` da **80 pruebas: 78 pasan, 0 fallan y 2 se omiten** (PostgreSQL).
- Las herramientas de `semana-12/herramientas/` dan:
  - paridad: **38/38**;
  - recorrido: **42 comprobaciones, 0 fallas**;
  - ruido en la bitácora: **9 → 0**.

| ID | Decisión | RF / RNF / regla | Dónde vive | Evidencia verificable | Sem. |
|---|---|---|---|---|:-:|
| D-18 | **La interfaz no decide.** `SiPermiso` y `permisoGuard` solo muestran u ocultan; cada operación se vuelve a autorizar en el servidor. | RF-05, R-04, RNF-01 | Componentes Vue diseñados en la S7; `app.js` (guard, `puede`) | **EC-4**: Ana ve «Guardar», el servidor responde 403. Paridad paso 23 (403 tras revocar). | 7·11 |
| D-19 | Refactor: `CambioDeMembresia` en el Dominio + `RegistradorDeCambios` en Aplicación | RNF-06, R-07 | `src/Dominio/Cambios/CambioDeMembresia.php`, `src/Aplicacion/Servicios/RegistradorDeCambios.php` | REF-01..03 (caracterización); Ce de `AsignarRol` 13 → 8 ([S7](../semana-07/semana-07-componentes-refactor.md)) | 7 |
| D-20 | Catálogo de mensajes M-01..M-31, cada uno ligado a un código real del backend | RF-03, RF-08, RF-14, RNF-10 | [S8 §6](../semana-08/semana-08-ux-rbac.md); `DATOS.mensajes` en el prototipo | Paridad: los 38 pasos (todos los códigos HTTP y de negocio) coinciden con el kernel real. | 8·11 |
| D-21 | **403 sin motivo + referencia** copiable, compartible y agrupada en bloques | RF-08, R-08, RNF-04, RNF-10 | `avisoHtml()` tipo `denegado`; `ErrorVista::accesoDenegado` | Recorrido EC-4 (sin motivo, foco en el aviso); EC-5 (el auditor la localiza aunque la pegue agrupada) | 8·10·11 |
| D-22 | **Confirmar la asignación con el nombre** de la persona y los permisos que obtiene | RF-01, RF-03 (BL-01, H-01 de la S9) | `vAsignar` paso 3 | Recorrido CF-6 («recibirá el rol Enfermería…») | 9·11 |
| D-23 | Revocar en un **diálogo destructivo**: consecuencias, motivo ≥ 10, casilla, foco en «Cancelar», fondo `inert` | RF-11, RF-14, RNF-11 (BL-05, BL-07) | `abrirRevocar()` | TEC-1 (foco), 12 `Tab` sin salir, `Esc`; TEC-2 (revocación solo con teclado) | 8·9·11 |
| D-24 | Evaluar con **heurísticas + WCAG** y priorizar por **severidad × impacto RBAC** | RNF-11 | [S9 checklist](../semana-09/checklist-evaluacion.md) (93 ítems) | 16 hallazgos con recorte; backlog BL-01..16 con criterio verificable | 9 |
| D-25 | **Paleta corregida** (`#5F6670`, `#7D838A`) y avatares ≥ 4.5:1 | RNF-11 (BL-02, BL-04, BL-15) | `estilos.css`, `datos.js` | `contraste.py`: 0 propuestas fallan; axe `color-contrast` = 0 | 9·11 |
| D-26 | **Mobile-first** con breakpoints 360 / 431 / 768 / 1024 | RNF-12 | `estilos.css` | `verificar-responsive.cjs` (S10): 31 vistas, 0 fallas. Recorrido móvil (S12): 16 pantallas, 0 fallas. | 10·11 |
| D-27 | **Tabla → tarjetas** bajo 1024 px; **hoja inferior** bajo 768 px | RNF-12 | `vRoles`, `.hoja` | Capturas `cf-04` y `tec-01` en escritorio y en móvil | 10·11 |
| D-28 | **Conexión limitada**: sin autorización offline, sin colas de cambios de permisos, reintento solo si es seguro (R-C1) | RNF-13, R-03, RF-11 | `confirmarAsignacion()`, `enviarRevocacion()` | `reintento.php`: asignar sin fin SEGURO, con fin **NO SEGURO**, revocar SEGURO. Demostración «Perder la próxima respuesta». | 10·11 |
| D-29 | **Una sola aplicación** para escritorio y móvil; **una sola fuente** de nombres, estados y mensajes | RNF-12, consistencia | `prototipo/datos.js` | Recorrido 42/42 en las dos vistas; inconsistencia de formato de referencia encontrada y corregida (S11) | 11 |
| D-30 | **Servidor simulado con paridad medida** contra el kernel real | RNF-07 | `api-simulada.js`, `paridad.cjs`, `paridad.php` | **38/38** iguales, incluido el número de eventos de la bitácora tras cada paso | 11·12 |
| D-31 | **Guard de ruta** + caché de 30 s solo en memoria, borrada al cambiar de usuario | RF-05, RF-07, RNF-04, R-01 | `navegar()`, `est.efectivos` | EC-1 (URL directa → «No tienes acceso» + referencia auditada); EC-2/EC-4 (caché vieja, servidor manda) | 8·11 |
| **D-32** | **Cambio solicitado (B-6): permisos efectivos.** Las consultas *pasivas* de la UI no se auditan; los *intentos* sí. Misma política que el PDP. | **RF-15, RNF-14**, RF-09, R-06 | `ConsultarPermisosEfectivos`, `POST /api/v1/autorizaciones/permisos-efectivos`, OpenAPI **v1.2.0**, `permisosEfectivos()` + `intentar()` en el prototipo | PEF-01..10 ([rojo](evidencia/cambio-1-antes-rojo.txt) → [guardas](evidencia/cambio-2-guardas-contrato-y-arquitectura.txt) → [verde](evidencia/cambio-3-despues-verde.txt)); PEF-03 concordancia con el PDP; ruido **9 → 0** ([evidencia](evidencia/cambio-5-ruido-antes-despues.txt)); recorrido: Ana con **exactamente 2** denegaciones, las 2 reales | 12 |

## 3. Cómo leer la matriz en la defensa

1. Elegir una fila.
2. Abrir el archivo de «Dónde vive».
3. Correr la evidencia. Si la decisión se rompiera, ese comando fallaría. Esto ya se comprobó en vivo:
   - El cambio D-32 rompió **a propósito** dos guardas, API-01 (contrato) y ARQ-08 (rutas sin permiso). Se actualizaron con justificación, no se silenciaron.
   - En la S11, axe encontró 24 fallas reales que hubo que corregir antes de entregar.

## 4. Riesgos que la matriz no cierra

| Riesgo | Estado |
|---|---|
| El servidor del prototipo es simulado | Mitigado con la paridad 38/38. Falta probar con el backend real por red. |
| Accesibilidad verificada con axe y teclado, no con lectores de pantalla | Pendiente: NVDA, VoiceOver y TalkBack |
| B-1 (membresías) y B-4 (búsqueda por nombre) existen solo en la simulación | Pendientes del contrato |
| B-5 (`Idempotency-Key`) | Pendiente. R-C1 lo mitiga en la UI. |
| RNF-09 (rendimiento < 50 ms p95) | Sigue pendiente de medir con PostgreSQL (S15) |
