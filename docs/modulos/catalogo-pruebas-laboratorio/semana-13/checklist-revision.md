# Checklist de la revisión técnica formal (14 ítems)

| ID | Foco | Pregunta de verificación | Dónde mirar | Sí / No / N/A | RT-NN |
|---|---|---|---|---|---|
| C-01 | Dominio | ¿`LabTest::validar` devuelve un error por **cada** campo inválido y no solo el primero (RN-02 a RN-04)? | `src/Domain/LabTest.php`, T-04 | | |
| C-02 | Dominio | ¿Se rechaza una versión cuya fecha desde es igual o anterior a la vigente, y se cierra el día previo cuando empieza después (RN-05)? | `RegistrarPrueba.php`, T-02, T-03 | | |
| C-03 | Dominio | ¿«Vigente a una fecha» usa los límites inclusivos `desde ≤ fecha ≤ hasta` igual en dominio, PDO e InMemory (RN-06)? | `Vigencia::incluye`, `buscarVigentes`, T-09 | | |
| C-04 | Dominio | ¿Las listas cerradas (sección, tipo de muestra, conservación) están definidas en un solo lugar? | `LabTest::SECCIONES`, `RequisitoMuestra::TIPOS` | | |
| C-05 | Integridad | ¿Guardar la versión nueva y cerrar la anterior ocurre en una sola transacción con rollback ante cualquier falla (RN-07)? | `PdoLabTestRepository::guardarNuevaVersion`, T-08 | | |
| C-06 | Integridad | ¿Las restricciones de `lab_test` (CHECK, UNIQUE) coinciden con las reglas del dominio, sin valores que el dominio acepte y la base rechace? | `sql/esquema.sql` frente a `RequisitoMuestra::errores` | | |
| C-07 | Integridad | ¿Ninguna operación borra versiones ni modifica una versión salvo `valid_to`? | Repositorios | | |
| C-08 | Contrato API | ¿Cada estado HTTP del contrato (200, 201, 400, 401, 403, 404, 409, 422, 500) tiene una ruta en el código y una prueba? | `contrato-api.md`, `ApiLabTestController`, T-13 a T-16 | | |
| C-09 | Contrato API | ¿Los nombres y tipos de los campos JSON coinciden exactamente con el contrato? | `ApiLabTestController::aJson` | | |
| C-10 | Permisos | ¿Solo el rol con `lab.catalogo.gestionar` puede registrar, y el permiso se verifica en el servidor? | `Autorizacion.php`, T-14 | | |
| C-11 | Tenant | ¿Todas las consultas y escrituras filtran por `tenant_id`, y el tenant sale del token y no de la petición? | `PdoLabTestRepository.php`, `Autorizacion.php` | | |
| C-12 | Seguridad | ¿Todo el SQL usa `prepare()` + `execute()` con parámetros, sin concatenar datos del usuario? | `src/Persistence/`, `bootstrap.php` | | |
| C-13 | Dependencias | ¿Domain no importa nada de Application, Persistence, Presentation ni PDO? | `src/Domain/*` (líneas `use`) | | |
| C-14 | Dependencias | ¿El módulo solo depende de lo necesario (RBAC para permisos y la base HOSPITAL) y no lee ni escribe tablas de otros módulos? | `semana-04/README.md` sección 4, `sql/esquema.sql` | | |
