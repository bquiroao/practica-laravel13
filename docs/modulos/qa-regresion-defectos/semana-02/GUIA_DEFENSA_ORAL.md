# Guía de defensa oral — Semana 2 (RF/RNF, criterios y LSP)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA funcional, pruebas manuales y matriz de regresión · **Documento:** [semana-02-lsp-requerimientos-diseno.md](semana-02-lsp-requerimientos-diseno.md)

## 1. Resumen en 60 segundos

Definí 14 RF y 8 RNF, con 15 criterios de aceptación que se ejecutan.

Apliqué LSP donde más pesa en este módulo, la **evidencia**. En el antes, todo lo que se adjuntaba era una `Evidencia`, y dos subtipos rompían el contrato:
- **El enlace al log del CI:** su huella era la de la URL. Cuando el log rotaba, la verificación seguía diciendo «íntegra».
- **La captura con datos clínicos:** lanzaba al leerla, y el informe de la campaña dejaba de generarse.

En el después:
- **el contrato es explícito** y sus métodos son `final`, así que esas violaciones ya no se pueden escribir;
- **el enlace dejó de ser una evidencia:** se descarga una vez y se guarda una copia;
- **los datos clínicos se protegen** en la Aplicación, con un permiso.

**La prueba es la misma suite de contrato** aplicada a cada subtipo: 4 violaciones en el antes y 0 en el después. 21/21 pruebas y 15/15 mutaciones detectadas.

## 2. Orden sugerido

1. Por qué LSP y no otro principio: la evidencia tiene muchas formas (§1).
2. RF y RNF: RNF-01 es el requisito que exige LSP (§2, §3).
3. El antes: la tabla de los dos subtipos y sus consecuencias (§6).
4. Correr `verificar.php` y mostrar LSP-01 frente a LSP-02, y LSP-03.
5. El después: `final`, `ReferenciaExterna` y `ConsultarEvidencia` (§7).
6. `mutaciones.php`: M1..M3 reintroducen el antes y se detectan.

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué no basta con documentar el contrato? | Porque el antes también estaba documentado y se rompió igual. `final` hace que la violación no compile, y la suite de contrato hace que no pase las pruebas. |
| ¿`ResultadoJUnit::resumen()` no viola LSP? | No: agrega un método, no cambia ninguno del contrato. LSP prohíbe debilitar postcondiciones o reforzar precondiciones, no extender. |
| ¿Y si de verdad se necesita evidencia «en vivo» del CI? | Entonces no es evidencia: es una `ReferenciaExterna`, útil como contexto. Lo que prueba una FALLA tiene que poder volver a comprobarse. |
| ¿No es más simple un `if ($e instanceof EnlaceExterno)` en el informe? | Funciona hoy y falla con el próximo subtipo. Es lo que LSP evita: que los clientes tengan que conocer los subtipos. LSP-06 verifica que hay 0. |
| ¿Por qué validar el formato en el constructor y no en un método? | Porque así una evidencia que existe ya es válida, y los métodos no tienen precondiciones que un subtipo pueda reforzar. |

## 4. Cambios que podrían pedir en vivo

| Pedido | Respuesta |
|---|---|
| «Agregar un video como evidencia» | Una clase `Video extends EvidenciaInmutable` que valide la firma en el constructor; se agrega a LSP-02 y nada más cambia. |
| «Que el tamaño máximo dependa del tipo» | Sería una regla de la Aplicación por `tipo()`; mejor como política configurable, sin `instanceof`. |
| «Mostrar que una violación no compila» | Quitar `final` es la mutación M1: LSP-02 la detecta. Sobrescribir un método `final` da un error fatal de PHP. |
