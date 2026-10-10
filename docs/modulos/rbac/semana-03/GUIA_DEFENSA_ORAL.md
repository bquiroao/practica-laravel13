# Guía de defensa oral — Semana 3

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** RBAC · **Documento:** [semana-03-arquitectura-micro-his.md](semana-03-arquitectura-micro-his.md) · **Código:** [micro-his-rbac](../micro-his-rbac/README.md)

## 1. Resumen en 60 segundos

> «Construí el Micro-HIS RBAC en PHP puro, con cuatro capas: Presentación, Aplicación, Dominio y Persistencia.
>
> La regla es que las dependencias apuntan hacia el Dominio: Presentación usa Aplicación, Aplicación usa Dominio, y Persistencia implementa las interfaces que el Dominio define. Así, las reglas de acceso del hospital no saben nada de HTTP ni de PostgreSQL.
>
> La persistencia usa PDO solo con sentencias preparadas. La arquitectura la documenté con C4 en tres niveles más un diagrama de paquetes, y la verifiqué con pruebas que leen el código y fallan si una capa depende de otra que no debe.
>
> Probé el camino feliz, las reglas de dominio y los errores de persistencia con dobles de PDO: 32 pruebas pasan.»

## 2. Orden sugerido de la exposición

1. **C4 contexto:** quién usa el sistema y con qué sistemas del HIS se relaciona.
2. **C4 contenedores:** API PHP, PostgreSQL y archivo de respaldo.
3. **Paquetes:** la regla de dependencias y por qué la Persistencia apunta al Dominio.
4. **C4 componentes:** recorrer una petición (Kernel → Middleware → caso de uso → política → repositorio).
5. **Código:** abrir `RepositorioPdo::ejecutar()` (sentencias preparadas) y `TablaRutas` (permiso por ruta).
6. **Demostración:** ejecutar las pruebas (sección 5).

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Interfaces de repositorio en el **Dominio** | Inversión de dependencias: el dominio define qué necesita (`RepositorioMembresias`) y la persistencia lo implementa. Por eso puedo probar las reglas sin base de datos. |
| `Transaccion` en **Aplicación** y no en Dominio | Delimitar una transacción es una decisión de orquestación del caso de uso, no una regla del hospital. |
| `ResultadoAutorizacion` **sin motivo** | Si la Presentación no recibe el motivo, no puede revelarlo por error en el 403. R-08 queda garantizado por diseño. |
| Permiso declarado **en la ruta** | La protección de rutas queda en un solo lugar (`TablaRutas`), revisable de un vistazo. Ninguna ruta puede quedar sin permiso porque el constructor de `Ruta` lo exige. |
| `AsignarRol` vuelve a autorizar `roles.asignar` | Defensa en profundidad: aunque alguien llame al caso de uso sin pasar por HTTP (por ejemplo, desde un script), la regla se sigue cumpliendo. |
| Validar formato en Presentación y reglas en Dominio | «¿Es una fecha?» es un problema de HTTP. «¿El rol existe?» es una regla del hospital. Si se mezclan, el dominio depende del formato de entrada. |
| `ErrorPersistencia` en el **Dominio** | Los puertos del dominio deben declarar qué errores lanzan. Si fuera una excepción de PDO, el dominio dependería de PDO. |
| `ATTR_EMULATE_PREPARES = false` | Obliga a que PostgreSQL prepare la sentencia de verdad en el servidor; PHP no arma el SQL. |
| Índice único **y** regla de dominio para duplicados | La regla da un mensaje claro (409) en el caso normal. El índice protege ante dos solicitudes simultáneas (PER-07, INT-03). |
| Raíz de composición sin framework | La tarea prohíbe frameworks. Una función que crea y conecta los objetos basta, y las pruebas la reutilizan con dobles. |

## 4. Preguntas probables y respuestas

**¿Qué es el modelo C4?**
Es una forma de documentar la arquitectura en niveles de zoom:

- **Contexto:** el sistema y su entorno.
- **Contenedores:** las piezas que se despliegan (API, base de datos).
- **Componentes:** las partes internas de un contenedor.
- **Código:** las clases.

Hice los tres primeros niveles. El de código lo cubre el diagrama de clases de la semana 2.

**¿Cuál es la diferencia entre capa y contenedor?**
- Un **contenedor** es algo que se ejecuta o se despliega por separado (la API PHP, PostgreSQL).
- Una **capa** es una separación lógica dentro de un contenedor. Las cuatro capas viven dentro del mismo contenedor «Aplicación PHP».

**¿Por qué la Persistencia depende del Dominio y no al revés?**
Porque la base de datos es un detalle que puede cambiar, y las reglas de acceso del hospital no deberían cambiar por eso. Si el Dominio dependiera de PDO, tendría que tocarlo para cambiar de base de datos y no podría probarlo sin una base real.

**¿Cómo demuestras que se cumple la regla de dependencias?**
- **ARQ-01:** lee el código de cada capa y falla si encuentra un `use` de una capa prohibida.
- **Comprobación de que la prueba sirve:** inyecté a propósito un `use Rbac\Persistencia\…` en el Dominio y la prueba falló señalando el archivo. Luego lo revertí.

