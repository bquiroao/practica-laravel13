# Guía de defensa oral — Semana 2

**Estudiante:** Lopez Perez, Mercedes Azucena · **Módulo:** Recepción de muestras y códigos identificadores · **Documento:** [semana-02-lsp-recepcion-muestras.md](semana-02-lsp-recepcion-muestras.md)

## 1. Resumen en 60 segundos

> «Apliqué el principio de sustitución de Liskov al diseño de validación para recepción de muestras. El problema original era una subclase especializada en sangre que lanzaba una excepción al recibir otra clase de muestra, aunque el contrato general prometía evaluar muestras. Eso sorprendía al servicio que coordinaba el flujo. En el diseño corregido, todas las políticas reciben el mismo objeto y devuelven un resultado explícito: aceptación, rechazo con motivos o no aplicación. La política de sangre agrega el chequeo de sello; para otros tipos informa “no aplica” y el servicio continúa con las reglas generales. Si no hay ninguna regla aplicable, el servicio no acepta por defecto. Las pruebas verifican la sustitución, los rechazos y el comportamiento agregado.»

## 2. Recorrido de demostración

1. Abrir la sección «Diseño antes» y explicar qué precondición adicional introduce la política restringida.
2. Mostrar `ejemplo-lsp/src/SpecimenAcceptance.php`: decisión explícita, contrato, reglas y servicio consumidor.
3. Explicar cómo la política de sangre responde «no aplica» a una muestra que no es de sangre y cómo el servicio continúa con la regla general.
4. Ejecutar desde `ejemplo-lsp/`:

   ```powershell
   php -l src/SpecimenAcceptance.php
   php -l tests/run.php
   php tests/run.php
   ```

5. Mostrar que el mismo contrato de pruebas se ejecuta con las dos políticas y que las pruebas de regla fallida devuelven motivos.

## 3. Preguntas probables

**¿Por qué lanzar una excepción rompe LSP?**

Porque el consumidor que dependía de la abstracción esperaba que toda muestra pudiera evaluarse. El sustituto restringe el conjunto de entradas válidas sin que el cliente lo espere.

**¿LSP significa que todas las reglas deben producir el mismo resultado?**

No. Una implementación puede añadir restricciones legítimas de su especialidad, siempre que respete el contrato común, sus precondiciones y las garantías del resultado.

**¿Por qué el servicio depende de una interfaz?**

Para invocar cualquier política sin condicionales sobre clases concretas. Las implementaciones se pueden sustituir si conservan el mismo comportamiento contractual.

**¿Qué significa que una regla especializada no aplica?**

Que esa regla no aporta aceptación ni rechazo para una categoría que no evalúa. No equivale a aceptar la muestra: las reglas generales y todas las reglas aplicables siguen ejecutándose; si ninguna aplica, el servicio rechaza por defecto.

**¿El ejemplo implementa recepción persistente o criterios clínicos?**

No. Es un ejemplo de dominio en memoria. No define umbrales clínicos, API, tablas, códigos de muestra ni impresión.

## 4. Decisiones clave

| Decisión | Justificación |
|---|---|
| Modelar las no conformidades como decisión con razones | Son resultados del negocio que deben poder comunicarse y probarse; no son errores inesperados del programa. |
| Mantener el mismo tipo de entrada para las políticas | Evita fortalecer precondiciones al sustituir una implementación. |
| Consolidar una regla general y las reglas específicas aplicables | Conserva el comportamiento base y añade restricciones particulares sin duplicar validaciones. |
| Separar decisión, coordinación y persistencia futura | Mantiene responsabilidades pequeñas y permite pruebas de reglas sin infraestructura. |
| Probar las implementaciones mediante el mismo contrato | Detecta regresiones de sustitución en cualquier política nueva. |
