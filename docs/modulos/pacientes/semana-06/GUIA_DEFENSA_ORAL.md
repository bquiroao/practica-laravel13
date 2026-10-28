# Guía de defensa oral — Semana 6 (primera evaluación parcial)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** ASII-03 Pacientes · **Presentación:** [semana-06-defensa-pacientes.pptx](presentacion/semana-06-defensa-pacientes.pptx) · **Documento:** [semana-06-defensa-arquitectura.md](semana-06-defensa-arquitectura.md)

## 1. Resumen en 60 segundos

> «Defiendo la arquitectura del proceso "registro, búsqueda y actualización segura de un paciente" como una cadena trazable. Los actores (admisión, clínico y supervisor) dan los casos de uso. Las decisiones del diagrama de actividad dan las reglas, y las reglas, los requisitos. Con LSP separé la lectura de la escritura y modelé el paciente sin documento como un identificador nulo. Esos contratos se volvieron los puertos del Dominio en una arquitectura de cuatro capas con MVC y Repository: dos adaptadores, PDO e InMemory, que cumplen la misma suite de contrato. El servidor se expone con un contrato OpenAPI que las pruebas comparan con el código. Como cambio práctico agregué la identidad protegida, para una paciente con orden de protección: no se la encuentra por nombre, su demografía se oculta por defecto, no se edita sin permiso, y los duplicados se detectan sin revelar quién es. Lo hice con pruebas primero, de 6 en rojo a 0. Fue una política nueva, y el controlador, las rutas y las vistas no cambiaron. Hoy hay 80 pruebas, 0 fallan, y las 22 mutaciones se detectan.»

## 2. Tiempos sugeridos (≈ 12 minutos + preguntas)

| Diapositiva | Tiempo | Qué decir sí o sí |
|:-:|:-:|---|
| 1 · Portada | 0:30 | El proceso y los tres números |
| 2 · Actores y UML | 1:30 | Cada actor tiene un objetivo distinto sobre la misma ficha; los identificadores CU/D/E/S se comparten entre vistas |
| 3 · Requisitos y LSP | 1:30 | Una decisión del diagrama → una regla → un RF con su criterio de aceptación; el antes/después de LSP |
| 4 · Capas y Repository | 2:00 | La regla de dependencias está **probada** (ARQ-01), no solo dibujada; una suite de contrato para dos adaptadores |
| 5 · Contrato y frontera | 1:30 | El contrato no puede divergir (API-01..03); no dividir es una decisión **medida** |
| 6 · Diagrama trazable | 1:00 | Recorrer la cadena de izquierda a derecha y bajar al cambio |
| 7 · Cambio práctico | 3:00 | Los seis aspectos, la fuga que encontró PROT-06 y el rojo → verde |
| 8 · Cierre | 1:00 | Qué no cambió; ofrecer la demostración en vivo |

## 3. Cómo responder si piden un cambio práctico distinto

El cambio defendido es la identidad protegida, pero el docente puede pedir otro en vivo. Seguir siempre el mismo método, que es el que muestra el [registro del cambio](registro-cambio-practico.md):

1. **Ubicarlo en la cadena:** ¿qué actor lo pide?, ¿qué caso de uso extiende?, ¿qué regla nueva aparece (R-12…)?
2. **Escribir el criterio de aceptación** antes de tocar código.
3. **Análisis de impacto por capa:** ¿es una regla (Dominio, política nueva), un dato (modelo, puerto y los dos adaptadores), un permiso (Aplicación) o solo formato (Formulario)?
4. **Prueba en rojo**, implementación y **verde**; después, una mutación que demuestre que la prueba sirve.
5. **Contrato:** si cambia la API, subir la versión. Menor si es compatible, mayor si rompe.

Ejemplos rápidos:

