# Guía de defensa oral — Semana 7

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA, pruebas E2E, CI y guía de despliegue final · **Tema:** componentes, contratos y refactor por capas

## 1. Resumen en 60 segundos

Esta semana hay tres entregables:
- Un diagrama de componentes por capas: clientes, presentación, aplicación, dominio y persistencia, con la regla de dependencias verificada por pruebas.
- Los contratos de entrada y salida de cada frontera.
- Un refactor medido.

**Qué se midió:** con `bin/acoplamiento.php` medí qué clases tomaban decisiones de negocio fuera del dominio. Aparecieron dos:
- `ReportarResultado`, con 27 ramas y 5 reglas;
- `RegistrarDespliegue`, que elegía la evaluación vigente.

**Qué se movió:** esas reglas pasaron al dominio, a `ReporteResultado`, `Ejecucion::preparar` y `Despliegue::registrar`.

**Cómo se probó que nada cambió:** antes de tocar nada grabé 35 respuestas reales y comprobé que siguen idénticas.

**Resultado:** las reglas de negocio fuera del dominio bajaron de 8 a 2 (las 2 que quedan son de transporte), sin una capa ni una tabla nueva y con el contrato HTTP intacto.

## 2. Orden sugerido (8 minutos)

| Paso | Qué mostrar | Minutos |
|---|---|---|
| 1 | Diagrama de componentes: las 5 capas y la flecha prohibida (el dominio no conoce nada) | 1:30 |
| 2 | Contratos: una operación completa, por ejemplo reportar resultado, desde HTTP hasta el repositorio | 1:30 |
| 3 | `acoplamiento-antes.txt`: por qué esas dos clases | 1:00 |
| 4 | Código antes y después de `ReportarResultado` | 2:00 |
| 5 | Caracterización: `esperado.json`, CAR-01 en verde y las mutaciones | 1:30 |
| 6 | Lo que empeoró (`Ejecucion`) y por qué se acepta | 0:30 |

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Cómo sabés que el refactor no cambió el comportamiento? | Grabé 35 respuestas con el código anterior (`bin/caracterizar.php`) y CAR-01 las compara. Incluyen combinaciones de dos fallas a la vez, donde el orden de las reglas decide el código. Además, la suite completa pasa contra PostgreSQL y las mutaciones siguen detectadas. |
| ¿Por qué no un servicio o una clase `Validador` aparte? | Agranda el módulo y necesita el estado interno de `Ejecucion`. El enunciado pide separar capas sin agrandar. Solo sumé una clase de dominio, `ReporteResultado`. |
| ¿Qué queda en la aplicación? | Orquestación: permiso, buscar, usar el almacén después de las reglas, transacción, auditoría y traducir a `Salida`. |
| ¿Qué encontraste? | M32 sobrevivió: nada probaba que `repite()` compare la ejecución, y el hueco venía de antes. Mientras la regla estaba enterrada en el caso de uso, no se podía probar sola. Al moverla al dominio escribí DOM-11, y con ella las 33 mutaciones quedan detectadas. |
| ¿Por qué el Kernel conserva `preg_match`? | Valida el formato de `Bearer` y de `X-Request-ID`. Son reglas del transporte HTTP, no del negocio. |
| ¿Algo empeoró? | Sí. `Ejecucion` pasó de 2 a 4 preocupaciones y de 113 a 133 líneas, y el Ce total subió 5. Lo dejo anotado para la revisión técnica de la semana 13. |
| ¿Por qué hubo que tocar las mutaciones? | Cinco buscaban texto que se movió (M6, M10, M11, M12 y M26). Las reubiqué con el **mismo** defecto en el código nuevo y sumé M32 y M33 sobre lo nuevo. Si no, el refactor habría dejado reglas sin vigilancia. |
| ¿Cambió el contrato? | El HTTP no: sigue en 1.1.0. Hacia adentro cambió la firma de `Despliegue::registrar`, y su único llamador se actualizó en el mismo cambio. |
| ¿Dónde entra el frontend? | La consola de release es un cliente más del contrato HTTP, igual que la CLI `ReportadorCi`. Sus vistas se diseñan de la semana 8 a la 11. |
