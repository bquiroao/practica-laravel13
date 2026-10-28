# Guía de defensa oral — Semana 5

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Gobernanza y auditoría · **Documento:** [semana-05-cliente-servidor-microservicios.md](semana-05-cliente-servidor-microservicios.md)

## 1. Resumen en 60 segundos

> «Llevé el micro-HIS a cliente-servidor. Escribí el contrato OpenAPI con cinco operaciones; no hay ninguna para modificar ni borrar. Tres pruebas comprueban que el contrato y el código coinciden, con 24 escenarios reales. Hice un cliente para los módulos emisores que reintenta sin duplicar, porque el event_id hace idempotente el registro. Si el servicio está caído, guarda el movimiento en una bandeja local y lo reenvía después, así que el emisor nunca falla por culpa de la auditoría. Agregué X-Request-ID, un registro de acceso sin datos personales y un ancla externa que por fin detecta que borren la última fila. Después evalué si la auditoría debe ser un microservicio y lo medí: registrar en proceso cuesta 1 ms y por HTTP 69 ms. La decisión es no separarla todavía y que los emisores escriban de forma asíncrona. Dejé cinco criterios medibles para extraerla y el plan de issue, rama, worktree y PR. Son 78 pruebas en verde con PostgreSQL y 18 de 18 mutaciones.»

## 2. Orden sugerido de la exposición

1. **Decisión** (§1) con la tabla de medición.
2. **Contrato** (§4) y API-03.
3. **Resiliencia** (§9) con el diagrama de secuencia.
4. **Ancla externa:** APP-11 e INT-07.
5. **Migración y criterios** (§5.3 y §12).
6. **Plan de issue y PR** (§13).

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| No extraer el servicio todavía | 68 ms más por movimiento, y sobre todo acoplar la disponibilidad de la atención a la de la auditoría. Sin SLA ni volumen que lo justifique. |
| Escritura asíncrona de los emisores | La auditoría no puede tumbar la atención de un paciente. |
| Idempotencia con `event_id` en el cuerpo | Ya es la identidad del evento en el emisor y en la bandeja central. |
| No reintentar ni guardar un 4xx | Un 4xx no se arregla reintentando: es un error del emisor que hay que corregir (M16 lo comprueba). |
| Ancla externa | Un sello encadenado no ve que falte el final; un valor publicado fuera de la base sí. |
| Registro de acceso sin consulta | Los filtros traen ids de usuario: el log técnico no debe convertirse en otra bitácora sin control. |

## 4. Preguntas probables y respuestas

**¿Qué es una frontera de microservicio?**
El límite donde un componente pasa a desplegarse y a tener sus datos por separado, y la comunicación pasa a ser por red. Se justifica por autonomía, escala o seguridad, y cuesta latencia, fallas parciales y consistencia eventual.

**¿Por qué 69 ms si es la misma máquina?**
`php -S` arranca un proceso y abre una conexión nueva a PostgreSQL por petición. En producción bajaría, pero la decisión se apoya en la disponibilidad, no solo en la latencia.

**¿Qué es el patrón outbox?**
Guardar el mensaje localmente y enviarlo después, de forma confiable. Junto con la idempotencia en el receptor, da un efecto de «exactamente una vez».

**¿Qué hace el circuit breaker?**
Tras 3 fallas deja de llamar durante 30 s, así no se insiste contra un servicio caído. Luego prueba una vez (semiabierto) y, si responde, vuelve a cerrado.

**¿Cómo sabe que el contrato no miente?**
API-03 ejecuta 24 escenarios reales y comprueba que cada estado y cada código de error que sale esté documentado en el contrato. API-01 compara las operaciones y los permisos con el código.

## 5. Recorrido de demostración

```bash
cd docs/modulos/gobernanza-auditoria/micro-his-auditoria
npx @redocly/cli@1.34.3 lint api/openapi.yaml
php tests/ejecutar.php                       # 63 pasan, 15 omitidas
AUDITORIA_DB_DSN=… php tests/ejecutar.php    # 78/78
AUDITORIA_DB_DSN=… php -S 127.0.0.1:8088 -t public &
AUDITORIA_DB_DSN=… php bin/medir.php 200
```
