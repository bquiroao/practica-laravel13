# Semana 5 — Cliente-servidor, API REST, microservicios e integración

**Tarea:** evolucionar el MISMO Micro-HIS hacia cliente-servidor y evaluar una frontera de microservicio:
contrato API, propiedad de datos, comunicación, seguridad, resiliencia, observabilidad y consistencia, con
diagramas y migración razonada, sin infraestructura de producción.
**Entregable:** contrato API y plan de issue, rama, worktree y PR.

| Parte | Contenido |
|---|---|
| A | Código: `ApiAlertas`, `AutenticadorFicticio`, `Permisos`, `RespuestaJson`, `Identidad` y 10 pruebas de API |
| B | Este README (contrato API, seguridad, observabilidad), vista cliente-servidor, secuencia y evidencia |
| C | `frontera-microservicio.md` (propiedad de datos, comunicación, resiliencia, consistencia, cola/eventos, migración) y `plan-issue-rama-worktree-pr.md` |

## 1. Vista cliente-servidor

`diagramas/cliente-servidor.png`: los clientes (navegador, sistema origen, planificador y una app clínica futura) hablan con un único servidor PHP. `public/index.php` envía `/api/*` a la API JSON y el resto al MVC; los dos usan los **mismos casos de uso**.

## 2. Contrato API v1

Base: `/api/v1`. Autenticación: `Authorization: Bearer <token>`. **El tenant y el origen salen del token**; si el cuerpo trae `tenant`, se ignora.

| Método | Ruta | Permiso/Rol | Descripción | Éxito |
|---|---|---|---|---|
| POST | `/alertas` | `sistema_origen` | Registra la alerta (CU-01); idempotente por `id_origen` | 201 + `Location` · 200 si duplicada |
| GET | `/alertas` | enfermería, médico, jefe de servicio, coordinación | Lista las alertas del tenant | 200 |
| GET | `/alertas/{id}` | ídem | Detalle con historial (CU-07) | 200 |
| POST | `/alertas/{id}/acuse` | ídem (y destinatario notificado, RN-07) | Acusa (CU-04), idempotente | 200 `{cambio}` |
| POST | `/alertas/{id}/cierre` | médico, jefe de servicio (RF-08) | Cierra con `{"nota"}` (CU-06) | 200 |
| POST | `/escalamientos` | `planificador` | Revisa plazos vencidos (CU-05) | 200 `{escaladas, agotadas}` |

**Petición de registro:**
```json
{"codigo_paciente": "PAC-00451", "servicio": "MED-INT", "id_origen": "LAB-77801",
 "prioridad": "CRITICA", "descripcion": "Potasio sérico 6.8 mmol/L (ref. 3.5-5.1)"}
```
**Respuesta 201:**
```json
{"datos": {"alerta": {"id": "<uuid>", "codigo_paciente": "PAC-00451", "servicio": "MED-INT", "prioridad": "CRITICA",
  "descripcion": "…", "estado": "ENVIADA", "nivel": 0, "escalamiento_agotado": false, "plazo_desde": "2026-10-29T10:00:00-06:00"},
  "duplicada": false}}
```
**Formato único de error** (todas las rutas):
```json
{"error": {"codigo": "regla_incumplida", "mensaje": "[RN-01] Falta el dato obligatorio «servicio»", "regla": "RN-01", "request_id": "demo-00000001"}}
```

| HTTP | `codigo` | Cuándo |
|---|---|---|
| 400 | `cuerpo_invalido` | El cuerpo no es un objeto JSON |
| 401 | `no_autenticado` | Sin token o token desconocido (con `WWW-Authenticate: Bearer`) |
| 403 | `sin_permiso` | El rol no tiene la acción |
| 403 | `regla_incumplida` (RN-07) | El usuario no fue notificado de esa alerta |
| 404 | `no_encontrada` | No existe o es de otro tenant (RNF-01) |
| 404 / 405 | `ruta_inexistente` / `metodo_no_permitido` | Ruta o método fuera del contrato |
| 422 | `regla_incumplida` | RN-01, RN-09 y otras reglas de dominio |
| 503 | `persistencia_no_disponible` | La base no responde; el cliente puede reintentar |

**Tokens ficticios de curso** (no son credenciales reales; en el HIS los emitirían autenticación y `rbac`):
`tok-lab-gua` (sistema_origen), `tok-enf-gua`, `tok-med-gua`, `tok-jefe-gua`, `tok-coord-gua` (HOSP-GUA-01), `tok-enf-xel` (HOSP-XEL-02) y `tok-plan` (planificador).

## 3. Seguridad

- Autenticación por token y autorización por la matriz `Permisos` (**propuesta**, por confirmar con `rbac`).
- Tenant tomado del token: un cliente no puede pedir datos de otro hospital poniendo otro tenant.
- RF-08 aplicado: enfermería recibe 403 al cerrar. Esto resuelve el pendiente de la semana 4.
- Respuestas con código de paciente, nunca nombre (RNF-02). El historial no expone datos clínicos extra.
- La API no usa cookies, por eso no le aplica el riesgo CSRF de los formularios MVC (sigue registrado para la semana 9).

## 4. Observabilidad

- `X-Request-Id`: se respeta el entrante si es válido (8–64 caracteres `[A-Za-z0-9-]`); si no, se genera uno. Vuelve en la cabecera y en todo error, para correlacionar cliente, servidor y bitácora.
- El historial de la alerta (RN-10) es la traza de negocio: quién, qué y cuándo.
- Métricas propuestas para la semana 14: tiempo hasta el acuse, alertas con escalamiento agotado y entregas fallidas por canal.

## 5. Evidencia

`diagramas/secuencia-api-registro.png` muestra la autenticación, el permiso y el tenant del token. En el entorno de preparación, con `php -S` y PostgreSQL 16, `curl` con `tok-lab-gua` devolvió `201 Created`, `Location` y el mismo `X-Request-Id`; sin token devolvió `401` en JSON.

Pruebas: 90 en total y 90 pasaron en el entorno de preparación. La evidencia oficial la genera Erick:
`php pruebas/ejecutar.php --evidencia=../semana-05/evidencia/salida-pruebas.txt`

Los .png se generaron desde sus .puml con PlantUML; abren correctamente en el repositorio.
