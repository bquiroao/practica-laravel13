# Guía de defensa oral — Semana 4

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Pacientes · **Documento:** [semana-04-capas-mvc-repositorio.md](semana-04-capas-mvc-repositorio.md) · **Código:** [micro-his-pacientes](../micro-his-pacientes/README.md)

## 1. Resumen en 60 segundos

> «Seguí con el mismo Micro-HIS. Reorganicé la entrada en MVC: el controlador solo coordina, el formulario valida el formato y las vistas son el único lugar con códigos HTTP. El Modelo son la Aplicación y el Dominio, y no cambié ni un archivo de esas dos capas. Las interfaces Repository están en el Dominio, porque es quien las necesita, y cada una tiene dos adaptadores: PDO para PostgreSQL e InMemory. Los dos pasan la misma suite de contrato, así que son intercambiables: con una variable de entorno la API corre sin base de datos. Las reglas del controlador las verifican pruebas que leen el código, y comprobé con 12 defectos inyectados que las pruebas los detectan todos. Por último, analicé cómo encaja en el PostgreSQL federado del HIS: Pacientes escribe en la base del hospital y avisa al índice maestro con un evento; la consecuencia es que la unicidad del DPI entre hospitales pasa a ser eventual.»

## 2. Orden sugerido de la exposición

1. **Qué cambió y qué no** (§2): Dominio y Aplicación intactos.
2. **Capas y responsabilidades** (diagrama de capas).
3. **MVC:** abrir `PacientesControlador.php` (unas pocas líneas por método) y mostrar ARQ-07.
4. **Repository:** el límite de la interfaz, los dos adaptadores y la suite de contrato.
5. **Demo:** `php bin/demo.php`.
6. **Repositorio compartido:** diagrama y la consecuencia de la unicidad eventual.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Interfaces Repository en `Dominio/Puertos` | Quien necesita el servicio define el contrato (inversión de dependencias). Si estuvieran en Persistencia, el Dominio dependería de la base. |
| `Transaccion` en `Aplicacion/Puertos` | Decidir qué va junto en una transacción es orquestación del caso de uso, no una regla del paciente. |
| Formulario (400) separado del Dominio (422) | El formulario solo sabe de formato. Si validara reglas, las duplicaría y el controlador acabaría decidiendo negocio. |
| Las vistas son el único lugar con HTTP | Cambiar un código de estado o el formato de error es cambiar un archivo. ARQ-08 impide que reaparezca en otro lado. |
| InMemory como adaptador **de producción** | No es un doble de prueba: sirve para demos, para desarrollar sin base y como referencia ejecutable del contrato. |
| Dobles = decoradores `*ConFallas` | Reutilizan el adaptador real y solo agregan la falla, así la lógica de memoria no se duplica en `tests/`. |
| `ConjuntoAdaptadores` | Garantiza que repositorio y bitácora compartan la conexión: sin eso, la transacción no los abarcaría. |
| Pacientes no escribe en CENTRAL | Una transacción no puede abarcar dos bases. El outbox hace atómicos el cambio y el aviso, y la sincronización actualiza el MPI. |

## 4. Preguntas probables y respuestas

**¿Qué es MVC y dónde está cada letra aquí?**
- **M** = Aplicación + Dominio: casos de uso y reglas.
- **V** = `PacienteVista` y `ErrorVista`: formato de la respuesta.
- **C** = `PacientesControlador`: coordina.

El `Kernel` es el *Front Controller* que elige qué método del controlador llamar.

**¿Cómo demuestras que el controlador no tiene SQL ni reglas?**
ARQ-07 revisa por reflexión que el constructor reciba solo casos de uso. Además lee el código y falla si encuentra SQL, `new`, códigos de negocio como `'PACIENTE_EXISTENTE'` o `'DPI'`, o armado de JSON. La mutación M10 mete una decisión de negocio en el controlador y ARQ-07 la detecta.

**¿Qué es el patrón Repository?**
Una interfaz con forma de **colección de objetos del dominio** (`porCodigo`, `registrar`, `actualizar`) que oculta cómo se guardan. El caso de uso trabaja con objetos `Paciente`, no con filas ni SQL.

**¿Cómo sabes que el adaptador InMemory se comporta igual que el PDO?**
Por la suite de contrato: la misma función con 6 verificaciones se ejecuta contra los dos. La mutación M12 (InMemory sin documento único) **solo** la detecta el contrato. Eso muestra por qué hace falta: la política del dominio tapa el error en las pruebas normales.

**¿Qué es un repositorio de datos compartido?**
Un estilo en el que varios componentes se comunican a través de un almacén común. En el HIS es el PostgreSQL federado:
- CENTRAL tiene el índice maestro de pacientes;
- cada HOSPITAL tiene sus `local_patients`, a los que se refieren admisión, citas y expediente.

**¿Qué cambia al integrarse con ese repositorio?**
Solo los adaptadores y la composición. La gran diferencia es que el DPI único ya no se garantiza en la misma transacción, porque `local_patients` y `mpi_identifiers` están en bases distintas. Dos hospitales podrían registrar el mismo DPI a la vez, y eso se resuelve como candidato a fusión en el MPI.

**¿Por qué no hiciste las migraciones en el proyecto principal?**
Afectan un esquema que usa todo el equipo, y aquí no pude validarlas: no están instaladas las dependencias de Laravel ni tengo la contraseña de la base. Quedaron documentadas como el primer paso de la integración.

## 5. Recorrido de demostración

1. `php tests/ejecutar.php` → 50 pasan, 2 omitidas.
2. `php bin/demo.php` → los 9 pasos de §9.3 del documento.
3. Abrir [`PacientesControlador.php`](../micro-his-pacientes/src/Presentacion/Http/Controladores/PacientesControlador.php) y [`ErrorVista.php`](../micro-his-pacientes/src/Presentacion/Http/Vistas/ErrorVista.php).
4. Abrir [`ContratoRepositoriosPrueba.php`](../micro-his-pacientes/tests/Contratos/ContratoRepositoriosPrueba.php) y mostrar que la misma función se llama con `FabricaEnMemoria` y con `FabricaPdo`.
5. Mostrar el diagrama del repositorio compartido.

## 6. Cambios que podrían pedirte en vivo

| Cambio solicitado | Cómo hacerlo |
|---|---|
| Demostrar que el controlador no puede tener reglas | Agregar en `registrar()` un `if ($comando->datos['tipo_documento'] === 'DPI') {...}` y ejecutar: falla ARQ-07. |
| Agregar un tercer adaptador (por ejemplo, archivo JSON) | Una clase por puerto en `Persistencia/Archivo/`, una `FabricaArchivo` y un caso en `crearAdaptadores()`. Para validarlo: `contratoRepositorios('CON-ARC', fn () => [FabricaArchivo::crear(...), $contador])`. |
| Cambiar el código HTTP de un conflicto | Solo `ErrorVista::POR_CATEGORIA`. Ningún otro archivo. |
| Agregar «inactivar paciente» | Caso de uso en Aplicación, un método `inactivar()` en el controlador (formulario → caso de uso → vista) y una línea en `Rutas::definir()`. |
| Mostrar la API sin base | `$env:PACIENTES_PERSISTENCIA = "memoria"` y `php -S 127.0.0.1:8081 -t public`. |

Para regenerar los diagramas:

```bash
java -jar plantuml.jar -charset UTF-8 -tpng docs/modulos/pacientes/semana-04/diagramas/*.puml
```
