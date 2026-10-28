# Guía de defensa oral — Semana 2

**Estudiante:** Lopez Perez, Mercedes Azucena · **Módulo:** Ingreso de resultados de laboratorio · **Documento:** [semana-02-lsp-ingreso-resultados.md](semana-02-lsp-ingreso-resultados.md)

## 1. Resumen en 60 segundos

> «Apliqué LSP al flujo de captura y corrección de resultados pendientes. El problema era un tipo base que ofrecía corregir cualquier resultado y un subtipo validado que lanzaba una excepción al recibir esa operación. El subtipo mantenía la firma, pero rompía la expectativa del consumidor. En el diseño corregido, pendiente y validado son tipos distintos sin esa herencia engañosa; el servicio de corrección recibe únicamente resultados pendientes y depende de un repositorio abstracto. Cada corrección crea una nueva versión con motivo y referencia a la anterior, y la escritura compara de forma atómica la versión esperada. La suite ejecuta el mismo contrato en dos adaptadores.»

## 2. Recorrido de demostración

1. Mostrar la sección «Diseño antes» y explicar por qué la excepción del subtipo rompe el contrato conductual.
2. Abrir `ejemplo-lsp/src/LabResultWorkflow.php`: observar tipos separados, resultado explícito y puerto `PendingResultRepository`.
3. Mostrar cómo la corrección crea una versión nueva ligada a la anterior y cómo el repositorio compara el ID esperado.
4. Desde `ejemplo-lsp/`, ejecutar:

   ```powershell
   php -l src/LabResultWorkflow.php
   php -l tests/run.php
   php tests/run.php
   ```

5. Revisar la suite de contrato y las pruebas de estado validado, motivo obligatorio y conflicto por versión obsoleta.

## 3. Preguntas probables

**¿Por qué el subtipo validado viola LSP si tiene el mismo método?**

Porque el consumidor que esperaba que `correct()` funcionara para `LabResult` recibe una excepción nueva al sustituir el objeto por `ValidatedLabResult`. Tener la misma firma no garantiza sustitución conductual.

**¿Por qué no se mantiene un método `correct()` que simplemente devuelva false?**

Eso seguiría exponiendo una operación no válida para el estado validado y trasladaría al cliente una precondición oculta. El modelo separa las capacidades: la corrección se ofrece sobre el tipo pendiente.

**¿Se puede sobrescribir el resultado anterior?**

No. Una corrección produce otra versión con un vínculo al identificador previo, motivo y autor. El historial anterior se conserva.

**¿Qué pasa si el bioquímico valida mientras se intenta corregir?**

La operación de escritura incluye el ID de la versión que se leyó y solo añade la corrección si aún es la versión vigente y sigue pendiente. Si cambió, devuelve conflicto y el usuario debe recargar.

**¿Este ejemplo define cómo validar el resultado clínicamente?**

No. La validación bioquímica es otro módulo; aquí solo se modelan captura y corrección mientras está pendiente.

**¿Por qué probar dos adaptadores?**

Para verificar que el servicio puede sustituir implementaciones del puerto sin cambiar el resultado observable que define el contrato.

## 4. Decisiones clave

| Decisión | Justificación |
|---|---|
| Evitar una jerarquía mutable con operaciones incompatibles | No se promete al cliente que un resultado validado pueda corregirse. |
| Usar versiones inmutables | Conserva trazabilidad y evita cambios silenciosos al valor original. |
| Exigir motivo y enlazar la versión anterior | La corrección queda explicable y auditable. |
| Comparar versión esperada al guardar | Evita corregir sobre un resultado que cambió o se validó concurrentemente. |
| Probar contrato contra adaptadores sustituibles | LSP se comprueba con comportamiento observable, no solo herencia. |
