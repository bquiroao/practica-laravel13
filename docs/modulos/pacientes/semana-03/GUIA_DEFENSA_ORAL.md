# Guía de defensa oral — Semana 3

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Pacientes · **Documento:** [semana-03-arquitectura-micro-his.md](semana-03-arquitectura-micro-his.md) · **Código:** [micro-his-pacientes](../micro-his-pacientes/README.md)

## 1. Resumen en 60 segundos

> «Inicié el Micro-HIS Pacientes en PHP puro, sin framework, con cuatro capas. Presentación recibe HTTP y lo traduce. Aplicación orquesta cada caso de uso: registrar, buscar, consultar y actualizar. Dominio tiene las reglas: validación, duplicados, quién puede editar y cuándo hace falta motivo. Persistencia habla con PostgreSQL por PDO, solo con sentencias preparadas. Las dependencias apuntan hacia el Dominio, y no solo lo dibujé en C4 y UML: cinco pruebas leen el código y fallan si una capa se salta la regla. El DPI nunca se guarda en claro: se busca por un hash y se guarda cifrado. La actualización compara la versión en el mismo UPDATE, así dos personas no se pisan. Tengo 37 pruebas en verde que cubren camino feliz, reglas de dominio y errores de persistencia, y comprobé con 8 defectos inyectados que las pruebas los detectan todos.»

## 2. Orden sugerido de la exposición

1. **C4 nivel 1 → 2 → 3:** del sistema en su entorno a los componentes de cada capa.
2. **Paquetes y regla de dependencias:** el sentido de las flechas y por qué Persistencia apunta al Dominio.
3. **Vista dinámica:** el `PUT` con versión vieja que termina en 409.
4. **Código:** abrir `PdoRepositorioPacientes.php` (SQL preparado) y `ActualizarPaciente.php` (orquestación).
5. **Pruebas en vivo:** `php tests/ejecutar.php` y, si hay tiempo, `php tests/mutaciones.php`.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Presentación no ve el Dominio | Los comandos llevan tipos primitivos y el resultado no tiene códigos HTTP. Así se puede cambiar la API (por ejemplo, a Laravel) sin tocar las reglas. ARQ-01 lo verifica. |
| El Dominio no conoce HTTP | Sus errores dicen la *categoría* (conflicto, inválido...) y `TraductorResultado` elige el código. Una regla de negocio no debería saber qué es un 409. |
| Puertos en el Dominio | Inversión de dependencias: el Dominio dice «necesito un repositorio» y PDO lo implementa. Por eso las pruebas usan dobles en memoria sin cambiar nada. |
| SQL como constantes literales | Sin concatenación es imposible meter texto del usuario en el SQL. ARQ-03 lo verifica con el analizador de tokens de PHP. |
| `ATTR_EMULATE_PREPARES = false` | PostgreSQL prepara la sentencia de verdad; PHP no arma el SQL con los valores. |
| Escape del `LIKE` | Aunque la sentencia esté preparada, `%` y `_` siguen siendo comodines del `LIKE`. Si no se escapan, buscar «50%» trae de más. |
| Hash + cifrado del documento | El hash permite buscar y exigir unicidad sin descifrar. El cifrado permite mostrar el documento a quien tiene permiso. Las claves vienen del entorno, nunca del repositorio. |
| `UPDATE ... WHERE version = :version` | Comparar y escribir en una sola sentencia es atómico. Leer y luego escribir deja una ventana para que otro guarde en medio. |
| `If-Match` obligatorio (428) | Sin la versión leída no hay forma de detectar el conflicto; editar «a ciegas» se rechaza. |
| Índice único parcial en `documento_hash` | La verificación en la aplicación puede perder una carrera. La base es la última defensa: `23505` → 409. |
| Por qué no en `app/` (Laravel) | La tarea exige «sin framework». El Dominio y la Aplicación se podrán mover a Laravel; solo cambiaría Presentación. |

## 4. Preguntas probables y respuestas

**¿Qué diferencia hay entre C4 y UML aquí?**
C4 muestra la arquitectura por niveles de zoom: sistema, contenedores y componentes. El UML de paquetes muestra la **regla de dependencias** entre capas, y el de secuencia muestra **una ejecución** concreta a través de ellas.