**¿Qué es una sentencia preparada y por qué evita la inyección SQL?**
La consulta se envía con marcadores (`:usuario`) y los valores viajan aparte. La base de datos nunca interpreta un valor como SQL, así que un `usuario_id` como `' OR 1=1 --` es solo un texto.

**¿Qué es un doble de prueba? ¿Cuáles usaste?**
Es un objeto que reemplaza a una dependencia real en una prueba. Usé:

- **Repositorios en memoria** (fakes).
- **`PdoQueFalla`:** un PDO que lanza `PDOException` con un SQLSTATE elegido, como 08006 (conexión perdida) o 23505 (duplicado).
- **`PdoEspia`:** registra BEGIN, COMMIT y ROLLBACK.
- **`RegistroAuditoriaEnMemoria` con `fallar = true`:** simula una bitácora caída.

**¿Qué pasa si la base de datos se cae en medio de una autorización?**
Fail-closed: la operación se deniega con 403 y el evento se escribe en el archivo de respaldo (PER-08, PER-10). Si la base se cae al arrancar, la API responde 503 sin detalles técnicos.

**¿Qué pasa si se cae a mitad de una asignación?**
La membresía y el evento `ROL_ASIGNADO` están en la misma transacción. Si cualquiera de los dos falla, se hace ROLLBACK y se responde 500 sin que quede una membresía sin evidencia (PER-05, PER-06, INT-05).

**¿Dónde están los patrones?**
- **Front Controller:** `public/index.php` + `Kernel`.
- **Strategy:** las reglas.
- **Repository + Adapter:** puertos y adaptadores PDO.
- **Unit of Work:** `Transaccion`.
- **Command:** `ComandoAsignarRol`.
- **Raíz de composición:** `contenedor.php`.

**¿Por qué no MVC todavía?**
MVC es el tema de la semana 4. Esta semana la Presentación ya separa la entrada (Kernel y acciones) de la lógica, y en la semana 4 se reorganizará formalmente en controladores.

**¿Ejecutaste contra PostgreSQL real?**
Las pruebas de integración están escritas (INT-01 a INT-05) y se ejecutan definiendo `MICROHIS_DB_DSN`. La evidencia de esta entrega usa dobles, que es una de las opciones que acepta la tarea.

> Si ejecutaste las pruebas de integración en tu equipo, cambia esta respuesta y muestra el resultado.

## 5. Demostración en vivo

```bash
cd docs/modulos/rbac/micro-his-rbac

# 1. Todas las pruebas
php tests/ejecutar.php

# 2. Romper la arquitectura a propósito: en src/Dominio/Reglas/Asignacion/ReglaNoAutoasignacion.php
#    agregar la línea:   use Rbac\Persistencia\Pdo\PdoTransaccion;
#    ejecutar de nuevo → ARQ-01 falla señalando el archivo. Quitar la línea.
php tests/ejecutar.php

# 3. (Si hay PostgreSQL) levantar la API y probar una denegación
php -S 127.0.0.1:8080 -t public
curl -i -X POST http://127.0.0.1:8080/api/v1/prescripciones -H "X-Tenant-ID: hosp-oriente" -H "Authorization: Bearer token-ana"
```

## 6. Cambios que podrían pedirte en vivo

| Cambio solicitado | Cómo hacerlo |
|---|---|
| «Protege una ruta nueva, por ejemplo `POST /api/v1/resultados` con `resultados.registrar`» | Agregar en `TablaRutas::crear()` la línea `new Ruta('POST', '#^/api/v1/resultados$#', 'resultados.registrar', new OperacionClinicaDemoAccion('Resultado'))`. Ninguna otra capa cambia. |
| «Agrega una regla de autorización» | Crear una clase en `src/Dominio/Reglas/Autorizacion/` que implemente `ReglaAutorizacion` y agregarla a la lista de `PoliticaAutorizacion` en `bootstrap/contenedor.php` (OCP, semana 2). |
| «Agrega una validación de asignación» | La misma receta con `ReglaAsignacion`, en `src/Dominio/Reglas/Asignacion/`. |
| «Cambia la base de datos por otra» | Escribir nuevos adaptadores que implementen los puertos de `Dominio/Puertos` y cambiarlos en `crearKernel()`. Dominio, Aplicación y Presentación no cambian. |
| «Escribe una prueba de una regla nueva» | Copiar el patrón de DOM-04 en `tests/Dominio/ReglasDominioPrueba.php`: crear la `SolicitudAsignacion` y comparar con `igual(...)`. |
| «Muéstrame que no hay SQL en los controladores» | Ejecutar las pruebas: ARQ-02 lo verifica. También se puede buscar `SELECT` en `src/Presentacion`. |
| «¿Dónde se ve la sentencia preparada?» | `src/Persistencia/Pdo/RepositorioPdo.php`, método `ejecutar()`: `prepare()` + `execute($parametros)`. |
