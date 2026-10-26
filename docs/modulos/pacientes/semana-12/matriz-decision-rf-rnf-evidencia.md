# Matriz decisión → RF/RNF → evidencia (semanas 7–12)

**Estudiante:** Boris Alexander Quiroa Orellana · **Proceso:** «registro, búsqueda y actualización segura de un paciente»

Continúa la matriz de la primera evaluación parcial ([semana 6](../semana-06/matriz-decision-evidencia.md), D-01..D-21), que cubrió arquitectura, capas, Repository y contrato. Esta cubre **componentes, UX, accesibilidad y movilidad**. Cada decisión se enlaza con:
- el requisito que la motiva;
- el lugar donde vive;
- la evidencia **ejecutable** que la comprueba.

**Estado verificable hoy:**

| Verificación | Resultado |
|---|---|
| `php tests/ejecutar.php` (Micro-HIS) | 87 pruebas: 85 pasan, 0 fallan, 2 omitidas |
| `php tests/mutaciones.php` | 26/26 detectadas |
| `node herramientas/paridad.cjs` | 37/37 iguales entre prototipo y kernel |
| `node herramientas/recorrido.cjs` | 36 comprobaciones, axe 0 |
| `verificar-responsive.cjs` (semana 10) | 39 vistas, 0 fallas |
| `contraste.py` (semana 9) | Propuestas 0 fallas |

## 1. Requisitos que se formalizan en esta evaluación

Las semanas 8 a 11 aplicaron criterios de UX, accesibilidad y movilidad que no estaban escritos como requisitos. Se formalizan aquí para poder trazarlos.

| ID | Requisito | Criterio de aceptación | Origen |
|---|---|---|---|
| RNF-12 | **Accesibilidad:** el flujo cumple WCAG 2.2 AA en escritorio y en móvil. | axe-core sin infracciones en los 30 pasos del recorrido; foco dentro de los diálogos; resultados y errores anunciados. | Semanas 9 y 11 |
| RNF-13 | **Movilidad:** el flujo se usa de 320 a 430 px sin perder funciones. | Sin desplazamiento horizontal; objetivos táctiles ≥ 44 px; campos ≥ 16 px; hospital activo visible (R-T1..R-T4). | Semana 10 |
| RNF-14 | **Conexión limitada:** un corte de red no duplica pacientes ni sobrescribe ediciones. | Reintentar el registro con la misma `Idempotency-Key` devuelve el mismo código; el `PUT` no se reintenta solo. | Semanas 5 y 10 |
| RNF-15 | **Privacidad en el cliente:** ningún dato personal en la URL ni guardado en el dispositivo. | Ningún `href` ni URL visitada lleva nombre, documento ni teléfono (R-D1); no hay `localStorage` de pacientes. | Semanas 8 y 10 |
| RF-17..RF-19, RNF-11 | Cambio solicitado B-4 | Ver el [registro del cambio](registro-cambio-solicitado.md) | Semana 12 |

## 2. Matriz

