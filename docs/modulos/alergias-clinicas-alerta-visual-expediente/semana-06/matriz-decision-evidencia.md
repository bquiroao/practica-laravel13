# Matriz decisión → evidencia (primer parcial)

| ID | Decisión | Por qué | Evidencia verificable |
|---|---|---|---|
| DEC-01 | El reconocimiento es por usuario y por huella | Que un reconocimiento viejo no oculte una alergia nueva | T-15, T-16, T-42 |
| DEC-02 | `SIN_REGISTRO` en gris con «no verificado» | La tabla no puede expresar «sin alergias conocidas» | T-06, vista `alerta.php`, CA-07 |
| DEC-03 | `unknown` = ALTA | Ante la duda, más visible | T-05, `ReglaPorSeveridad` |
| DEC-04 | Interfaces Repository en el Dominio | Probar sin base e invertir dependencias | T-01 a T-09 con dobles; comandos de dependencias (semana 3) |
| DEC-05 | Registrar devuelve la alerta recalculada | Quien registra ve el nivel nuevo | T-01, T-39 |
| DEC-06 | Duplicado revisado en la Aplicación y en el índice | Mensaje claro y protección ante concurrencia | T-02, T-11, T-41 |
| DEC-07 | Usuario fijo en la web hasta la semana 5 | Demo de MVC antes de los tokens | `bootstrap.php` (`ALERGIAS_USUARIO_DEMO`) |
| DEC-08 | InMemory es un adaptador del mismo contrato | Cambiar de persistencia sin cambiar la Aplicación | T-25 a T-34 |
| DEC-09 | Tenant tomado del token, nunca del encabezado | Evitar que un cliente elija el hospital | T-38 |
| DEC-10 | Registro de acceso sin datos clínicos | Privacidad (RNF-06) | T-43 |
| DEC-11 | No separar como microservicio | ≈ 10× de latencia (p95 7.96 → 82.97 ms) y riesgo de expediente sin alerta | `scripts/medir-frontera.php`, README de la semana 5 |
| DEC-12 | OCP en el calculador | Las reglas de nivel son lo que más cambia | `semana-02/ejemplo-ocp/verificar.php` 10/10 |
| DEC-13 | Único escritor de `allergies` | Una sola fuente de verdad para alergias | Frontera de la semana 1 y repositorio compartido de la semana 4 |
