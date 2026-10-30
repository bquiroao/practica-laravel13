# Semana 15 — Pruebas unitarias, caja blanca y caja negra

**Tarea:** diseñar y ejecutar pruebas trazadas a RF/RNF: unitarias para las reglas, caja blanca con ramas y condiciones del flujo y caja negra con equivalencia, límites, permisos y errores, sin probar módulos ajenos.
**Entregable:** matriz RF/RNF → prueba, **4 unitarias, 5 de caja blanca y 5 de caja negra**, comandos y resultados, capturas y defectos registrados.

El código de las pruebas está en `micro-his/pruebas/formales/` (PR 15A). Corren con el mismo ejecutor junto con las 90 pruebas anteriores. Todas usan reloj fijo, notificador simulado y datos ficticios, y ninguna llama a otros módulos del HIS.

## 1. Pruebas formales

| ID | Tipo | Técnica | Qué verifica | Trazado a |
|---|---|---|---|---|
| U-01 | Unitaria | Valores límite | Plazo 5 y 60 aceptados; 4 y 61 rechazados | RF-10, RN-11 |
| U-02 | Unitaria | Doble de canal | Exactamente 3 intentos; una excepción cuenta como fallo | RF-05, RN-08, RNF-05 |
| U-03 | Unitaria | Estado | Acuse `true` una vez y `false` después; el estado no cambia | RF-06, RN-06 |
| U-04 | Unitaria | Contenido | El resumen lleva el código de paciente, sin nombres, y tiene longitud acotada | RNF-02 |
| CB-01 | Caja blanca | Cobertura de ramas (a)(a)(b) de `NotificarNivel` | Sin destinatarios en ningún nivel → sube hasta 2 y agota | RF-03, RN-03, RN-05 |
| CB-02 | Caja blanca | Ramas (d)(d)(e) | Fallan los niveles 0 y 1; en el 2 uno recibe → termina sin agotar | RF-05, RN-08 |
| CB-03 | Caja blanca | Rama (f) | En el nivel 2 nadie recibe → agota | RN-05, RN-08 |
| CB-04 | Caja blanca | Rama (c) | Todos reciben en el nivel 0 → ENVIADA, sin subir | RF-04 |
| CB-05 | Caja blanca | 4 ramas de `Alerta::acusar` | Ya acusada, no enviada, no notificado y primer acuse | RF-06, RN-06, RN-07 |
| CN-01 | Caja negra | Partición de equivalencia | Prioridad CRITICA y «alta» → 201; BAJA y vacía → 422 | RF-01, RN-01 |
| CN-02 | Caja negra | Valores límite | `PAC` con 5 dígitos sí, con 4 y 6 no; descripción de 200 sí, de 201 no | RF-01, RN-01 |
| CN-03 | Caja negra | Valores límite de tiempo | ALTA: a los 29 min no escala, a los 30 sí | RF-07, RN-04 |
| CN-04 | Caja negra | Tabla de decisión rol × acción | Laboratorio registra pero no consulta; enfermería y médico consultan pero no registran; el planificador no hace ninguna de las dos | RNF-01, RF-08 |
| CN-05 | Caja negra | Errores del contrato | 401, 400, 404, 405 y 422, todos con `codigo` y `request_id` | Contrato API (semana 5) |

**Cobertura de caja blanca:** las 6 ramas (a)–(f) de `NotificarNivel::ejecutar` quedan cubiertas por CB-01 a CB-04, y las 4 de `Alerta::acusar` por CB-05. Es una cobertura medida a mano sobre el código, no con una herramienta: no hay Xdebug ni PCOV en el entorno.

## 2. Matriz RF/RNF → prueba