| ID | Decisión | RF / RNF / regla | Dónde vive | Evidencia | Sem. |
|---|---|---|---|---|:-:|
| D-22 | **`RevisionDeFicha`**: un solo dueño del orden de las reglas de la ficha (visibilidad → datos → edición → protección → duplicados) | R-07, R-11, RF-15 | `Dominio/Servicios/RevisionDeFicha.php` | REF-01..03 (también sobre el código anterior), M21; Ce 16 → 14 y P 5 → 1 ([acoplamiento](../semana-07/evidencia/acoplamiento-2-despues.txt)) | 7 |
| D-23 | **`Capacidades`** en lugar de booleanos de permiso; `ControlAcceso::capacidades()` como único traductor; fail-closed | R-05, RNF-10 | `Dominio/Modelo/Capacidades.php`, `ControlAcceso` | PROT-02..06, M24 | 7 |
| D-24 | **El frontend no decide reglas**: muestra lo que manda el servidor; `null` = «Reservado» | RNF-10, R-11 | Componentes de la semana 7; `app.js` | Paridad 37/37 ([paridad.txt](evidencia/paridad.txt)); recorrido paso 15 (sin «Sofía» ni teléfono) | 7·11 |
| D-25 | **Buscar antes de registrar**, con precarga | R-02, R-03 | Flujo de admisión (semana 8), W1 → W2, `#/pacientes` | Recorrido pasos 02–03 | 8 |
| D-26 | **Catálogo de mensajes ligado a los códigos del backend** | RF-01..RF-16 (respuestas), RNF-12 | [Catálogo M-01..M-22](../semana-08/semana-08-ux-pacientes.md#6-catálogo-de-mensajes) | Paridad: los 37 códigos coinciden; API-03 | 8 |
| D-27 | **Nada en la URL ni en el dispositivo** | R-04, RNF-15 | `consultaDe()` y el estado en memoria de `app.js`; regla R-D1 | `verificar-responsive.cjs` (R-D1 en 39 vistas); el recorrido comprueba la URL en cada paso | 8·10 |
| D-28 | **Motivo solo si cambia la identidad, con la MISMA normalización del backend** | R-07 | `normalizar()` en `app.js` = `Texto::normalizar` | Paridad paso 29 («Lucía» → «Lucia»: 200); recorrido «cambiar el teléfono no pide motivo» | 9 |
| D-29 | **Conflicto 409: reaplicar en memoria**, sin portapapeles | R-06, RNF-15 | `reaplicar()` en `app.js` | Paridad paso 7 (409); [captura del conflicto](../semana-10/capturas/m4-editar-conflicto-320.png) | 9·10 |
| D-30 | **Contraste corregido**: `#5F6670` y `#7D838A`; avatar `#1F6B66` | RNF-12 (WCAG 1.4.3, 1.4.11) | `estilos.css`, `datos.js` | `contraste.py` (propuestas 0 fallas); axe 0 en 30 pasos | 9·11 |
| D-31 | **Error crítico sin revelar**: «Esta persona ya está registrada» ante `RESERVADO`, con salida segura | R-11, RF-16, R-02 | `resultadoRegistroHtml()` en `app.js` | PROT-05; recorrido paso 12 (no contiene `P-000005`); paridad pasos 15–18 | 6·11 |
| D-32 | **Mobile-first**: 5 breakpoints y reglas R-T1..R-T4 | RNF-13 | `estilos.css` | `verificar-responsive.cjs`: 39/39 ([semana 10](../semana-10/evidencia/verificacion-responsive.txt)) | 10 |
| D-33 | **Registro idempotente; el `PUT` nunca se reintenta solo** | RNF-14, R-02, R-06 | `registrar()` y `guardar()` en `app.js` | [reintento.php](../semana-10/evidencia/reintento.txt); paridad pasos 3 y 7; recorrido 06 → 07 | 5·10 |
| D-34 | **Sin conexión no se encola ni se guarda**: «requiere conexión» | RNF-14, RNF-15, R-02 | Estados sin conexión (semanas 10 y 11) | [Captura M1 sin conexión](../semana-10/capturas/m1-buscar-sin-conexion-360.png); semana 10 §7.4 | 10 |
| D-35 | **Una sola app para escritorio y móvil** | RNF-12, RNF-13 | `prototipo/` | El recorrido corre los mismos 15 pasos en 1280 y 360 px | 11 |
| D-36 | **Diálogo modal accesible**: resto `inert`, foco en el título, `Esc` | RNF-12 (2.1.2, 2.4.3) | `abrirDialogo()` en `app.js` | Recorrido: «Tab ×8 se queda dentro del diálogo» (escritorio y móvil) | 11 |
| **D-37** | **Cambio B-4**: campo `documento` ausente = sin cambio; `nombres` y `apellidos` en el detalle; contrato 1.2.0 | **RF-17, RF-18, RF-19, RNF-11** | `RevisionDeFicha`, `Paciente::detalle()`, `FormularioPaciente` | B4-01..04 ([rojo](evidencia/cambio-1-antes-rojo.txt) → [verde](evidencia/cambio-3-despues-verde.txt)); M25, M26; paridad pasos 8–12; [lint](evidencia/salida-lint-openapi.txt) | 12 |

## 3. Cómo leer la matriz en la defensa

1. Elegir una decisión.
2. Abrir el archivo de «Dónde vive».
3. Ejecutar la evidencia.
4. Para demostrar que la prueba no pasa por casualidad, aplicar la mutación o el cambio inverso: el defecto inyectado debe hacerla fallar.

**Ejemplo en vivo:** D-37. En `FormularioPaciente` (Presentación), cambiar `array_key_exists('documento', $cuerpo)` por `true` (es la mutación M25). Fallan B4-01 y B4-04: un `PUT` sin documento vuelve a borrarlo.

## 4. Riesgos que la matriz no cierra

| Riesgo | Por qué sigue abierto |
|---|---|
| La paridad cubre 37 situaciones, no todas | Una regla fuera de la secuencia podría diferir entre el prototipo y el backend. |
| Accesibilidad medida con axe, sin lector de pantalla real ni usuarios | axe detecta cerca de un tercio de los problemas; faltan NVDA, TalkBack y pruebas con personal de admisión. |
| Brechas B-1..B-3 | Estado del paciente, pedir revisión de una coincidencia e historial de la ficha siguen sin operación en el contrato. |
| PostgreSQL | Las 2 pruebas de integración siguen sin ejecutarse. |
