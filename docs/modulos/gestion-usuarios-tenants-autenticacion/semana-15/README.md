# Semana 15 — Pruebas de usuarios, tenants y autenticación

**Estudiante:** Lopez Perez, Mercedes Azucena (Azucena17)

**Flujo:** Alta de usuario por tenant, autenticación segura y revocación de sesión.

## Base y alcance

Esta rama parte de b32f57b, semana-7-gestion-usuarios-tenants-componentes-refactorizacion-mercedes-lopez. Se prueba el flujo real de [semana 2](../semana-02/ejemplo-srp/despues/IdentityWorkflow.php) y sus adaptadores en memoria, que contienen alta, membresía, credenciales, emisión de sesión, acceso y revocación. Se conserva la separación RF/RNF de [semana 2](../semana-02/semana-02-srp-usuarios-tenants.md), sin probar módulos clínicos ajenos ni modificar el código previo.

**Límite JWT:** SessionToken es un objeto de contrato; no es un JWT serializado y firmado. Estas pruebas verifican sujeto/tenant/jti/expiración y revocación del modelo de sesión, no validación criptográfica JWT ni middleware Laravel. La consigna de JWT real permanece como brecha de integración registrada (G-JWT), no como PASS simulado. La base debe integrarse antes de este PR.

## Comandos y resultados

PHP 8.2.33 CLI, sin framework ni base externa:
```powershell
php docs/modulos/gestion-usuarios-tenants-autenticacion/semana-15/pruebas/run.php
php docs/modulos/gestion-usuarios-tenants-autenticacion/semana-15/pruebas/run.php --defectos
php docs/modulos/gestion-usuarios-tenants-autenticacion/semana-02/ejemplo-srp/verificar.php
```

- **14 casos funcionales exitosos:** 4 unitarios, 3 caja blanca y 7 caja negra.
- **2 reproducciones de defectos confirmadas**, no correcciones.
- **33 aserciones de regresión exitosas.**

El runner devuelve 1 ante discrepancias inesperadas. En --defectos, PASS confirma que el defecto documentado sigue presente. No mezclar esas reproducciones con la tasa funcional. Reloj fijo e infraestructura nueva por caso; secretos de demostración no se imprimen ni se usan credenciales reales. La prueba de contraseña comprueba la interfaz de verificación, no certifica configuración criptográfica productiva.

## Matriz RF/RNF → prueba

| ID | Tipo | RF/RNF | Entrada / condición y resultado esperado |
|---|---|---|---|
| U01 | Unitaria | RF-05, RNF-07 | Membresía: inicio inclusivo, fin exclusivo, inicio futuro e inactiva rechazados. |
| U02 | Unitaria | RF-05/06, RNF-02 | Verificador con credencial correcta, incorrecta y sujeto desconocido. |
| U03 | Unitaria | RF-07/09 | Exp igual a ahora rechazada; un segundo futuro permitido en contrato. |
| U04 | Unitaria | RF-08/09, RNF-05/06 | Revocar jti en H1 no revoca otro jti ni el mismo jti en H2. |
| W01 | Caja blanca | RF-04 | Campo obligatorio vacío retorna antes de consultar tenant; cero identidades. |
| W02 | Caja blanca | RF-05/06 | Credencial inválida no llama emisor; válida lo llama exactamente una vez. |
| W03 | Caja blanca | RF-04/10, RNF-04 | Falla inyectada del alta no deja identidad ni membresía. Es falla previa al callback; atomicidad completa requiere además revisar rollback del adaptador. |
| B01 | Caja negra | RF-01/02/04 | Alta permitida crea identidad/membresía; duplicado no aumenta conteos. |
| B02 | Caja negra | RF-03/05 | Administrador desconocido, rol no asignable, tenant ajeno o inactivo bloqueados. |
| B03 | Caja negra | RF-05/07, RNF-05 | Login H1 emite sujeto/tenant y expiración +15 min; H2 sin membresía no autentica. |
| B04 | Caja negra | RF-06, RNF-03 | Secreto incorrecto, sujeto inexistente e identidad inactiva comparten error genérico sin token. |
| B05 | Caja negra | RF-08/09 | Acceso cruzado rechazado; logout exitoso convierte acceso posterior en REVOKED. |
| B06 | Caja negra | RF-08/10, RNF-06 | Falla del almacén no confirma cierre; sesión sigue permitida hasta revocación efectiva. |
| B07 | Caja negra | RF-05 | Membresía inactiva o expirada no permite login. |
| D01 | Defecto / límite de blancos | RF-09 | CheckSessionAccess acepta sujeto/jti con espacios, mientras logout los rechaza. |
| D02 | Defecto de contrato de emisor | RF-07, RNF-05 | Emisor devuelve H2 para login H1 y autenticación declara éxito. |
| G-JWT | Pendiente integración | RF-07/09, RNF-08 | JWT firmado, manipulado/expirado y revocación persistente no verificables en este prototipo de objetos. |

