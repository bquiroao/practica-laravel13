# Declaración de uso de inteligencia artificial — Semana 2

| | |
|---|---|
| **Estudiante según la captura** | Lopez Perez, Mercedes Azucena |
| **Módulo** | Gestión de usuarios, tenants y endurecimiento de autenticación |
| **Entrega** | Semana 2 — Requerimientos, criterios de aceptación y aplicación de SRP |

## Herramienta y propósito

Se utilizó un asistente de IA integrado en VS Code como apoyo para organizar la consigna, trazarla al modelado de Semana 1, proponer la separación SRP, elaborar un ejemplo PHP ejecutable, redactar documentación y revisar la ejecución de pruebas. La IA apoyó la preparación; la estudiante debe revisar, comprender y validar el material antes de entregarlo.

## Artefactos elaborados con apoyo de IA

| Archivo | Aporte |
|---|---|
| `semana-02-srp-usuarios-tenants.md` | RF/RNF, criterios de aceptación, responsabilidades antes/después y trazabilidad a Semana 1. |
| `ejemplo-srp/antes/AllInOneIdentityService.php` | Ejemplo ilustrativo del acoplamiento que motiva la aplicación de SRP. |
| `ejemplo-srp/despues/*.php` | Contratos, casos de uso y fixture para probar el flujo en memoria. |
| `ejemplo-srp/verificar.php` | Aserciones automatizadas asociadas a los criterios de aceptación. |
| `ejemplo-srp/README.md` y `evidencia/salida-verificar.txt` | Instrucciones y evidencia del comando de verificación. |
| `GUIA_DEFENSA_ORAL.md` | Estructura de exposición y preguntas posibles. |
| `DECLARACION_IA.md` | Borrador de esta declaración. |

## Límites y validación

El ejemplo no es una integración Laravel ni usa una base de datos; no contiene credenciales reales. Los hashes son inventados y se mantienen en memoria durante la ejecución. El token de sesión es una representación de prueba, no un JWT firmado. El almacén de revocación también es un doble en memoria. El proveedor y la ubicación de credenciales de producción siguen pendientes; la entrega no los presenta como decisiones resueltas.

La salida de [`ejemplo-srp/verificar.php`](ejemplo-srp/verificar.php) se debe ejecutar y revisar. La estudiante debe verificar que los criterios corresponden a la consigna y reglas de Semana 1, comprender por qué se separaron las responsabilidades y adaptar cualquier contenido con el que no esté de acuerdo.

## Revisión humana de la estudiante

Completar después de revisar el material:

| Elemento | Aceptado, modificado o descartado | Cambios o justificación |
|---|---|---|
| Requerimientos y criterios de aceptación | | |
| Separación de casos de uso y contratos | | |
| Alcance y límites de seguridad | | |
| Código y pruebas del ejemplo | | |
| Informe y guía de defensa | | |

- [ ] Leí y comprendí el informe, los criterios y el ejemplo.
- [ ] Verifiqué que la salida de pruebas corresponde al comando documentado.
- [ ] Confirmé que no se presentan dobles de prueba como infraestructura productiva.
- [ ] Revisé y adapté el material a mi propia comprensión.

**Fecha de revisión:** ____________________