| Requisito | Pruebas formales (S15) | Pruebas de semanas anteriores |
|---|---|---|
| RF-01 Registrar | CN-01, CN-02 | CA-01, CA-02, «CAMINO FELIZ» (dobles y PDO) |
| RF-02 No duplicar | — | CA-03; contrato 4; API «201 y luego 200 duplicada» |
| RF-03 Destinatarios | CB-01 | CA-04 |
| RF-04 Enviar | CB-04 | CA-05 |
| RF-05 Reintentos | U-02, CB-02 | CA-06 |
| RF-06 Acusar | U-03, CB-05 | CA-07, CA-08, CA-09; PDO «un solo evento acusada» |
| RF-07 Escalar | CN-03 | CA-10 a CA-13 |
| RF-08 Cerrar | CN-04 (por rol) | CA-14; API «RF-08» · **D-03 abierto en la web** |
| RF-09 Historial | — | CA-15; PDO «la base rechaza modificar o borrar el historial» |
| RF-10 Configurar plazos | U-01 | CA-16 |
| RNF-01 Tenant | CN-04 | Contrato 2; API «el tenant sale del token»; web 404 |
| RNF-02 Privacidad | U-04 | «RNF-02 el resumen…» |
| RNF-03 Trazabilidad | CB-01 (secuencia de eventos) | CA-15 |
| RNF-04 Testabilidad | Todas con reloj fijo | Suite completa en < 1 s |
| RNF-05 Confiabilidad | U-02 | «RNF-05 si el canal lanza excepción…», «ERROR DE PERSISTENCIA» |
| RNF-06 Mantenibilidad | — | `verificar-srp.php`; contrato PDO/InMemory |
| RNF-07 Persistencia segura | — | Ítem 12 de la RTF (semana 13); PDO «id no válido devuelve null» |
| RNF-08 Desempeño (< 500 ms) | — | **No medido:** la suite completa con PostgreSQL tarda menos de 0.4 s (107 pruebas), pero no hay una prueba aislada del registro. Queda pendiente. |

## 3. Comandos y resultados

```powershell
cd docs/modulos/alertas-criticas-notificaciones-internas/micro-his
docker compose ps                     # healthy
php scripts/crear-esquema.php --prueba
php pruebas/ejecutar.php --evidencia=../semana-15/evidencia/salida-pruebas.txt
php pruebas/ejecutar.php --filtro=CB-   # solo caja blanca
php pruebas/ejecutar.php --filtro=CN-   # solo caja negra
```

**Resultado en el entorno de preparación** (PHP 8.3.6, PostgreSQL 16.15): `Total: 107 · Pasaron: 107 · Fallaron: 0`.
La captura `capturas/salida-pruebas-preparacion.png` muestra esa salida renderizada. La evidencia oficial es `evidencia/salida-pruebas.txt`, que genera Erick con PHP 8.4.

## 4. Registro de defectos

| ID | Defecto | Severidad | Detectado en | Estado | Prueba |
|---|---|---|---|---|---|
| D-01 | `"«$campo»"`: PHP tomaba `»` como parte del nombre de la variable; los mensajes salían sin el dato | Menor | S3, al probar | **Corregido** | Regresión: CA-02 exige «codigo_paciente» en el mensaje |
| D-02 | `Alerta` dependía de una constante de `PoliticaEscalamiento` (dependencia invertida) | Menor | S3, al partir PRs | **Corregido** | CA-13 en `alerta-pruebas.php` |
| D-03 | La web MVC deja cerrar a enfermería (RF-08); la API sí lo impide | Mayor | S9 (H-05), RTF-01 | **Abierto** | `[DEFECTO D-03]` |
| D-04 | Con la base caída al arrancar, `/api/*` responde texto plano en lugar del JSON del contrato | Menor | RTF-02 | **Abierto** | `[DEFECTO D-04]` |
| D-05 | La bitácora del notificador simulado usa la hora real, no el `Reloj` inyectado, y no coincide con el historial | Menor | S15, al revisar la bitácora | **Abierto** | `[DEFECTO D-05]` |

Las pruebas `[DEFECTO D-NN]` afirman el comportamiento **actual** para dejarlo documentado sin romper la suite. Al corregir el defecto, la prueba falla y se invierte. Por la métrica M6 del plan SQA, D-03 (mayor) debe corregirse o justificarse antes del cierre.

**Corrección propuesta para D-03:** en `AlertaController::cerrar`, consultar `Permisos::permite($sesion->rol(), 'cerrar')` y responder 403; en `detalle.php`, mostrar el formulario solo si el rol lo permite.
