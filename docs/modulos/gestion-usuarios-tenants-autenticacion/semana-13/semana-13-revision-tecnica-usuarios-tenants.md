# Semana 13 — Revisión técnica formal: usuarios, tenants y autenticación

**Autora:** Lopez Perez, Mercedes Azucena (Azucena17)

**Flujo:** Alta de usuario por tenant, autenticación segura y revocación de sesión.

**Versión:** 1.0 · 28 de octubre de 2026 · Horarios de Guatemala.

## Plan y línea base

Objetivo: preparar revisión de dominio, contrato API, permisos/tenant, credenciales, sesiones, JWT, revocación, aislamiento y dependencias estrictamente necesarias. No se evalúan módulos clínicos ni se aprueba un despliegue productivo.

Línea base: b32f57b, rama de semana 7. Artefactos:

- [RF-01..RF-10 y RNF-01..RNF-08 de semana 2](../semana-02/semana-02-srp-usuarios-tenants.md).
- [Casos de uso y puertos](../semana-02/ejemplo-srp/despues/IdentityWorkflow.php).
- [Adaptadores en memoria](../semana-02/ejemplo-srp/despues/InMemoryAdapters.php) y [regresión](../semana-02/ejemplo-srp/verificar.php).
- [Componentes/refactor de semana 7](../semana-07/semana-07-componentes-refactorizacion.md) y prototipo AuthenticateTenantUser de esa entrega.

El contrato de API de otras ramas debe incorporarse con ruta y commit exactos antes de dictaminar su implementación. No se interpreta un objeto SessionToken como JWT firmado ni la autorización en memoria como aislamiento de servidor.

Antecedente fuera de esta base: semana 15, commit 507beee, contiene 14 casos, 33 aserciones de regresión, AU-D01/AU-D02 y brecha G-JWT. Se registran como hallazgos conocidos; no se copian sus archivos ni se presume que esa rama ya esté integrada.

## Roles y estado

| Rol | Asignación | Función |
|---|---|---|
| Autora | Mercedes Azucena Lopez Perez | Preparar evidencias, explicar alcance, corregir y aportar retest; no aprobar su propio cierre. |
| Moderador | Por asignar y aceptar | Comprobar entrada, controlar tiempos/alcance, ratificar severidad y emitir decisión. |
| Revisor de dominio/persistencia | Por asignar y aceptar | Revisar membresías, alta atómica, reloj, contratos y revocación. |
| Revisor de seguridad/API | Por asignar y aceptar | Revisar identidad, permisos, tenant, JWT y errores; puede coincidir con el anterior si se registra. |
| Secretario | Moderador u otro participante designado | Conservar hallazgos, responsables, fechas y acuerdos reales. |

Estado: **revisión preparada; sesión formal pendiente**. No se enviaron convocatorias ni se inventan participantes o aprobaciones. La inspección asistida no sustituye revisión independiente.

## Criterios de entrada

1. Commit congelado y lista de artefactos accesibles, con contrato API seleccionado o su ausencia explícita.
2. RF/RNF, alcance y criterios conocidos; separar controles del contrato de los controles reales de integración.
3. Comandos reproducibles, versión PHP, datos ficticios y salidas sin secretos; defectos previos disponibles.
4. Moderador/revisores independientes asignados y roles/agenda aceptados antes de celebrar sesión.
5. Identificar adaptador JWT/almacén real y configuración de prueba o declarar que faltan; la falta bloquea aprobación de integración, no se oculta con NA.

## Método y calendario propuesto

| Fase / fecha Guatemala | Actividad | Salida / responsable |
|---|---|---|
| 28 oct | Inventario, base y regresión | Autora: paquete preparado. |
| 29 oct, antes de 17:00 | Lectura individual y observaciones por checklist | Revisores asignados; pendiente si no aceptan rol. |
| 29 oct, 18:00–18:45 | 5 min alcance, 15 min alta/login, 15 min JWT/tenant/revocación, 10 min hallazgos y decisión | Moderador/secretario: acta. |
| 30 oct, antes de 18:00 | Corrección de bloqueantes y retest | Autora: commits y evidencia. |
| 30 oct, antes de 22:00 | Revisión de cierre o nueva revisión | Revisores/moderador; entrega académica antes de 23:59. |

La agenda es propuesta, no reunión confirmada. Cada revisor registra observación con evidencia antes de debatir solución. La sesión identifica defectos, riesgos y preguntas; no amplía el módulo ni reemplaza correcciones por discusión.

## Checklist de 15 ítems

Estados al revisar: C cumple, NC no cumple, P pendiente, NA solo con justificación aceptada. La columna inicial no es un dictamen independiente.

