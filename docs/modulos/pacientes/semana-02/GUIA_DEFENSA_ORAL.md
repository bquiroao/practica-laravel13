# Guía de defensa oral — Semana 2

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Pacientes · **Documento:** [semana-02-lsp-requerimientos-diseno.md](semana-02-lsp-requerimientos-diseno.md)

## 1. Resumen en 60 segundos

> «LSP dice que si mi código funciona con un tipo base, tiene que seguir funcionando con cualquiera de sus subtipos. En el módulo de pacientes encontré tres subtipos que compilaban pero no se comportaban como prometía la base. Un identificador temporal con clave vacía hacía que dos desconocidos parecieran el mismo paciente. Una réplica de lectura heredaba del repositorio y lanzaba una excepción al escribir. Y una caché heredaba del repositorio y escribía sin comparar la versión, así que se perdían ediciones sin ningún error. Los corregí cambiando las abstracciones, no agregando ifs: sin documento es null, la lectura y la escritura son contratos separados, y la caché envuelve al repositorio en vez de heredar. Lo demuestro con pruebas de contrato: la misma batería pasa con todas las implementaciones nuevas y falla con las viejas. 25 pruebas en verde.»

## 2. Orden sugerido de la exposición

1. **Requerimientos:** RF-01 a RF-11 salen de la semana 1. RF-04 (pacientes sin documento) es nuevo y es justo el que provoca la violación V1.
2. **LSP:** la cita de la fuente del curso y el ejemplo cuadrado/rectángulo. Luego las cuatro reglas: precondiciones, postcondiciones, invariantes y excepciones.
3. **Antes:** el diagrama rojo y las tres violaciones. Insistir en V3: **no da ningún error**.
4. **Después:** las tres correcciones y por qué ninguna es un `if`.
5. **Evidencia:** ejecutar `verificar.php` en vivo y mostrar las mutaciones, sobre todo M4.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| No existe `IdentificadorTemporal`: sin documento, `identificador = null` | Un objeto que «no aplica» pero dice ser identificador rompe la invariante «una clave = una persona». `null` es honesto: el servicio no tiene clave que comparar y no necesita `instanceof`. |
| La réplica implementa solo `ConsultaPacientes` | Si no puede escribir, no debe prometer que escribe. Con la interfaz separada, el error aparece **al componer** (`TypeError`) y no en producción. |
| La caché es un decorador, no una subclase | Al delegar `actualizar()` al repositorio envuelto, hereda **su** garantía de versión en lugar de reimplementarla mal. |
| La caché invalida en vez de guardar lo escrito | Si la transacción se revierte (por ejemplo, porque la bitácora falla), una caché que guardó el dato serviría algo que nunca se confirmó. |
| El servicio único se dividió en tres | Cada uno pide el contrato **mínimo**: la búsqueda solo necesita leer, así que acepta la réplica. |
| El estado (fallecido) es un dato, no una subclase | `PacienteFallecido extends Paciente` sería otro cuadrado/rectángulo. Además, el estado cambia en el tiempo y un objeto no cambia de clase. |
| La versión la compara el repositorio, no el servicio | Leer, comparar y escribir por separado tiene una carrera. El `UPDATE ... WHERE version = ?` es atómico, y por eso es parte del contrato. |

## 4. Preguntas probables y respuestas

**¿Qué diferencia hay entre LSP y simplemente «usar herencia»?**
La herencia es un mecanismo del lenguaje. LSP es una regla de **comportamiento**: dice cuándo esa herencia (o implementación de interfaz) es correcta. Las tres violaciones del antes compilan perfectamente.

**¿IdentificadorTemporal no hereda nada, solo implementa una interfaz. ¿Igual aplica LSP?**
Sí. LSP habla de subtipos, y implementar una interfaz crea un subtipo. El contrato de `Identificador` está escrito en su comentario y en las pruebas; el temporal lo viola (I1, I3).

**¿Cómo se «prueba» LSP?**
Con pruebas de contrato: se escriben **una vez** contra el tipo base y se ejecutan contra **cada** implementación. Si un subtipo es sustituible, pasa la misma batería. Eso es LSP-01 a LSP-03; y ANT-01 a ANT-03 muestran que la misma batería detecta las del antes.

