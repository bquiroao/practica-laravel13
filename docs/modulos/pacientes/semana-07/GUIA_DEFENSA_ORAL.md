# Guía de defensa oral — Semana 7

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** ASII-03 Pacientes · **Documento:** [semana-07-componentes-refactor.md](semana-07-componentes-refactor.md) · **Contratos:** [contratos-entrada-salida.md](contratos-entrada-salida.md)

## 1. Resumen en 60 segundos

> «Diseñé los componentes de Pacientes en cuatro capas, en el backend que ya está implementado y en un frontend Vue que queda como diseño. Los dos lados se hablan solo por el contrato OpenAPI, y el frontend no aplica ninguna regla de la ficha: muestra lo que el backend decide. Para elegir qué refactorizar no adiviné: escribí un script que mide, por clase, de cuántas clases depende y cuántas de las seis preocupaciones de la tarea mezcla. El punto más acoplado eran los casos de uso de escritura, `ActualizarPaciente` y `RegistrarPaciente`: cruzaban cinco y cuatro preocupaciones, traducían permisos a booleanos uno por uno y decidían el orden de las reglas, del que depende no revelar a un paciente protegido. Moví ese cruce a un servicio de Dominio, `RevisionDeFicha`, y los permisos a un objeto `Capacidades`. Ahora cada caso de uso mezcla una sola preocupación, y el orden vive en un solo lugar. Antes de tocar nada escribí tres pruebas de caracterización y las corrí sobre el código viejo. Después del refactor pasa todo igual: 81 pruebas y 24 mutaciones detectadas, sin funcionalidad nueva.»

## 2. Orden sugerido de la exposición

1. **Diagrama de componentes** (§2): las cuatro capas en los dos lados y el contrato en el medio.
2. **Contratos** (un ejemplo por capa): `ActualizarPaciente` → `Resultado`; `RevisionDeFicha::paraEdicion()` y su orden de errores; `useGuardarPaciente` con `If-Match`.
3. **Medición** (§4): ejecutar `php bin/acoplamiento.php` y explicar por qué el repositorio PDO, que mezcla las 6 preocupaciones, **no** es el problema.
4. **Antes / después** (§5–§6): los dos diagramas y el código de `paraEdicion()`.
5. **Prueba de que no se amplió** (§9): REF-01..03 sobre el código viejo; la misma salida de la demo de la semana 6.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Elegir los casos de uso y no `PdoRepositorioPacientes` (P = 6) | Un adaptador debe conocer todos los campos para guardarlos; ese acoplamiento es esperado y está aislado detrás de un puerto. Un caso de uso de Aplicación que decide reglas de Dominio, no. |
| El orden de las reglas va al Dominio | «No se edita lo que no se ve» exige verificar la visibilidad **antes** de validar; si no, un 422 revelaría los campos de un protegido. Eso es regla de negocio, no orquestación. |
| `Capacidades` en vez de booleanos | Un objeto con nombres (`verProtegidos`, `proteger`…) no se confunde como `verificarRegistro($p, true, false, $m, true)`. Por defecto vale «nada» (fail-closed). |
| Las tres políticas no se tocaron | Reduce el riesgo: el refactor solo las compone. Sus pruebas de las semanas 3 a 6 siguen verdes sin cambios. |
| Pruebas de caracterización | Para afirmar «mismo comportamiento» hay que fijarlo antes. Las tres se corrieron sobre el código viejo. |
| Frontend sin reglas | Si la UI decidiera qué ocultar, un usuario podría saltarse la regla. El backend siempre decide; la UI muestra `null` como «Reservado». |

## 4. Preguntas probables y respuestas

**¿Qué es Ce y qué es Ca?**
Ce (acoplamiento eferente) es cuántas clases del proyecto usa una clase: cuantas más usa, más razones tiene para romperse. Ca (aferente) es cuántas la usan a ella: cuantas más, más cuidado hay que tener al cambiarla. Son métricas de Robert C. Martin.

**Si `RevisionDeFicha` también mezcla 5 preocupaciones, ¿qué ganaste?**
Que el cruce, que es real en el negocio, tiene ahora **un dueño** en la capa correcta: el Dominio. Antes estaba repetido en dos clases de Aplicación que además manejaban transacciones, idempotencia y permisos. `RevisionDeFicha` tiene 67 líneas y no sabe nada de transacciones ni de nombres de permisos.

**¿Por qué el Ce bajó tan poco (16 → 14)?**
Porque el caso de uso todavía necesita sus puertos (repositorio, auditoría, transacción, reloj) y sus comandos. Lo que bajó fuerte es lo que importaba: las preocupaciones (5 → 1) y las decisiones de Dominio que tomaba.

**¿Por qué dice «conceptualmente» la tarea y tú lo implementaste?**
Porque un refactor implementado se puede medir y probar. El diseño conceptual está en los diagramas y en la tabla de responsabilidades; la implementación demuestra que es viable y que no cambia el comportamiento.

**¿Qué es una prueba de caracterización?**
Una prueba que no dice cómo **debería** comportarse el código, sino cómo se comporta **hoy**. Se escribe antes de un refactor para detectar cualquier cambio de comportamiento (Feathers, 2004).

**¿Cuál sería el siguiente refactor?**
Pasar `Capacidades` también a `Paciente::resumen()`, a `detalle()` y a las políticas, para eliminar los booleanos que quedan. No lo hice ahora para no tocar firmas que cubren las pruebas de las semanas 3 a 6.

**¿Por qué la búsqueda no va en la URL del frontend?**
Porque `?nombre=Ana López` quedaría en el historial del navegador, en los marcadores y en cualquier enlace copiado. Es una fuga de datos personales (R-04).

## 5. Recorrido de demostración

Desde `docs/modulos/pacientes/micro-his-pacientes/`:

1. `php bin/acoplamiento.php` → `ActualizarPaciente` y `RegistrarPaciente` con P = 1; `RevisionDeFicha` con P = 5.
2. `php tests/ejecutar.php` → 83 pruebas: 81 pasan, 2 omitidas. Mostrar REF-01..03.
3. Abrir [`refactor/antes/ActualizarPaciente.php`](refactor/antes/ActualizarPaciente.php) junto a `src/Aplicacion/CasosDeUso/ActualizarPaciente.php`.
4. Mutación a mano: en `RevisionDeFicha::paraEdicion()`, mover la línea de `exigirVisibilidad` debajo de `Paciente::desdeDatos(...)` y volver a ejecutar las pruebas. Falla REF-01, porque ahora admisión recibe un 422 con los campos de una protegida. Deshacer el cambio.

## 6. Cambios que podrían pedirte en vivo

| Cambio solicitado | Cómo hacerlo |
|---|---|
| «Agrega una regla nueva al editar» (por ejemplo, que la fecha de nacimiento no cambie más de 1 año) | Un método en una política (o una política nueva) y **una** línea en `RevisionDeFicha::paraEdicion()`. Los casos de uso no cambian. |
| «Un permiso nuevo que afecte a la ficha» | Una propiedad en `Capacidades` y una línea en `ControlAcceso::capacidades()`. |
| «Muestra que el refactor no cambió el comportamiento» | Ejecutar las pruebas y la demo de la semana 6; comparar con [`caracterizacion-sobre-codigo-anterior.txt`](evidencia/caracterizacion-sobre-codigo-anterior.txt). |
