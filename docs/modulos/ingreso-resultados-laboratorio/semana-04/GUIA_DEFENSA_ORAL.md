# Guía de defensa oral — Semana 4

## Resumen en 60 segundos

«Mantuve el flujo de captura y corrección de resultados pendientes y le di entrada MVC: un front controller enruta, `ResultForm` valida el formato, `ResultController` coordina el caso de uso y `ResultView` produce el texto y el código de salida. El controlador no tiene SQL, reglas ni `new`. El caso de uso depende de la interfaz `LabResultRepository`, que tiene dos adaptadores, PDO SQLite e InMemory, elegidos en `bootstrap.php`. Una suite de contrato corre los mismos casos contra ambos. Para el repositorio compartido propongo un tercer adaptador sobre PostgreSQL, con historial solo anexado y escritura condicional.»

## Demostración

1. Mostrar el diagrama de capas y señalar dónde está el límite del repositorio.
2. Desde `semana-04/ejemplo/`: `php tests/run.php` (46 aserciones).
3. `php bin/demo.php`: captura, corrección y reintento obsoleto (conflicto, código 3).
4. Mostrar la prueba ARQ-01 y su control negativo.

## Preguntas probables

**¿Por qué el controlador no crea objetos?** Recibe el caso de uso ya armado; la composición vive en `bootstrap.php`.

**¿Dónde está la regla «no corregir validados»?** En Application/Domain, no en la vista ni en el controlador.

**¿Qué demuestra la suite de contrato?** Que InMemory y PDO se comportan igual, así que son intercambiables.

**¿Cómo se integraría el repositorio compartido?** Con un adaptador nuevo del mismo puerto; el cambio no toca controlador ni caso de uso. Debe pasar la suite de contrato.

**¿Qué falta?** Concurrencia real entre procesos, permisos por rol, validación y entrega; y el adaptador compartido es solo propuesta.

## Evidencia

- [Documento](semana-04-capas-mvc-repositorio-ingreso-resultados.md)
- [Validación](evidencia/validacion-semana-04.txt) · [Demo](evidencia/demo-semana-04.txt)
- [Diagramas](diagramas/)
