# Primera evaluación parcial — Usuarios, tenants y autenticación

Autora: Lopez Perez, Mercedes Azucena · Ocho diapositivas · 6–8 minutos.

## Diapositiva 1 — Problema, actores y alcance

Alta por tenant, autenticación y revocación de sesión. Administrador autorizado invita/asigna rol; persona invitada establece credencial; usuario autentica y revoca su propia sesión. Tenant activo y contexto autorizado son precondiciones. No incluye reglas de pacientes ni módulos clínicos.

Defensa: identidad global no implica pertenencia ni permisos en otro hospital.

## Diapositiva 2 — UML y requerimientos

Semana 1 modela casos de uso/actividad/secuencia. RF-01..04: alta con autorización, identidad/membresía y atomicidad. RF-05..07: tenant, identidad, credencial, membresía vigente y sesión limitada. RF-08..10: revocar antes de confirmar, rechazar expirado/revocado/contexto ajeno y no convertir falla en éxito.

RNF: SRP, secreto protegido, error genérico, integridad, aislamiento, cierre seguro y evidencia honesta. Diagrama trazable conecta decisiones con operaciones y puertos.

## Diapositiva 3 — SOLID aplicado

SRP de semana 2 separa alta, autenticación, revocación y control de acceso: cambian por razones distintas. Autenticar usa verificador; alta no debe manejar el secreto del usuario en nombre del administrador. Dependencias se inyectan por contratos para sustituir persistencia y fallos en prueba.

Decisión: evitar un servicio único que mezcle tenant, hash, SQL, tokens y presentación. El costo es composición explícita; el beneficio es probar causas de fallo por separado.

## Diapositiva 4 — Capas y Repository

Presentation adapta entrada/respuesta; Application coordina permisos y casos de uso; Domain representa usuario/sesión/reglas; Persistence implementa consultas mediante PDO preparado. Los puertos TenantRepository, UserRepository y SessionRepository están en Application del ejemplo de semana 3.

El controlador no debe contener SQL ni reglas de membresía. Repository no elimina la necesidad de transacción, autorización ni filtrado tenant. No afirmar MVC/repositorios de otra rama como integrados: aquí se revisan las interfaces disponibles y su adaptador PDO.

## Diapositiva 5 — Contrato API y consistencia

Semana 5 propone OpenAPI, no una API desplegada: POST /tenants/{tenantId}/user-invitations, POST /auth/invitation-credentials, POST /auth/sessions, GET /auth/sessions/current y DELETE /auth/sessions/current.

202 de invitación no significa mensaje entregado. Aceptación de invitación/credencial exige consumo atómico de un solo uso. Login solo responde sesión después de persistirla. Logout 204 solo tras revocación persistida. La evolución por invitación es diseño de semana 5, no funcionalidad ejecutada por el ejemplo previo de alta directa.

## Diapositiva 6 — Aislamiento, sesiones y riesgos JWT

El cliente no autoriza tenant ni rol: servidor verifica contexto y permiso antes de operar. Acceso cruzado, membresía vencida, token expirado o revocado deben rechazarse; fallos de dependencia no permiten acceso.

Los ejemplos de sesiones no demuestran JWT firmado. Si se elige JWT, un adaptador real debe verificar firma/algoritmo/claims y consultar revocación persistente. No convertir el nombre SessionToken o un bearer de contrato en evidencia criptográfica. Token completo y contraseña no van en logs/capturas.

## Diapositiva 7 — Cambio práctico CH-01

Solicitud simulada: “Si falla la revocación, no confirmar logout; permitir reintento seguro”. Mantener responsabilidad actual: revocar sesión propia, sin ampliar a revocar todas las sesiones ni administrar otros usuarios.

Adaptación: caso de uso devuelve falla de persistencia; presentación no anuncia 204; API usa error temporal según contrato. Reintento de sesión conocida no crea ni reactiva sesión. Retest con fallo y éxito posterior; registrar limitación del ensayo PHP frente a API real.

## Diapositiva 8 — Evidencia y conclusión

Ejecutar las regresiones de semanas 2 y 3 y revisar sus salidas incluidas. Mostrar matriz decisión→evidencia y ficha CH-01. Contrato OpenAPI, actores y diagramas son evidencia de diseño; PHP/PDO es evidencia del ejemplo, no integración HTTP/JWT productiva.

Riesgos pendientes: coherencia alta directa→invitación, API implementada, claims JWT reales y revocación entre procesos. La defensa presenta decisiones verificables y límites, sin declarar sistema hospitalario listo.