**¿Dónde está el «error de persistencia» que pide la tarea?**
- **Con dobles en memoria:** APP-06 (falla al guardar), APP-07 (falla la bitácora y se revierte el paciente) y APP-08 (falla al leer).
- **Con dobles de PDO:** PER-03 (conexión caída en cada operación), PER-04 (`23505`) y PER-06 (falla el `COMMIT`).
- **En HTTP:** HTTP-07 muestra que el usuario recibe un 503 sin detalles internos.

**¿Qué es un doble de prueba y cuáles usaste?**
Un objeto que reemplaza una dependencia real en una prueba. Usé:
- repositorio, índice, bitácora y transacción **en memoria**;
- `PdoQueFalla`, que lanza el error de una base caída;
- `PdoGuionado`, que responde filas programadas y registra el SQL y los parámetros.

**¿Cómo sabes que las pruebas detectan errores y no pasan siempre?**
Con la comprobación por mutación: rompo el código a propósito, un defecto a la vez, en una copia. Las 8 mutaciones hacen fallar al menos una prueba.

**¿Por qué la integración con PostgreSQL quedó omitida?**
Porque necesita la contraseña de mi base local. Las pruebas están escritas (INT-01..05) y se activan con `PACIENTES_DB_DSN`. La tarea permite dobles **o** base de prueba, y los dobles cubren el error de persistencia.

**¿Qué pasa si dos personas registran el mismo DPI al mismo tiempo?**
Las dos pasan la verificación de la aplicación. La base acepta el primer `INSERT` y rechaza el segundo por el índice único (`23505`). El repositorio lo traduce a `DocumentoDuplicado` y la API responde 409 `DOCUMENTO_EN_USO`.

**¿Dónde se aplicó lo de la semana 2 (LSP)?**
Los contratos de `Identificador`, `ConsultaPacientes` y `RepositorioPacientes` son los puertos del Dominio. DOM-04 vuelve a probar el contrato de `Identificador`. La búsqueda recibe `ConsultaPacientes`, así que en el futuro puede leer de una réplica.

## 5. Recorrido de demostración

1. `cd docs/modulos/pacientes/micro-his-pacientes` y `php tests/ejecutar.php` → 37 pasan, 1 omitida.
2. Abrir [`PdoRepositorioPacientes.php`](../micro-his-pacientes/src/Persistencia/Pdo/PdoRepositorioPacientes.php) y mostrar `SQL_ACTUALIZAR` con `AND version = :version`.
3. Abrir [`ActualizarPaciente.php`](../micro-his-pacientes/src/Aplicacion/CasosDeUso/ActualizarPaciente.php): permiso, dominio, transacción y auditoría.
4. Mostrar el diagrama de paquetes y luego [`DependenciasPrueba.php`](../micro-his-pacientes/tests/Arquitectura/DependenciasPrueba.php) (ARQ-01).
5. `php tests/mutaciones.php` → 8 de 8 detectadas.

## 6. Cambios que podrían pedirte en vivo

| Cambio solicitado | Cómo hacerlo |
|---|---|
| Demostrar que la regla de capas se verifica | En `TraductorResultado.php` agregar `use Pacientes\Dominio\Modelo\Paciente;` y ejecutar: falla ARQ-01. |
| Agregar «inactivar paciente» | Caso de uso `InactivarPaciente` en Aplicación (permiso `pacientes.editar_restringido`), ruta `POST /api/v1/pacientes/{codigo}/inactivacion` con su acción, y `conEstado(INACTIVO)` + `actualizar()` con versión. Las pruebas se escriben como APP-04. |
| Buscar también por teléfono | `CriterioBusqueda::desde()` con un nuevo tipo, una constante SQL nueva en `PdoRepositorioPacientes` (con parámetro, nunca concatenado), y el parámetro en `BuscarPacientesAccion::CRITERIOS`. |
| Cambiar PostgreSQL por otra base | Nuevos adaptadores que implementen los mismos puertos y un cambio en `crearCasosDeUsoPdo()`. Dominio, Aplicación y Presentación no cambian. |
| Mostrar el 503 sin base | Levantar `php -S 127.0.0.1:8081 -t public` sin variables de entorno: responde `503 CONFIGURACION_INCOMPLETA`. |

Para regenerar los diagramas:

```bash
java -jar plantuml.jar -charset UTF-8 -tpng docs/modulos/pacientes/semana-03/diagramas/*.puml
```
