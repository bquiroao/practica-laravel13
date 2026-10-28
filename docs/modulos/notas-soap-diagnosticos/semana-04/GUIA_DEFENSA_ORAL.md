# Guía de defensa oral — Semana 4

**Estudiante:** Joshua Eduardo García Reyes · **Módulo:** ASII-11 Notas SOAP y diagnósticos · **Documento:** [semana-04-mvc-repository.md](semana-04-mvc-repository.md) · **Código:** [`../micro-his-notas-soap/`](../micro-his-notas-soap/)

## 1. Resumen en 60 segundos

> «Seguí con el mismo micro-HIS de la semana 3. Primero ordené la entrada con MVC. El controlador solo arma el comando, llama al caso de uso y le pasa el resultado a una vista. Hay dos vistas, JSON y HTML, y el mismo controlador sirve a las dos. Después dejé las interfaces Repository en el Dominio, porque las usan los casos de uso y hablan con objetos del dominio. Cada interfaz tiene dos adaptadores, uno con PDO y otro en memoria. Unas pruebas de contrato corren lo mismo contra los dos para comprobar que se comportan igual. Una prueba revisa el código del controlador y falla si tiene SQL o reglas. Por último analicé el repositorio compartido, que en nuestro HIS es la base CENTRAL. La nota se queda en el hospital, y al firmarla se guarda un evento con solo identificadores en una bandeja de salida, en la misma transacción. Un publicador lo manda después a la bandeja de entrada de CENTRAL. Eso lo agregué con un decorador del Repository, sin tocar los casos de uso.»

## 2. Orden sugerido de la exposición

1. **Qué cambió** (tabla de la sección 2 del documento).
2. **MVC:** diagrama `semana-04-mvc-entrada` y el código del controlador (tres líneas).
3. **Repository:** diagrama de clases; por qué la interfaz está en el Dominio.
4. **Dos adaptadores:** tabla de puertos y pruebas REP. Contar lo de SQLSTATE 23505 contra 23000.
5. **Capas y objetos reutilizables:** diagrama y tabla de la sección 7.
6. **Repositorio compartido:** diagrama de integración y la secuencia del outbox.
7. **Demostración** (sección 5 de esta guía).

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| La interfaz Repository va en el Dominio | La usan los casos de uso y recibe `NotaSoap`; si estuviera en Persistencia, el dominio dependería de la base |
| La validación de formato va en el formulario de entrada (400) y la de negocio en el Dominio (422) | Un arreglo donde va texto es un JSON mal armado; un plan vacío es una nota clínica incompleta. Son errores distintos y viven en capas distintas |
| Dos vistas para el mismo controlador | Demuestra la separación del MVC: cambiar cómo se muestra no toca ni al controlador ni al modelo |
| Pruebas de contrato | Implementar la misma interfaz no garantiza el mismo comportamiento; el contrato lo comprueba |
| El adaptador en memoria imita la llave foránea | Si no, un diagnóstico sin nota pasaría en memoria y fallaría en PostgreSQL: los adaptadores no serían intercambiables |
| Outbox en lugar de escribir directo en CENTRAL | El hospital sigue trabajando si CENTRAL se cae; no se mezcla una transacción local con una llamada remota |
| El evento lleva solo identificadores | Es lo que pide `payload_minimal` en CENTRAL; el contenido clínico no sale del hospital |
| Decorador para el outbox | Se agregó la integración sin modificar casos de uso ni adaptadores (abierto a extensión, cerrado a modificación) |

## 4. Preguntas probables y respuestas

**¿Dónde está el "modelo" del MVC si no hay pantallas?**
El modelo son los casos de uso y las entidades: `RegistrarNotaConDiagnostico`, `NotaSoap`, `Diagnostico`. La vista es la representación de la respuesta (JSON o HTML) y el controlador conecta las dos cosas.

**¿Qué es el patrón Repository?**
Un objeto que hace ver la base de datos como una colección de objetos del dominio. El caso de uso le dice "guarda esta nota" o "dame la nota con este id" y no sabe si detrás hay PostgreSQL, SQLite o un arreglo.