**¿Por qué V3 es la más peligrosa?**
Porque no tiene síntoma: no lanza excepción ni obliga a usar `instanceof`. Responde 200 y borra la edición de otro usuario. Solo una prueba de contrato (R5) la detecta.

**¿Qué es una prueba de mutación y qué encontró?**
Se rompe el código a propósito y se verifica que alguna prueba falle. Encontró que una caché que no se invalidaba pasaba todo (M4). El contrato no exigía que después de actualizar se leyera lo nuevo. Se agregó la cláusula R8. Lección: un subtipo solo puede cumplir lo que el contrato dice explícitamente.

**¿`instanceof` siempre es malo?**
No en todos los casos, pero en un cliente que recibe una abstracción es una **señal** de que algún subtipo no cumple el contrato. En el antes hay 3, y basta olvidar uno para tener un defecto (ANT-05).

**¿Qué relación tiene con los otros principios SOLID?**
- **ISP:** separar `ConsultaPacientes` de `RepositorioPacientes` es segregar interfaces, y eso es lo que permitió que la réplica cumpliera LSP.
- **DIP:** los servicios dependen de contratos y solo `composicion.php` conoce las clases concretas.
- **OCP:** agregar un tipo de documento es agregar una clase, sin tocar los servicios.

**¿Esto ya está en el sistema real?**
No. Es un ejemplo de diseño con adaptadores en memoria. El sistema real necesitará adaptadores PDO para `local_patients` y para el MPI, y deberán pasar las **mismas** pruebas de contrato antes de usarse.

## 5. Recorrido de demostración

1. Ejecutar `php docs/modulos/pacientes/semana-02/ejemplo-lsp/verificar.php` → 25/25.
2. Abrir [`antes/RepositorioPacientesConCache.php`](ejemplo-lsp/antes/RepositorioPacientesConCache.php) y señalar que `actualizar()` ignora `$versionEsperada`.
3. Mostrar ANT-06 en `verificar.php`: dos ediciones con versión 1 y ambas responden 200.
4. Abrir [`despues/src/Adaptadores/RepositorioPacientesConCache.php`](ejemplo-lsp/despues/src/Adaptadores/RepositorioPacientesConCache.php): la escritura se delega.
5. Abrir [`pruebas/contratos.php`](ejemplo-lsp/pruebas/contratos.php) y mostrar la cláusula R5.
6. Mostrar [`evidencia/mutaciones.txt`](evidencia/mutaciones.txt) y explicar M4.

## 6. Cambios que podrían pedirte en vivo

| Cambio solicitado | Cómo hacerlo |
|---|---|
| Agregar un tipo de documento (por ejemplo, `PartidaNacimiento` para menores) | Crear `despues/src/Dominio/PartidaNacimiento.php` que implemente `Identificador` (con `ClaveIdentificador::calcular()` y `enmascarar()`), y agregar un `case` en `FabricaIdentificador`. Para probar LSP, en LSP-01 agregar `igual([], contratoIdentificador($fabricaPartida), 'PartidaNacimiento')`. Los servicios no se tocan. |
| Demostrar que la caché nueva es necesaria | En `RepositorioPacientesConCache` (después), borrar la línea `unset(...)` y ejecutar: falla LSP-03 con «R8 después de actualizar, porCodigo devuelve datos viejos». |
| Demostrar V2 sin las pruebas | En `composicion.php`, pasar `new ReplicaLecturaPacientes($almacen)` como primer argumento de `ServicioRegistroPacientes`: PHP lanza `TypeError` al componer. |
| Usar la réplica para la búsqueda | Llamar a `componerPacientes('replica')`. Es lo que hace LSP-06: el resultado es idéntico. |
| Cambiar el límite de búsqueda a 10 | Cambiar `CriterioBusqueda::LIMITE`. El contrato Q5 usa la constante, así que sigue pasando; CA-06 espera 20 y fallará, lo que es correcto porque cambió un requerimiento. |

Para regenerar los diagramas:

```bash
java -jar plantuml.jar -charset UTF-8 -tpng docs/modulos/pacientes/semana-02/diagramas/*.puml
```
