# Guía de defensa oral — Semana 3 (Micro-HIS por capas, PDO y vista C4)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA funcional, pruebas manuales y matriz de regresión · **Documento:** [semana-03-arquitectura-micro-his.md](semana-03-arquitectura-micro-his.md)

## 1. Resumen en 60 segundos

El proceso de las semanas 1 y 2 ya corre. Es un Micro-HIS en PHP sin framework, con cuatro capas:

- **Presentación:** HTTP.
- **Aplicación:** 9 casos de uso.
- **Dominio:** las 14 reglas.
- **Persistencia:** PDO sobre PostgreSQL y un almacén de evidencias en disco.

La dependencia apunta siempre al Dominio, y lo verifican 7 pruebas de arquitectura.

Las reglas que no pueden fallar también están en la base:

- un intento no se edita ni se borra (trigger);
- una versión aprobada no cambia;
- el autor no puede ser el aprobador;
- la clave de idempotencia es única.

La tarea pedía tres tipos de prueba y hay de los tres:

- **camino feliz:** diseñar → … → GO;
- **reglas de dominio:** DOM-01..09;
- **errores de persistencia:** con dobles (almacén caído, traza que falla, base caída) y con PostgreSQL real (ROLLBACK, triggers).

Resultados: 53 pruebas y 16/16 mutaciones detectadas. Las pruebas encontraron 2 defectos reales que corregí.

## 2. Orden sugerido

1. **Contexto C4:** cinco roles y cuatro sistemas externos detrás de puertos (§3).
2. **Paquetes:** la regla de dependencias y ARQ-01..07 (§6).
3. **Vista dinámica de «registrar resultado»:** por qué el almacén va antes de la transacción (§7, D-4).
4. **Correr** `php tests/ejecutar.php` y mostrar APP-11 y APP-12.
5. **Correr** `bin/demo-http.sh`: NO_GO por un crítico, corrección, re-ejecución que cierra y GO.
6. **Hallazgo H-2:** la mutación que sobrevivía (§12).

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué el almacén va fuera de la transacción? | Porque un almacén externo no participa del COMMIT. Si va antes y falla, no se escribe nada (fail-closed). Si va antes y lo que falla es la transacción, queda un archivo huérfano, pero direccionado por su huella: no se pierde ni cambia nada. |
| ¿Por qué repetir reglas en la base si ya están en el dominio? | Porque el dominio protege a quien pasa por la aplicación. Un script de mantenimiento o un error futuro no pasa por ella. INT-03 hace un `UPDATE intentos` directo y la base lo rechaza. |
| ¿Dónde está el tenant? | Sale del token, nunca del cuerpo. Toda consulta filtra por tenant y las FK lo incluyen. Una campaña de otro hospital responde 404, igual que una inexistente (INT-07). M13 lo quita del SQL y se detecta. |
| ¿Por qué dos mecanismos de idempotencia? | La consulta previa da la respuesta correcta aunque la campaña ya esté cerrada. La restricción UNIQUE cubre dos peticiones simultáneas. Sin la primera, un reintento tras el cierre daba 409 (H-2, APP-15). |
| ¿Qué es un «doble» y por qué no un mock? | Es una implementación en memoria del mismo puerto, que además puede fallar a pedido y deshacer como una transacción. Así pruebo el comportamiento (qué quedó guardado), no qué métodos se llamaron. |
| ¿Cómo sé que las pruebas sirven? | Por las mutaciones: 16 defectos sembrados (reglas, SQL, esquema, traductor) y todos hacen fallar alguna prueba. |

## 4. Cambios que podrían pedir en vivo

| Cambio | Dónde | Prueba que lo confirma |
|---|---|---|
| Umbral de GO al 90 % por defecto | `PlanificarCampana::UMBRAL_POR_DEFECTO` | DOM-05/06 siguen; agregar una con tasa 0,92 |
| BLOQUEADO también exige defecto | `RegistrarResultado` (la condición de R-06) y el CHECK `intentos_falla_con_defecto` | Copia de APP-05 con BLOQUEADO |
| Un tester no puede re-ejecutar su propio defecto | `RegistrarResultado::defectoAVerificar` (comparar con `reportadoPor`) | Prueba nueva → 409 |

## 5. Comandos

```bash
cd docs/modulos/qa-regresion-defectos/micro-his-qa
php tests/ejecutar.php
QA_DB_DSN="pgsql:host=127.0.0.1;port=55432;dbname=micro_his_qa" php tests/ejecutar.php --solo=INT
QA_DB_DSN=... php tests/mutaciones.php
QA_DB_DSN=... bash bin/demo-http.sh
```