Caja negra usa entradas y resultados públicos; caja blanca usa espías de dependencias/contadores para comprobar retornos y ramas. No se presenta porcentaje de cobertura de código sin instrumentación. Límites elegidos: blancos, inicio/fin de membresía y exp exacto; no se inventa longitud mínima de contraseña no definida en el contrato.

## Defectos registrados

| ID | Severidad propuesta / estado | Reproducción y observado | Esperado / recomendación |
|---|---|---|---|
| AU-D01 | Media / abierto | --defectos D01: SessionToken con subject=' ' y jti=' ' devuelve ALLOWED; RevokeCurrentSession devuelve INVALID_TOKEN. | Validación de campos consistente con trim antes de consultar acceso; retest de null/vacío/blancos y sesión correcta. No implica un JWT malformado aceptado por un servidor real. |
| AU-D02 | Alta en integración / abierto | --defectos D02: SessionTokenIssuer defectuoso retorna tenant H2 para autenticación H1; resultado AUTHENTICATED. | Establecer/validar postcondición de emisor para sujeto/tenant/jti/expiración. El control posterior bloquea tenant distinto si se usa correctamente; no se demuestra acceso cruzado consumado. |
| G-JWT | Brecha alta / pendiente | No hay decodificación/verificación de firma JWT en este ejemplo; solo SessionToken. | Seleccionar adaptador real existente del proyecto y prueba aislada de firma/claims/revocación, sin inventar un verificador para aparentar integración. No aprobar cobertura JWT mientras falte. |

Propietaria propuesta de corrección: autora del módulo. Cierre exige cambio, retest y revisión independiente. Esta entrega registra hallazgos y no modifica silenciosamente la línea base de otras semanas.

## Plan de integración JWT pendiente

| Caso previsto | Esperado | Evidencia necesaria |
|---|---|---|
| JWT válido del emisor elegido con tenant autorizado | Acceso solo al tenant de claims verificados | Respuesta del límite autenticado y claims verificados sin publicar token. |
| Payload/firma manipulados o algoritmo no admitido | Rechazo antes de acceder a datos | Prueba del adaptador real, sin usar objetos SessionToken construidos a mano como sustituto. |
| JWT con exp igual/pasado y contexto ajeno | Rechazo | Reloj controlado y respuesta de autorización. |
| Logout con almacén persistente y segundo proceso | Rechazo del mismo jti después de cierre | Evidencia independiente del proceso emisor; el mapa en memoria no lo demuestra. |
| Almacén de revocación caído | No confirmar cierre ni convertir fallo en acceso autorizado | Error explícito y política de fallo del límite real. |

Este plan no cuenta como pruebas ejecutadas. No se afirma que RNF-08 permita omitir JWT y a la vez declarar completa la consigna: queda explícita la cobertura parcial del objetivo de integración.

## Capturas y salidas

- [Suite](evidencia/salida-suite.txt) · [Captura suite](evidencia/captura-suite.png).
- [Defectos](evidencia/salida-defectos.txt) · [Captura defectos](evidencia/captura-defectos.png).
- [Regresión](evidencia/salida-regresion.txt).

Las capturas renderizan en navegador las salidas PHP guardadas en reporte.html; no son una UI productiva ni una terminal interactiva. No hay datos reales ni tokens emitidos por producción.

## Defensa

Mostrar matriz y ejecutar runner. Explicar que una membresía en H1 no autentica en H2; exp exacto rechaza; logout necesita confirmación del almacén. Mostrar los defectos sin tratarlos como corregidos y distinguir el modelo de sesión de un JWT criptográficamente verificado. Conclusión: reglas del contrato probadas; JWT real e integración persistente pendientes.