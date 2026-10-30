# Semana 15 — Pruebas unitarias, de caja blanca y de caja negra de Citas médicas y agenda

| | |
|---|---|
| **Tarea Odoo** | 2472 — Semana 15: Pruebas unitarias, caja blanca y caja negra |
| **Flujo** | «Solicitud, validación de disponibilidad y confirmación o cancelación de cita» |
| **Objeto de prueba** | [`micro-his-citas/`](../micro-his-citas/) de `develop` (semanas 3 y 4), sin modificarlo |
| **Ejecutor** | [pruebas/pruebas-s15.php](pruebas/pruebas-s15.php) · salida: [evidencia/salida-pruebas.txt](evidencia/salida-pruebas.txt) |
| **Estudiante** | Josué David Morales Ramírez |

## 1. Alcance y entorno

Se prueban las reglas propias de la agenda: citas, disponibilidad, confirmación, cancelación y concurrencia. No se prueban la autenticación, el RBAC ni el registro de médicos (son de otros módulos). CN-04 solo verifica que la agenda **rechace** a quien no está autorizado. Las pruebas se ejecutan con PHP 8.4.23 sin dependencias. Las unitarias y las de caja blanca fijan «ahora» en 1000 (RNF-03). Las de caja negra levantan `php -S` con un SQLite temporal que trae dos médicos vigentes, uno inactivo y una cita vencida, y se borra al final. Todos los datos son ficticios.

## 2. Matriz RF/RNF → prueba

| Requisito | Unitarias | Caja blanca | Caja negra | Estado |
|---|---|---|---|---|
| RF-01 Cita futura, con fin posterior y motivo (R-01, R-06) | U-02 | CB-01 (C1, C2) | CN-01, CN-02, CN-06 | ✘ DEF-01, DEF-03 |
| RF-02 Sin cruce del médico (R-02) | U-03 | CB-01 (C4) | CN-02 | ✔ |
| RF-03 Sin cruce del paciente (R-03) | U-03 | CB-01 (C5) | CN-03 | ✔ |
| RF-04 Especialidad vigente (R-04, R-05) | — | CB-01 (C3) | CN-01 | ✔ (sin fechas de vigencia: H-05 de la S13) |
| RF-05 Cancelar con motivo libera el horario (R-07) | U-01, U-04 | CB-02 | CN-03, CN-05 | ✔ |
| RF-06 / RNF-05 Motivo de cada cancelación (R-10) | U-04 | — | — | ✔ (el «quién» es H-04 de la S13) |
| RNF-01 Personal autorizado del mismo hospital (CP-04 de la S14) | — | — | CN-04 | ✘ DEF-02 |
| RNF-03 «Ahora» fijo para probar | U-02 | CB-01 | — | ✔ |
| Concurrencia: carrera al cambiar (E7) y al guardar (E6, S6) | U-05 | CB-02 | — | ✔; U-05 pasa con el código de la S6 |
| Errores con código y HTTP fijo | U-06 | — | CN-05, CN-07 | ✘ DEF-04 |

## 3. Pruebas unitarias

| ID | Unidad | Casos | Resultado |
|---|---|---|---|
| U-01 | `EstadoCita::validarCambioA` | Los 16 pares: programada → confirmada o cancelada, confirmada → cancelada; los otros 13 dan E7 | ✔ |
| U-02 | `Horario::validar` | Inicio = ahora, fin = inicio y fin < inicio dan E2; [1001, 1002) se admite | ✔ |
| U-03 | `ServicioCitas` + InMemory | Intervalo [inicio, fin): la cita contigua se admite; con 1 segundo de cruce, E4 (médico) y E5 (paciente) | ✔ |
| U-04 | `ServicioCitas` + InMemory | Cancelar con «  » = E2 y sigue programada; con motivo, el motivo queda guardado y el horario se libera | ✔ |
| U-05 | Carrera al guardar (S6) | `HorarioTomado` se traduce a E6 | Pendiente en `develop`; ✔ contra la S6 |
| U-06 | Códigos de rechazo | Los 6 códigos de dominio y aplicación están en `VistaJson::HTTP` | ✔ |

## 4. Caja blanca

**CB-01 — caminos básicos de `ServicioCitas::solicitar`.** Tiene 4 decisiones propias (motivo, especialidad, cruce del médico y cruce del paciente), así que V(G) = 5. A eso se suma la salida por la excepción de `Horario`: 6 caminos. Un doble espía con respuestas configurables cuenta las escrituras.

| Camino | Condición que lo fuerza | Esperado | Escrituras |
|---|---|---|---|
| C1 | Motivo « » | E2 | 0 |
| C2 | Inicio = ahora | E2 | 0 |
| C3 | Sin especialidad vigente | E3 | 0 |
| C4 | `cruce` = «medico» | E4 | 0 |
| C5 | `cruce` = «paciente» | E5 | 0 |
| C6 | Todo válido | id | 1 |

