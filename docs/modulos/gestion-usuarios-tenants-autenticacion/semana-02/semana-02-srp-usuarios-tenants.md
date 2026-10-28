# Semana 2 — Requerimientos, criterios de aceptación y aplicación de SRP

| | |
|---|---|
| **Curso** | Análisis de Sistemas II — Proyecto Final 2026 |
| **Proyecto** | Sistema Hospitalario Integrado (HIS) |
| **Módulo** | Gestión de usuarios, tenants y endurecimiento de autenticación |
| **Tema** | Proceso y modelo de diseño; principios SOLID |
| **Flujo** | «Alta de usuario por tenant, autenticación segura y revocación de sesión» |
| **Principio aplicado** | SRP — Principio de responsabilidad única |
| **Estudiante según la captura** | Lopez Perez, Mercedes Azucena |
| **Fecha** | Octubre 2026 |

## Índice

1. [Objetivo y alcance](#1-objetivo-y-alcance)
2. [Requerimientos funcionales](#2-requerimientos-funcionales)
3. [Requerimientos no funcionales](#3-requerimientos-no-funcionales)
4. [Criterios de aceptación](#4-criterios-de-aceptación)
5. [SRP aplicado al flujo](#5-srp-aplicado-al-flujo)
6. [Diseño antes de SRP](#6-diseño-antes-de-srp)
7. [Diseño después de SRP](#7-diseño-después-de-srp)
8. [Responsabilidades y dependencias](#8-responsabilidades-y-dependencias)
9. [Evidencia verificable](#9-evidencia-verificable)
10. [Trazabilidad con Semana 1](#10-trazabilidad-con-semana-1)
11. [Conclusión y límites](#11-conclusión-y-límites)
12. [Referencias](#12-referencias)

## 1. Objetivo y alcance

En Semana 1 se modelaron tres operaciones relacionadas: el alta autorizada de una identidad y su membresía por hospital, la autenticación contextualizada a un tenant y la revocación de la sesión actual. Aquí se mantiene el mismo flujo y se aplica el principio de responsabilidad única.

SRP se interpreta como **una razón principal para cambiar por clase**, no como una clase por cada línea. La solicitud de alta, la verificación de credenciales, la emisión de sesión y la revocación tienen políticas y fallas distintas, por lo que se modelan como casos de uso separados. Cada uno colabora con contratos para las tareas externas correspondientes.

El prototipo usa PHP 8.2+ sin framework, Composer, base de datos ni secretos reales. La alta crea una identidad global y una membresía dentro de un tenant ficticio en una operación transaccional de prueba. La autenticación verifica un hash de demostración a través de un proveedor abstracto y exige una membresía vigente antes de emitir un objeto de sesión limitado al tenant. La revocación registra el `jti` hasta la expiración y el control de acceso lo consulta.

**Límites:** no se implementan endpoints Laravel, RBAC productivo, correo de invitación, recuperación de contraseña, MFA, algoritmo/firma JWT real ni almacén persistente de blacklist. En el repositorio las credenciales siguen siendo una decisión pendiente; la demostración abstrae el verificador y no define dónde vivirían los secretos en producción. El objeto `SessionToken` representa claims ya verificados en el límite de la aplicación; no es un JWT firmado.

## 2. Requerimientos funcionales

| ID | Requerimiento | Origen |
|---|---|---|
| RF-01 | El sistema debe permitir que un administrador autorizado dé de alta una identidad y una membresía para un tenant activo. | R-01, R-02, R-03, R-11 |
| RF-02 | La identidad global y la membresía del hospital deben conservarse como conceptos separados; el alta no debe otorgar acceso implícito a otros tenants. | R-02, R-03 |
| RF-03 | El alta debe exigir autorización para administrar usuarios y para asignar el rol inicial en ese tenant. | R-11, D-02, E-02 |
| RF-04 | El alta debe impedir campos obligatorios vacíos y una membresía activa duplicada, y crear identidad/membresía atómicamente. | R-03, D-03, D-04, E-03, E-04 |
| RF-05 | La autenticación debe comprobar tenant activo, identidad activa, credenciales y membresía vigente de ese tenant antes de emitir la sesión. | R-01, R-02, R-05, R-06 |
| RF-06 | El error de credenciales inválidas, identidad inexistente/inactiva o membresía ausente debe ser genérico y no emitir sesión. | R-05, E-05 |
| RF-07 | La sesión emitida debe estar contextualizada por sujeto, tenant y `jti`, y tener expiración limitada. | R-06 |
| RF-08 | El cierre debe persistir la revocación del `jti` antes de confirmar que la sesión terminó. | R-07, E-08 |
| RF-09 | La verificación de acceso debe rechazar sesión expirada, revocada o presentada para un tenant distinto. | R-08, E-07 |
| RF-10 | Ningún flujo debe convertir una dependencia no disponible en un alta, autenticación o revocación exitosa. | R-09, E-08 |

## 3. Requerimientos no funcionales

| ID | Atributo | Requerimiento | Verificación |
|---|---|---|---|
| RNF-01 | Mantenibilidad (SRP) | Los casos de uso de alta, autenticación, revocación y control de sesión deben tener responsabilidades separadas. | CA-15 a CA-18 |
| RNF-02 | Seguridad de credenciales | El flujo de alta no recibe contraseñas; el autenticador entrega el secreto a un verificador y el ejemplo no lo almacena ni registra en texto claro. | CA-08, inspección de código |
| RNF-03 | Confidencialidad | Los errores de login no revelan si una identidad existe. | CA-09 |
| RNF-04 | Integridad | Identidad y membresía se crean juntas o ninguna permanece. | CA-07 |
| RNF-05 | Aislamiento | Una membresía en un tenant no habilita sesión para otro tenant. | CA-10, CA-13 |
| RNF-06 | Cierre seguro | La sesión solo se confirma revocada si el almacén de revocación lo confirma. | CA-12, CA-14 |
| RNF-07 | Testabilidad | Las políticas se prueban con dobles en memoria y el reloj se controla. | `verificar.php` |
| RNF-08 | Alcance honesto | La evidencia del prototipo no se presentará como verificación de JWT, integración Laravel ni blacklist productiva. | Sección 11 |

## 4. Criterios de aceptación

Formato *Dado / Cuando / Entonces*. Cada criterio tiene aserciones en [`ejemplo-srp/verificar.php`](ejemplo-srp/verificar.php).

| ID | RF/RNF | Dado | Cuando | Entonces |
|---|---|---|---|---|
| CA-01 | RF-01 a RF-04 | administrador autorizado, tenant activo y alta válida | solicita alta | se crean una identidad global y una membresía en el tenant |
| CA-02 | RF-04 | la membresía de la persona en el tenant ya está activa | se repite el alta | se informa duplicidad y no se crea otra membresía |
| CA-03 | RF-03 | el administrador no tiene permiso para altas | solicita alta | se deniega y no se crea identidad |
| CA-04 | RF-03 | el administrador no puede asignar el rol inicial solicitado | solicita el alta | se deniega sin crear una membresía |
| CA-05 | RF-01 | el tenant está inactivo | se solicita un alta | se rechaza el alta sin modificaciones |
| CA-06 | RF-04 | falta sujeto, nombre o rol | se intenta registrar | se informa entrada inválida antes de persistir |
| CA-07 | RF-04, RF-10 | falla la operación transaccional | se solicita alta | se informa falla y no queda identidad ni membresía parcial |
| CA-08 | RF-05, RF-07 | identidad activa, credenciales válidas y membresía vigente en el tenant | inicia sesión | se emite sesión para sujeto y tenant seleccionados, con expiración limitada |
| CA-09 | RF-06 | se intenta login con secreto incorrecto o sujeto inexistente | se autentica | ambos producen el mismo estado genérico y no emiten token |
| CA-10 | RF-05, RF-06 | el usuario no tiene membresía vigente en el tenant seleccionado | inicia sesión | falla con respuesta genérica y no emite sesión |
| CA-11 | RF-09 | sesión vigente y no revocada | se verifica en su tenant | el control permite continuar |
| CA-12 | RF-08, RF-10 | almacén de revocación disponible | se cierra la sesión actual | se confirma revocación y posteriores comprobaciones rechazan el `jti` |
| CA-13 | RF-09 | la sesión pertenece al tenant A | se presenta para tenant B | se rechaza por contexto |
| CA-14 | RF-08, RF-10 | el almacén falla al registrar la revocación | se cierra sesión | no se confirma cierre; la sesión sigue marcada como no revocada en el doble |
| CA-15 | RNF-01 | se inspecciona el constructor de alta | se examinan sus dependencias | el servicio coordina contratos de tenant, autorización, identidad, membresía, transacción y reloj |
| CA-16 | RNF-01 | se inspecciona autenticación | se examinan sus dependencias | no maneja alta ni revocación |
| CA-17 | RNF-01 | se inspecciona revocación | se examinan sus dependencias | no maneja credenciales ni alta |
| CA-18 | RNF-01 | se inspecciona control de sesión | se examinan sus dependencias | consulta expiración/contexto y revocación como responsabilidad separada |

## 5. SRP aplicado al flujo

SRP dice que una clase debe tener una sola responsabilidad y una razón principal para cambiar. En este flujo son motivos de cambio diferentes:

- política de alta, asignación de rol y creación transaccional;
- política de autenticación y elegibilidad de membresía;
- emisión del formato de sesión;
- revocación durable del identificador del token;
- verificación de una sesión en cada ruta protegida;
- acceso a tenant, identidades, membresías, credenciales, tiempo y almacenamiento.

Por ello, los casos de uso se separan en `RegisterTenantUser`, `AuthenticateTenantUser`, `RevokeCurrentSession` y `CheckSessionAccess`. Estos coordinan operaciones; no contienen SQL, configuración de Laravel, firma JWT ni almacenamiento de credenciales.

## 6. Diseño antes de SRP

El archivo [`AllInOneIdentityService.php`](ejemplo-srp/antes/AllInOneIdentityService.php) ilustra el problema: una clase atiende alta, autorización, validación, membresía, autenticación, creación de `jti` y revocación. Varias políticas y detalles cambian por razones distintas dentro del mismo servicio.

```text
AllInOneIdentityService
├── valida y autoriza altas
├── crea identidades y membresías
├── verifica el inicio de sesión
├── construye la sesión
├── revoca tokens
└── comprueba estado de sesión
```

El ejemplo antes es conceptual y no se ejecuta contra una base real. Los secretos y capacidades nativas/externas se expresan mediante indicadores de demostración para hacer visible el acoplamiento; no es un patrón seguro que se proponga copiar.

## 7. Diseño después de SRP

```text
RegisterTenantUser
  ├── TenantDirectory
  ├── UserManagementAuthorization
  ├── IdentityRepository
  ├── MembershipRepository
  ├── IdentityRegistrationTransaction
  └── Clock

AuthenticateTenantUser
  ├── TenantDirectory
  ├── IdentityRepository
  ├── MembershipRepository
  ├── CredentialVerifier
  ├── SessionTokenIssuer
  └── Clock

RevokeCurrentSession ── SessionRevocationStore + Clock
CheckSessionAccess   ── SessionRevocationStore + Clock
```

Los contratos están declarados junto con los modelos en [`IdentityWorkflow.php`](ejemplo-srp/despues/IdentityWorkflow.php); los dobles de integración están en [`InMemoryAdapters.php`](ejemplo-srp/despues/InMemoryAdapters.php). La verificación construye las colaboraciones para probar los casos de uso sin Laravel ni infraestructura.

La clase `InMemoryIdentityInfrastructure` es deliberadamente un fixture de prueba que implementa varios puertos para reducir el montaje. **No es el diseño de adaptadores productivos**. En una implementación real se separaría por componentes de persistencia/proveedor y se conectaría en la raíz de composición.

## 8. Responsabilidades y dependencias

| Componente | Responsabilidad única | No hace |
|---|---|---|
| `RegisterTenantUser` | Coordinar alta administrativa en un tenant. | No autentica, emite JWT ni revoca sesión. |
| `AuthenticateTenantUser` | Verificar tenant, identidad, credenciales y membresía y solicitar emisión de sesión. | No crea identidades ni revoca sesiones. |
| `RevokeCurrentSession` | Persistir la revocación de la sesión presentada. | No valida contraseña ni gestiona membresías. |
| `CheckSessionAccess` | Rechazar contexto incorrecto, expiración o `jti` revocado. | No registra usuarios ni autentica credenciales. |
| `IdentityRepository` | Leer y crear identidades globales. | No administra membresías o secretos. |
| `MembershipRepository` | Consultar/crear vínculo con el tenant y vigencia. | No verifica credenciales. |
| `CredentialVerifier` | Verificar el secreto mediante el proveedor abstracto. | No registra, muestra o devuelve el secreto. |
| `SessionTokenIssuer` | Crear la representación de sesión contextualizada. | El doble no firma JWT real. |
| `SessionRevocationStore` | Registrar y consultar `jti` revocados hasta su expiración. | No decide si la contraseña es correcta. |
| `Clock` | Proveer el momento actual. | No aplica política de expiración o acceso por sí solo. |

## 9. Evidencia verificable

Desde la raíz:

```bash
php docs/modulos/gestion-usuarios-tenants-autenticacion/semana-02/ejemplo-srp/verificar.php
```

La suite verifica las altas, errores genéricos, pertenencia por tenant, expiración limitada, revocación efectiva y separación de dependencias. La salida real se guarda en [`evidencia/salida-verificar.txt`](evidencia/salida-verificar.txt). La prueba usa hashes de secretos inventados en memoria; nunca contiene credenciales reales.

## 10. Trazabilidad con Semana 1

| Regla de Semana 1 | Responsabilidad relacionada | Criterio |
|---|---|---|
| R-01 — tenant y contexto activos | `TenantDirectory`, alta y autenticación | CA-05, CA-08, CA-10 |
| R-02 — membresía explícita y vigente | `MembershipRepository`, `TenantMembership` y autenticación | CA-01, CA-08, CA-10 |
| R-03 — identidad separada de membresía | `IdentityRepository` y `MembershipRepository` | CA-01, CA-02 |
| R-04 — protección de secretos | `CredentialVerifier` fuera del flujo de alta | CA-08, RNF-02 |
| R-05 — error genérico | `AuthenticateTenantUser` | CA-09, CA-10 |
| R-06 — sesión contextualizada | `SessionTokenIssuer` | CA-08 |
| R-07 — revocar antes de confirmar | `RevokeCurrentSession` | CA-12, CA-14 |
| R-08 — rechazar tokens revocados | `CheckSessionAccess` | CA-11 a CA-13 |
| R-09 — fallar cerrado | casos de uso ante fallo de proveedor/almacén | CA-07, CA-14 |
| R-11 — alta administrativa autorizada | `UserManagementAuthorization` y alta | CA-03, CA-04 |

## 11. Conclusión y límites

La separación evita que cambios de credenciales, altas administrativas y revocación tengan que modificar un mismo servicio. También permite probar las reglas con dependencias controladas. Mantener SRP no sustituye las garantías de transacción, la autorización RBAC real, la verificación criptográfica del JWT ni la disponibilidad del almacén de revocación.

La evidencia valida el diseño del ejemplo; **no** declara implementado el flujo en Laravel ni decide el proveedor de credenciales. En la Semana 3 se deberá continuar el mismo micro-HIS en PHP 8.2+ con capas Presentation/Application/Domain/Persistence, PDO y sentencias preparadas, manteniendo los contratos y las pruebas de camino feliz, regla de dominio y falla de persistencia.

## 12. Referencias

- Captura de la tarea Semana 2 «Gestión de usuarios, tenants y endurecimiento de autenticación», compartida por la estudiante.
- [Semana 1 — Modelado UML de alta de usuarios, autenticación y revocación de sesión](../semana-01/semana-01-uml-usuarios-tenants-autenticacion.md), reglas R-01 a R-11.
- Robert C. Martin, *Agile Software Development: Principles, Patterns, and Practices*, capítulo sobre Single Responsibility Principle.
- `composer.json` del proyecto: PHP 8.2 y Laravel 12; el ejemplo de esta entrega no requiere Composer.
