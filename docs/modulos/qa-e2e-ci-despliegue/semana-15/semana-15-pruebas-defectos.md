# Semana 15 — Matriz de pruebas, pruebas por técnica y defectos registrados

| | |
|---|---|
| **Estudiante** | Boris Alexander Quiroa Orellana (@bquiroao) |
| **Objeto** | `micro-his-e2e` (contrato 1.3.0) |
| **Pruebas nuevas** | [tests/Calidad/Semana15Prueba.php](../micro-his-e2e/tests/Calidad/Semana15Prueba.php) |

## 1. Matriz RF/RNF → pruebas

| Requisito | Pruebas | Mutación que lo rompe |
|---|---|---|
| RF-01 escenarios con datos sintéticos | DOM-01, HTTP-03 | M1 |
| RF-02 versión con digest único | APP-09 (`DIGEST_EN_USO`, `VERSION_EXISTENTE`) | — (también lo exige la base: UNIQUE) |
| RF-03 plan con todos los críticos, congelado | DOM-02, DOM-03, APP-09 | M2, M3 |
| RF-04 ejecución sobre el digest | DOM-04, APP-09 | M4 |
| RF-05 resultado con evidencia; fail-closed | APP-04, APP-05, PER-05, CAR-01 | M10, M12, M31, M33 |
| RF-06 reintento que pasa = INESTABLE | DOM-05, APP-02 | M5 |
| RF-07 completar o abortar; solo se evalúa una COMPLETADA | APP-10, DOM-06 | — |
| RF-08 cuarentena | **Sin prueba: no implementado** (RT-02, aceptado) | — |
| RF-09 gate con reglas a–e y digest; vista previa | DOM-08, DOM-10, APP-02, APP-11, NEG-04 | M8, M9, M34 |
| RF-10 evaluaciones sin editar; cuenta la última | CAM-02, INT-10 | M26 |
| RF-11 excepción | **Sin prueba: no implementado** (RT-02, aceptado) | — |
| RF-12 desplegar solo lo aprobado y vigente, mismo digest; **un reintento no duplica** | CAM-01…06, INT-10, **UNI-01…03, BLA-01, BLA-02, NEG-01, NEG-02** | M26–M30, **M35** |
| RF-13 idempotencia del reporte | APP-03, DOM-11, INT-05, NEG-03 | M11, M32 |
| RF-14 permisos por tenant | APP-06, HTTP-02, CAM-06, NEG-04 | — |
| RNF-01 DIP | ARQ-01…06 | — |
| RNF-04 integridad de evidencia | PER-04, PER-05 | M31 |
| RNF-07 reproducibilidad | CAM-03, INT-10 | M27 |
| RNF-08 gate < 1 s (300 escenarios) | `bin/medir-gate.php` (semana 14): 0,41 ms p95 | — |

## 2. Pruebas por técnica (nuevas)

| Técnica | Id | Qué prueba | Antes de corregir | Después |
|---|---|---|---|---|
| **Unitaria** | UNI-01 | `Despliegue::esReintento`: lo mismo a los 2 min es reintento | ❌ (no existía) | ✅ |
| **Unitaria** | UNI-02 | Otro resultado del smoke no es reintento | ❌ | ✅ |
| **Unitaria** | UNI-03 | Sin previo, no; **límite de la ventana**: 15 min sí, 15 min 1 s no | ❌ | ✅ |
| **Caja blanca** | BLA-01 | Rama «reintento» de `RegistrarDespliegue`: 200, el mismo id, sin registro ni traza nuevos | ❌ | ✅ |
| **Caja blanca** | BLA-02 | Rama «nuevo»: con otra guía, 201 y un segundo registro | ✅ (fija lo correcto) | ✅ |
| **Caja negra** | NEG-01 | Mismo POST dos veces por HTTP → 201, 200 y 1 despliegue | ❌ (201, 201, 2) | ✅ |
| **Caja negra** | NEG-02 | Partición inválida de la guía (`guia-26.3`, `GUIA-2026.3`) → 422 | ✅ | ✅ |
| **Caja negra** | NEG-03 | Valores límite de `intentos` (0..5): 5 → 201; 6 y −1 → rechazo | ✅ | ✅ |
| **Caja negra** | NEG-04 | Partición por rol en la vista previa: Release 200; pipeline y Ops 403 | ✅ | ✅ |

**Caja blanca:** `RegistrarDespliegue` tiene ahora dos caminos después de las reglas de R-11. BLA-01 y BLA-02 cubren los dos, y UNI-01…03 cubren cada condición de `esReintento`, incluido el borde de la ventana.

**Caja negra:** NEG-01…04 se diseñaron desde el contrato 1.3.0 (particiones y límites), sin mirar el código.

## 3. Comandos y resultados

| # | Comando | Resultado | Evidencia |
|---|---|---|---|
| 1 | `php tests/ejecutar.php` (antes de corregir) | 98 pruebas, **5 fallan**: UNI-01…03, BLA-01 y NEG-01 | [1-antes…](evidencia/1-antes-de-corregir-def01.txt) |
| 2 | `E2E_DB_DSN=… php tests/ejecutar.php` | **98/98**, 0 omitidas | [2-suite-postgresql](evidencia/2-suite-postgresql.txt) |
| 3 | `bash ../semana-13/herramientas/reproducir-rt01.sh` | 201, **200**, 1 despliegue (antes: 201, 201, 2) | [3-rt01-después](evidencia/3-rt01-despues.txt) |
| 4 | `E2E_DB_DSN=… php tests/mutaciones.php` | **35/35** detectadas; M35 (DEF-01) la detectan UNI-01, UNI-03, BLA-01 y NEG-01 | [4-mutaciones](evidencia/4-mutaciones.txt) |

**Capturas** de las corridas: [capturas/](capturas/).

## 4. Defectos registrados

| Id | Origen | Descripción | Severidad | Prueba que lo reproduce | Corrección | Estado |
|---|---|---|---|---|---|---|
| DEF-01 | Revisión técnica (RT-01) | Un reintento idéntico del registro de despliegue creaba un segundo despliegue | Mayor | NEG-01, BLA-01 (y `reproducir-rt01.sh`) | `Despliegue::esReintento` y la rama en `RegistrarDespliegue`: 200 `DESPLIEGUE_YA_REGISTRADO`; contrato 1.3.0 | **Cerrado** (98/98, M35 detectada) |
| DEF-02 | Recorrido de la semana 11 (H-1) | La pantalla del gate mostraba 5 reglas; el servidor evalúa 6 | Menor | Recorrido A3 (exige 6) | La consola lista las 6 del servidor | Cerrado (semana 11) |
| DEF-03 | Revisión visual de la semana 11 (H-3) | Tras el 409, el contexto seguía diciendo «Aprobada» | Menor | Recorrido B3 | Contexto y acción actualizados | Cerrado (semana 11) |
| DEF-04 | Revisión técnica (RT-03) | La vigencia de 72 h está duplicada en la consola | Menor | — | Exponer `vence_en` en la trazabilidad | Abierto |
| DEF-05 | Revisión técnica (RT-06) | No hay listado de versiones | Menor | — | `GET /versiones` | Abierto |
| DEF-06 | Mutación de la semana 7 (M32) | Ninguna prueba exigía que `repite()` comparara la ejecución | Menor | DOM-11 | Prueba agregada | Cerrado (semana 7) |

**Abiertos:** DEF-04 y DEF-05, ambos menores. RT-02 (cuarentena y excepción) sigue aceptado como fuera de alcance.
