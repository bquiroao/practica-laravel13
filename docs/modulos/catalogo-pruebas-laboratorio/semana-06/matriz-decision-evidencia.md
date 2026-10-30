# Matriz decisión → evidencia

| # | Decisión de arquitectura | Por qué | Requisitos | Evidencia |
|---|---|---|---|---|
| D-A01 | Una prueba se versiona: un cambio crea otra versión y cierra la anterior el día previo | Las órdenes ya hechas deben conservar los requisitos con que se pidieron | RF-04, RN-05 | S1 actividad y secuencia (S-09 a S-16); S3 `RegistrarPrueba`; T-02, T-03 |
| D-A02 | La vigencia se consulta siempre a una fecha (por defecto hoy) | Médicos y Órdenes necesitan saber qué se puede pedir ese día | RF-05, RF-06, RN-06 | `Vigencia::incluye`; `buscarVigentes`; T-06, T-07, T-16 |
| D-A03 | Responsabilidad única: validar, versionar y guardar en piezas separadas | Cada regla cambia por su propio motivo y se prueba sola | RNF-05 | S2 `solid-srp/` con `verificar.php` (4 de 4 escenarios iguales) |
| D-A04 | Cuatro capas con dependencias hacia Domain | El dominio no depende de PDO ni de HTTP | RNF-05 | S3 `dependencias-capas.png`, C4 de componentes |
| D-A05 | Interfaz `LabTestRepository` en Domain, con adaptadores PDO e InMemory | Probar casos de uso sin base y cambiar la persistencia sin tocar reglas | RNF-05 | S4 `capas-mvc-repository.png`; T-09 (mismo contrato en ambos) |
| D-A06 | Guardar versión y cierre en una transacción | No dejar dos versiones abiertas ni registros parciales | RNF-01, RN-07 | `PdoLabTestRepository::guardarNuevaVersion`; T-05, T-08 |
| D-A07 | Controlador sin SQL ni reglas | El mismo caso de uso atiende consola, web y API | RNF-05 | S4 `LabTestController`; T-12 |
| D-A08 | El tenant sale del token y cada rol tiene permisos fijos | Un hospital no ve ni modifica el catálogo de otro | RF-07, RNF-03, RN-01 | S5 `Autorizacion`; contrato API; T-14 |
| D-A09 | Errores con un formato único y `request_id` | Que el cliente sepa qué corregir y que soporte pueda rastrear | RNF-04, RNF-06 | S5 contrato (sección 4); T-15 |
| D-A10 | No separar el catálogo como microservicio todavía | No hay gatillo medible cumplido | — | S5 `frontera-microservicio.md` (gatillos y etapas) |