| Cambio pedido | Dónde va |
|---|---|
| «Fusionar dos fichas duplicadas» | Caso de uso nuevo + política en el Dominio (solo supervisor, con motivo); un estado nuevo («fusionada») para la ficha absorbida, que deja de aparecer en las búsquedas; evento al MPI |
| «Buscar también por teléfono» | `CriterioBusqueda` + los dos adaptadores + CON-* nuevo; el teléfono se reduce para los protegidos |
| «Registrar pacientes extranjeros con pasaporte» | Un nuevo tipo de `Identificador` que cumpla el contrato de LSP; `PoliticaDuplicados` no cambia |
| «Que el clínico pueda editar el teléfono» | Solo permisos (`config.php`) + una prueba; ni el Dominio ni la API cambian |

## 4. Preguntas probables y respuestas

**¿Por qué una política nueva y no un `if` en `ActualizarPaciente`?**
Por el principio abierto/cerrado. La regla de confidencialidad tiene su propia razón para cambiar (la ley, la institución), distinta de la de edición. En una clase propia se prueba sola (DOM-09) y `PoliticaEdicion` quedó intacta.

**¿Qué significa «fail-closed» aquí?**
Que el valor por defecto es el seguro. `resumen()` y `detalle()` reducen salvo que se les pase explícitamente el permiso, y `CriterioBusqueda` excluye a los protegidos salvo que se pida incluirlos. Si un caso de uso nuevo se olvida de pasar el permiso, el resultado es mostrar de menos, no de más.

**¿Por qué por código o documento sí aparece?**
Porque hay que atenderla. Quien ya tiene su código o su documento completo la tiene identificada; lo que se impide es «pescarla» buscando por nombre. Aun así ve los datos reducidos.

**¿Por qué el campo ausente no quita la protección?**
Porque un cliente viejo (de la semana 5) no conoce el campo. Si su `PUT` lo quitara por omisión, desprotegería a la paciente sin que nadie lo decidiera. Por eso la protección solo cambia si se envía el campo, con permiso y motivo.

**¿Por qué «RESERVADO» y no ocultar el 409?**
Ocultarlo permitiría registrarla dos veces, y eso rompe R-02. El 409 sigue avisando que la persona ya existe; lo que no dice es quién es.

**¿Qué encontró PROT-06?**
Una fuga real: el 409 por documento duplicado ya ocultaba el código, pero repetía el DPI enmascarado. PROT-06 recorre 10 respuestas y el registro de acceso buscando cualquier dato de la paciente. Se corrigió en `PoliticaDuplicados`.

**¿Dónde está el principio SOLID que defiendes?**
LSP, desde la semana 2: `ConsultaPacientes ⊂ RepositorioPacientes`, de modo que una réplica de solo lectura no promete escribir; y el identificador nulo para la identidad provisional. En esta semana también aparecen OCP (una política nueva sin tocar las existentes) y DIP (el filtro va en el puerto, y los dos adaptadores lo cumplen).

**¿Por qué no se probó contra PostgreSQL?**
No tenía las credenciales. La suite de contrato es la misma para PDO, y PER-02 verifica la sentencia y los parámetros que envía PDO. Las 2 pruebas omitidas quedan listas para ejecutarse con `PACIENTES_DB_DSN`.

## 5. Demostración en vivo

Desde `docs/modulos/pacientes/micro-his-pacientes/`:

1. `php tests/ejecutar.php` → 80, 78 pasan, 0 fallan, 2 omitidas.
2. `php bin/demo-proteccion.php` → los 8 pasos: admisión no puede proteger (403), el supervisor sí (201), el clínico no la encuentra por nombre, la encuentra reducida por código, el registro duplicado da 409 `RESERVADO`, admisión no puede editarla y el supervisor ve todo.
3. Mutación a mano: en `Paciente::seMuestraReducidoPara()`, cambiar el retorno por `false` y volver a ejecutar las pruebas. Fallan DOM-09, PROT-02, 03, 04 y 06. Deshacer el cambio.
4. Abrir [matriz-decision-evidencia.md](matriz-decision-evidencia.md), elegir una decisión que pida el docente y seguir sus tres columnas.
