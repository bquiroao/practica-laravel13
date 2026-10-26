# Guía de defensa oral — Semana 2

**Estudiante según la captura:** Lopez Perez, Mercedes Azucena · **Módulo:** Gestión de usuarios, tenants y endurecimiento de autenticación · **Tema:** SRP aplicado a alta, login y revocación.

## Resumen en 60 segundos

> «Partí del flujo de Semana 1: dar de alta una identidad y su membresía para un tenant, autenticarla dentro de ese contexto y revocar la sesión. El servicio original concentraba estas tareas; cada política podía cambiar por una razón diferente. Apliqué SRP separando cuatro casos de uso: `RegisterTenantUser`, `AuthenticateTenantUser`, `RevokeCurrentSession` y `CheckSessionAccess`. Los conecté mediante contratos para tenant, autorización, identidad, membresía, verificación de credenciales, emisión y revocación. La suite PHP prueba reglas, fallos y el aislamiento entre tenants. Es una demostración en memoria, no una integración productiva ni un JWT firmado.»

## Cómo exponer

1. Empezar por el mismo flujo y los límites definidos en Semana 1.
2. Mostrar [`antes/AllInOneIdentityService.php`](ejemplo-srp/antes/AllInOneIdentityService.php): explicar que alta, credenciales, emisión de sesión y revocación comparten clase.
3. Mostrar el diagrama textual «después» en el [informe](semana-02-srp-usuarios-tenants.md): cada caso de uso coordina contratos de una política.
4. Explicar el aislamiento: identidad global no equivale a acceso; la sesión se liga a sujeto, tenant, `jti` y expiración.
5. Ejecutar el comando del [README del ejemplo](ejemplo-srp/README.md) y relacionar sus aserciones con los criterios CA.
6. Cerrar expresando las limitaciones: fixtures en memoria, JWT modelado no firmado y ningún proveedor de credenciales de producción.

## Preguntas frecuentes

**¿Qué significa SRP aquí?**

Cada caso de uso tiene una razón de negocio principal para cambiar. Las reglas de alta no cambian necesariamente junto con el mecanismo de credenciales o la persistencia de revocación.

**¿No se está creando una clase por método?**

No. Se separan políticas distintas y se declaran contratos para colaboradores externos. Los casos de uso siguen coordinando el flujo; no se extraen clases para pasos triviales sin responsabilidad propia.

**¿Por qué existe un adaptador en memoria que implementa tantas interfaces?**

Es un fixture de prueba para compartir estado y facilitar la ejecución sin servicios externos. No se propone como adaptador productivo; en la aplicación real se separarían los adaptadores y se conectaría cada contrato en la raíz de composición.

**¿`SessionToken` es un JWT seguro?**

No. Es un objeto de prueba que modela sujeto, tenant, `jti` y expiración, suponiendo que la firma ya fue verificada en el límite. La firma, validación criptográfica y biblioteca JWT quedan fuera del ejemplo.

**¿Cómo demuestra revocación si no hay base de datos?**

El doble simula un almacén que registra y consulta el `jti`; la prueba demuestra el orden y la política del caso de uso, no la durabilidad de un sistema real.

**¿Dónde se almacenan las credenciales?**

El diseño deja el proveedor como dependencia abstracta y no define almacenamiento productivo, coherente con la decisión pendiente en Semana 1. El adaptador de prueba almacena hashes en memoria con `password_hash`.

**¿Qué ocurre si falla el almacenamiento al cerrar sesión?**

El caso de uso devuelve `STORAGE_UNAVAILABLE` y no confirma la revocación. En una aplicación real la respuesta debe reflejar que no se pudo completar el cierre y las rutas protegidas deben continuar consultando el almacén.

## Evidencia que se puede demostrar

```bash
php docs/modulos/gestion-usuarios-tenants-autenticacion/semana-02/ejemplo-srp/verificar.php
```

La salida en [`evidencia/salida-verificar.txt`](evidencia/salida-verificar.txt) debe corresponder a la ejecución más reciente.

## Revisión de autoría

Antes de presentar, leer el informe completo, confirmar que las reglas coinciden con Semana 1, verificar la salida de pruebas y completar en [`DECLARACION_IA.md`](DECLARACION_IA.md) la revisión humana. La guía apoya la defensa; no sustituye la comprensión de cada clase y criterio.