**CB-02 — `cambiar()` y la condición de `cancelar`.** Se cubren las 4 salidas de `cambiar()`: NF, E7 del dominio, E7 de la carrera y éxito. Las dos E7 se distinguen por las escrituras: la del dominio no intenta escribir (0) y la de la carrera sí (1). La condición del ternario de `cancelar` se cubre con el motivo vacío (E2, 0 escrituras) y con el motivo presente (ok, 1).

**Sensibilidad.** Se corrieron dos mutaciones de una línea en una copia temporal. Permitir confirmada → completada la detecta U-01, y cambiar `<=` por `<` en `Horario` la detectan U-02 y CB-01.

## 5. Caja negra (HTTP)

| ID | Técnica | Entradas | Esperado | Obtenido |
|---|---|---|---|---|
| CN-01 | Equivalencia | Médico vigente; médico inactivo; especialidad que no tiene; paciente vacío | 201, 422 E3, 422 E3, 422 E2 | ✘ paciente vacío = 201 |
| CN-02 | Límites del horario | Inicio pasado; fin = inicio; contigua; 1 segundo cruzado con el médico | 422 E2, 422 E2, 201, 409 E4 | ✔ |
| CN-03 | Cruce y liberación | El paciente con otro médico; cancelar; el mismo horario otra vez | 409 E5, 200, 201 | ✔ |
| CN-04 | Permisos | `X-Tenant-ID: hospital-ajeno` agenda; el médico m1 cancela una cita de m2 | 403, 403 | ✘ 201, 200 |
| CN-05 | Errores del cliente | Cita 99; `GET /citas`; confirmar una cancelada; cancelar sin motivo; cuerpo que no es JSON | 404 NF, 404, 422 E7, 422 E2, 422 E2 | ✔ |
| CN-06 | Estado temporal | Confirmar la cita vencida (programada, horario pasado) | 422 | ✘ 200 |
| CN-07 | Error de infraestructura | La base no se puede abrir al arrancar | 503 E8, sin detalles | ✘ 200 con el «Fatal error» |

## 6. Comandos y resultados

```powershell
php docs/modulos/citas-medicas/semana-15/pruebas/pruebas-s15.php          # 10 de 14 pasan; 4 fallan (exit 1)
php docs/modulos/citas-medicas/micro-his-citas/pruebas/pruebas.php        # regresión: todo correcto (exit 0)
```

Con el Micro-HIS de la S6, en una copia temporal, pasan 11 de 15: U-05 pasa y siguen las mismas 4 fallas. Capturas: [01-pruebas-develop.png](capturas/01-pruebas-develop.png) y [02-regresion-s6-mutaciones.png](capturas/02-regresion-s6-mutaciones.png).

## 7. Defectos registrados

| ID | Prueba | Severidad | Descripción | Causa probable | Propuesta | Estado |
|---|---|---|---|---|---|---|
| DEF-01 | CN-01 | Mayor | Se agenda una cita sin paciente (201) | `solicitar` valida el motivo, pero no médico, paciente ni especialidad vacíos | E2 para cualquier dato obligatorio vacío, con su prueba en `pruebas.php` | Abierto; nuevo |
| DEF-02 | CN-04 | Crítica | Otro hospital agenda, y un médico cancela citas ajenas | `atender()` no recibe usuario, permiso ni tenant | Guardia de autorización (E1, 403) antes de cada caso de uso, con el RBAC como dependencia | Abierto; confirma H-01 y CP-04 |
| DEF-03 | CN-06 | Mayor | Se confirma una cita cuyo horario ya pasó (200) | `confirmar` no consulta el `Reloj` | Rechazar la confirmación si `inicio <= ahora` | Abierto; confirma H-03 de la S13 |
| DEF-04 | CN-07 | Mayor | Con la base caída responde 200 y muestra el error de PHP con SQLSTATE y la ruta del servidor | `new PDO` está en `public/index.php`, fuera del adaptador que traduce a E8 | Capturar `PDOException` al conectar y responder `VistaJson::noDisponible()` | Abierto; nuevo |

Los defectos se registran y no se corrigen aquí: corregirlos cambia el Micro-HIS, y eso corresponde a un PR propio con su prueba.

## 8. Bibliografía

Myers, G. J., Sandler, C. y Badgett, T. (2011). *The Art of Software Testing* (3.ª ed.). Wiley · McCabe, T. J. (1976). «A Complexity Measure». *IEEE Transactions on Software Engineering*, SE-2(4) · ISO/IEC/IEEE 29119-4:2021. *Test techniques*.