**¿Por qué no basta con que las dos clases implementen la interfaz?**
Porque la interfaz solo obliga a tener los mismos métodos, no a comportarse igual. Por ejemplo, las dos deben negar una segunda firma. Eso lo revisan REP-01 a REP-06 contra los dos adaptadores.

**¿Cómo sabe que el controlador no tiene SQL ni reglas?**
Por la prueba ARQ-09. Lee el archivo del controlador y falla si encuentra SQL, PDO, clases del Dominio, funciones de validación o un `if`.

**¿Para qué sirve el adaptador en memoria si en producción se usa PostgreSQL?**
Para probar rápido y sin base, y para la demostración. Además demuestra que los casos de uso de verdad no dependen de la base: INT-08 corre el mismo flujo con los dos adaptadores y las respuestas son idénticas.

**¿Qué es el repositorio de datos compartido en este proyecto?**
La base CENTRAL del HIS. Tiene el índice maestro de pacientes, los usuarios, los consentimientos y permisos entre hospitales, y la bandeja de entrada de sincronización. Las notas no se guardan ahí.

**¿Qué es el patrón outbox?**
Guardar el evento en una tabla del mismo hospital (`outbox_events`), dentro de la misma transacción que el cambio. Después, un proceso aparte lo envía. Así no puede pasar que se guarde la firma y se pierda el evento, ni al revés.

**¿Qué pasa si el evento se envía dos veces?**
CENTRAL tiene `UNIQUE (source_node_id, event_id)` en `central_inbox_messages`. El segundo envío se ignora.

**¿Cómo ve otro hospital la nota?**
CENTRAL revisa si hay un permiso vigente en `cross_hospital_access_grants`, por consentimiento o por "break-glass" con justificación. Si lo hay, la lectura se hace contra el hospital que tiene la nota y queda auditada.

**¿Qué es un decorador?**
Una clase que implementa la misma interfaz que otra, la envuelve y le agrega algo. `RepositorioNotasConEventos` llama al repositorio real para firmar y además deja el evento en la bandeja.

## 5. Recorrido de demostración

```bash
cd docs/modulos/notas-soap-diagnosticos/micro-his-notas-soap
php tests/ejecutar.php
php bin/demo-en-memoria.php
```

1. Mostrar en las pruebas el grupo **Contrato del Repository**: las mismas seis pruebas para `memoria` y `pdo-sqlite`.
2. Correr la demostración: registrar, rechazo del Dr. López, firmar, segunda firma (409), vista HTML y, al final, el evento en la bandeja de salida.
3. Romper el controlador a propósito. En `src/Presentacion/Controladores/NotasSoapController.php`, dentro de `registrar`, agregar al inicio `if ($hospital === '') { return $this->vistas->para($s)->error(400, 'x', 'x'); }`. Correr las pruebas: falla **ARQ-09** y dice que hay un `if`. Quitar la línea.
4. Abrir `semana-04/evidencia/nota-vista-html.png` para mostrar la vista HTML.

## 6. Cambios que podrían pedirte en vivo

| Cambio pedido | Qué modificar |
|---|---|
| «Agregue un tercer adaptador, por ejemplo en archivo JSON» | Crear `Persistencia/Archivo/RepositorioNotasArchivo` que implemente `RepositorioNotas`, agregarlo al arreglo `$adaptadores` de `RepositorioContratoPrueba.php` y conectarlo en `contenedor.php`. Los casos de uso no se tocan |
| «Que la vista HTML muestre la fecha en formato dd/mm/aaaa» | Solo `Vistas/plantillas/nota.php` (o `VistaHtml`). El controlador y el modelo no cambian |
| «Mueva una regla al controlador» (pregunta trampa) | Explicar que ARQ-09 lo impediría y que la regla debe ir en el Dominio |
| «Publique también cuando se registra el borrador» | En `RepositorioNotasConEventos::guardar()`, agregar `EventoClinico` de tipo nuevo. Discutir por qué no conviene: el borrador puede cambiar (RC-02) |
| «Que el evento incluya el código CIE-10» | Agregar el campo en `EventoClinico::notaFirmada()` y la prueba DOM-09. Discutir si un diagnóstico es dato sensible para `payload_minimal` |
| «Muestre dónde se elige PDO o memoria» | `bootstrap/contenedor.php`: `crearKernel()` contra `crearKernelEnMemoria()` |