| ID | Criterio verificable | Evidencia / prueba esperada | Preparación |
|---|---|---|---|
| CK01 | Alta exige campos obligatorios y tenant activo | RegisterTenantUser, INVALID_INPUT/TENANT_UNAVAILABLE | Listo para inspección. |
| CK02 | Administración y asignación de rol se autorizan para ese tenant | UserManagementAuthorization, permisos negativos | Listo en contrato; servidor pendiente. |
| CK03 | Identidad global y membresía se separan, sin acceso implícito a otro hospital | Repositorios, login H1/H2 | Listo en contrato. |
| CK04 | Alta es atómica, sin duplicados ni parciales ante fallo intermedio | Transaction, createIdentity/createMembership, conteos/rollback | Revisar doble y adaptador persistente cuando exista. |
| CK05 | Credenciales no se reciben en alta ni se guardan/loguean en claro | Puertos, hash de prueba, logs y respuestas | Listo en demo; integración por revisar. |
| CK06 | Login verifica tenant/identidad activos y membresía vigente con límites correctos | AuthenticateTenantUser y TenantMembership::isValidAt | Listo para inspección. |
| CK07 | Error de login es genérico y no emite sesión ante fallo | AuthenticationOutcome y casos inexistente/incorrecto/inactivo | Listo en contrato; no prueba igualdad temporal de respuesta. |
| CK08 | Contrato API define endpoints/payloads/errores/identidad y no promete rutas inexistentes | Documento y commit seleccionados; comparación con implementación | P: incorporar contrato de integración. |
| CK09 | Sujeto, tenant, jti y exp devueltos por emisor cumplen la solicitud | SessionTokenIssuer y resultado de autenticación | H-02 abierto: emisor incoherente aceptado. |
| CK10 | JWT real verifica firma, algoritmo admitido, claims y expiración antes del acceso | Adaptador real y casos de token manipulado/expirado | P: G-JWT; objeto SessionToken no cubre esto. |
| CK11 | Sesión inválida/blancos, expirada o de tenant ajeno se rechaza consistentemente | CheckSessionAccess y RevokeCurrentSession | H-01: blancos inconsistentes. |
| CK12 | Logout confirma solo después de persistir y revocación se respeta entre procesos | RevocationStore, fallo/éxito, segundo proceso | Contrato disponible; persistencia real pendiente. |
| CK13 | Cambio de membresía/tenant y fallo del almacén no generan acceso inseguro | Política de reevaluación y pruebas de integración | P: acordar política y verificar límite autenticado. |
| CK14 | Dependencias separan UI/aplicación/dominio/adaptadores y solo incluyen lo necesario | SRP/puertos, refactor semana 7 y composición | Listo para inspección; SDK fuera del dominio. |
| CK15 | RF/RNF y defectos tienen evidencia reproducible sin secretos y sin afirmar cobertura inexistente | Matriz/salidas/registro; revisar capturas | Regresión disponible; ramas adicionales requieren selección expresa. |

## Hallazgos preliminares

No son acuerdos de una sesión ni correcciones realizadas. Severidad propuesta para ratificación.

| ID / antecedente | Observación y esperado | Impacto / severidad | Acción / propietario / estado |
|---|---|---|---|
| H-01 / AU-D01 | CheckSessionAccess compara campos con cadena vacía, sin trim; sujeto/jti de espacios pasan. Logout sí rechaza blancos. | Media: validación inconsistente del contrato; no prueba JWT real aceptado. | Autora: unificar validación de campos y retest de null/vacío/blancos/válido; abierto. |
| H-02 / AU-D02 | Login acepta cualquier SessionToken no nulo del emisor; puede informar éxito con tenant distinto al solicitado. | Alta para integración; el control posterior podría bloquearlo, no se afirma acceso cruzado consumado. | Autora: postcondición de sujeto/tenant/jti/exp y prueba de emisor defectuoso; abierto. |
| H-03 / G-JWT | Solo existe modelo de sesión para este ejemplo; no hay evidencia de firma JWT ni revocación persistente entre procesos. | Alta como brecha de integración. | Autora: seleccionar adaptador real y ejecutar negativos de firma/claims/reuso; pendiente, no aprobado. |
| H-04 / CK08/13 | Contrato API y política de reevaluación tras cambios de membresía no forman parte de esta línea base. | Riesgo de integración: autorización obsoleta o contrato incoherente. | Autora: incorporar documento/commit y casos acordados; pendiente de diseño/revisión. |

## Criterios de salida

Para cerrar la **sesión**: participantes y roles registrados, base exacta, 15 ítems con evidencia/estado, hallazgos ratificados y acta confirmada por moderador/revisores. Cierre de sesión no equivale a software aprobado.

Para aprobar **contrato de demostración**: reglas declaradas comprobadas, críticos/altos aplicables resueltos y retest independiente, regresión exitosa y límites explícitos. Observaciones menores diferidas requieren propietario, fecha y aceptación registrada.

Para recomendar **integración**: además, API/identidad/tenant verificadas, postcondiciones de sesión correctas, JWT firmado real comprobado y revocación efectiva entre procesos, cero críticos/altos abiertos y revisión R2 registrada. No se elimina un bloqueante llamándolo NA.

Decisiones: aprobado para alcance declarado; aprobado con observaciones menores; requiere correcciones y retest; requiere nueva revisión. Si no hubo sesión: PREPARADA/PENDIENTE. La base actual no permite recomendar autenticación productiva.

## Evidencia y formatos

Preparación ejecutada con PHP 8.2.33:
```powershell
php docs/modulos/gestion-usuarios-tenants-autenticacion/semana-02/ejemplo-srp/verificar.php
```
Resultado: 33 aserciones exitosas. [Salida](evidencia/regresion-semana-02.txt). No cubre JWT real ni cierra los hallazgos.

Usar [formato-hallazgos.md](formato-hallazgos.md) y [acta-revision.md](acta-revision.md). Son plantillas sin participantes ni aprobación inventados.