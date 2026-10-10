# Guía de defensa oral — Semana 4

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** RBAC · **Documento:** [semana-04-capas-mvc-repositorio.md](semana-04-capas-mvc-repositorio.md) · **Código:** [micro-his-rbac](../micro-his-rbac/README.md)

## 1. Resumen en 60 segundos

> «Seguí con el mismo Micro-HIS de la semana 3. Organicé la entrada con MVC: el controlador solo coordina (formulario, caso de uso y vista), el formulario valida el formato, las vistas son las únicas que arman la respuesta, y el modelo son las capas de Aplicación y Dominio.
>
> Las interfaces Repository están en el Dominio, porque quien las necesita las define, y cada una tiene dos adaptadores: PDO para PostgreSQL e InMemory. Elijo cuál usar con una variable de entorno y una misma suite de contrato prueba los dos.
>
> Todo esto lo hice sin tocar ninguna regla de negocio. Y analicé cómo se integraría el módulo con la base federada del HIS: `hospital_memberships` ya tiene la misma semántica, así que bastaría con escribir adaptadores nuevos. Encontré tres brechas en el esquema compartido que hay que resolver antes.»

## 2. Orden sugerido de la exposición

1. **Tabla «qué cambió»** (§2 del documento): Dominio y Aplicación intactos.
2. **MVC:** diagrama y código de `AsignacionRolControlador::crear()`.
3. **Repository:** por qué la interfaz va en el Dominio; tabla de los dos adaptadores; suite de contrato.
4. **Objetos reutilizables** (§6): escoger 3 (`RepositorioPdo`, `ErrorVista`, `contratoRepositorios`).
5. **Repositorio compartido** (§8): diagrama, correspondencia de tablas, separación lectura/escritura y las 3 brechas.
6. **Demostración** (sección 5 de esta guía).

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Aplicación + Dominio = **Modelo** | En MVC, el Modelo es todo lo que no es entrada ni salida. Separarlo en capas es la arquitectura en capas dentro del Modelo. |
| **Formulario** separado del controlador | Valida el formato (¿hay `usuario_id`? ¿la fecha es ISO?). Así el controlador queda en tres pasos y la validación se prueba sola (MVC-01). Gracias a esa prueba encontré el bug de la fecha `"x"`. |
| Vistas como **únicas** que arman JSON | Si mañana cambia el formato de los errores, se cambia en `ErrorVista` y nada más. ARQ-07 impide que alguien arme JSON en otro lado. |
| Rutas apuntan a `$controlador->crear(...)` | Es un *callable* tipado de PHP 8.1: se ve en una línea qué método atiende la ruta y qué permiso la protege. |
| Interfaz Repository en el **Dominio** | Inversión de dependencias: si estuviera en Persistencia, el Dominio dependería de la base de datos. |
| `Transaccion` en **Aplicación** | Decidir qué va junto en una transacción es una decisión de orquestación del caso de uso, no una regla del hospital. |
| InMemory **de producción** (no solo un doble) | La tarea lo pide como adaptador. Además, permite la demo sin base y que las pruebas usen el mismo código que corre. |
| Decoradores `*ConFallas` en lugar de dobles copiados | No duplico la lógica del adaptador: el decorador solo agrega «falla a pedido» sobre el adaptador real. |
| **Suite de contrato** | Es la forma de demostrar que los dos adaptadores son intercambiables (principio de sustitución de Liskov). Un adaptador futuro (el de CENTRAL) se valida igual. |
| `ConjuntoAdaptadores` + fábricas | Garantiza que los 5 adaptadores vengan del mismo origen (misma conexión PDO) para que la transacción los abarque a todos. |
| Separación lectura/escritura en el repositorio compartido | Escribir en CENTRAL (fuente de verdad) y leer de la réplica HOSPITAL (disponibilidad). Con fail-closed, si CENTRAL se cae y se lee de ahí, nadie podría trabajar. |

## 4. Preguntas probables y respuestas

**¿Qué es MVC y dónde está cada letra?**
- **M (Modelo):** casos de uso + Dominio (`src/Aplicacion`, `src/Dominio`).
- **V (Vista):** `src/Presentacion/Http/Vistas`. Arman la respuesta JSON.
- **C (Controlador):** `src/Presentacion/Http/Controladores`.

El `Kernel` es el *Front Controller* que recibe todas las peticiones y despacha al controlador correspondiente.

**¿Por qué tus vistas son JSON y no HTML?**
Porque el micro-HIS es una API. La Vista es la representación de la respuesta, y en una API esa representación es JSON. El patrón es el mismo: el controlador no decide el formato.

**¿Cómo demuestras que el controlador no tiene SQL ni reglas de negocio?**
- **ARQ-02:** sin SQL ni PDO fuera de Persistencia.
- **ARQ-05:** el controlador solo recibe casos de uso, no usa `new` y no contiene códigos como `'medico'` o `'PERMISO_NO_ASIGNADO'`.
- **ARQ-07:** no arma respuestas.
- **Comprobación:** inyecté a propósito un `new` y un `'medico'` en un controlador, y la prueba falló señalando el archivo.

**¿Qué es el patrón Repository?**
Un objeto que el dominio usa como si fuera una colección (`membresiasDe`, `guardar`), sin saber dónde se guardan los datos. Separa la lógica de negocio del acceso a datos.

**¿Qué significa «límite apropiado» para la interfaz?**
La frontera entre el dominio y la infraestructura. La interfaz se define del lado del dominio, con su vocabulario (`existeActiva`, no `SELECT`), y la infraestructura la implementa.

**¿Cuál es la diferencia entre el adaptador InMemory y un mock?**
- El **InMemory** es una implementación completa y real del contrato: guarda, busca, rechaza duplicados y hace rollback. Pasa la misma suite de contrato que PDO.
- Un **mock** solo responde lo que la prueba le programa.

**¿Qué es un repositorio de datos compartido? ¿Es lo mismo que el patrón Repository?**
No. El **patrón Repository** es una clase del código. El **repositorio de datos compartido** es un estilo arquitectónico donde varios módulos se integran leyendo y escribiendo el mismo almacén. En el HIS, ese almacén es el PostgreSQL federado CENTRAL/HOSPITAL.

**¿Qué cambiarías en tu código para integrarte al repositorio compartido?**
Solo la Persistencia y la composición:
- un adaptador `PdoRepositorioMembresiasCentral` con los nombres de columnas del HIS;
- un adaptador de lectura sobre `local_user_refs`;
- cambiar `crearAdaptadores()`.

Dominio, Aplicación y MVC no cambian, y los adaptadores nuevos deben pasar `contratoRepositorios()`.

**¿Qué riesgos tiene compartir la base de datos?**
- **Acoplamiento al esquema:** mitigado porque el acceso solo pasa por adaptadores, y la suite de contrato detecta cambios.
- **Propiedad difusa de los datos:** definir quién escribe cada tabla.
- **Privilegios excesivos:** un rol de base de datos por módulo, sin `DELETE`.
- **Disponibilidad de CENTRAL:** leer de la réplica local.
- **Consistencia eventual de la réplica:** monitorear `sync_checkpoints.lag_seconds`.

**¿Qué brechas encontraste en el esquema del HIS?**
1. Las tablas de roles y permisos (Spatie) no están migradas en el esquema federado.
2. `local_access_audit` (H8) está pendiente.
3. `local_user_refs` admite un solo rol por usuario, y RBAC admite varios.

**¿Cuáles son tus objetos reutilizables?**
- `RepositorioPdo` (seguridad SQL heredada);
- las fábricas + `ConjuntoAdaptadores`;
- `Restaurable` (rollback en memoria);
- las políticas y reglas (no dependen de HTTP);
- `AutorizarOperacion` (usada por las rutas y por la asignación);
- `ErrorVista`;
- `construirCasosDeUso()` (misma composición en producción y en pruebas);
- `contratoRepositorios()`.

## 5. Demostración en vivo

```bash
cd docs/modulos/rbac/micro-his-rbac

# 1. Pruebas (mostrar los grupos Contrato, MVC y ARQ-05..07)
php tests/ejecutar.php

# 2. Flujo completo sin base de datos
php bin/demo.php

# 3. Romper MVC a propósito: en src/Presentacion/Http/Controladores/OperacionClinicaControlador.php
#    cambiar   return OperacionVista::registrada('Prescripción');
#    por       return \Rbac\Presentacion\Http\Respuesta::json(201, []);
#    → ARQ-07 falla. Deshacer el cambio.

# 4. API real sin base (PowerShell)
$env:MICROHIS_PERSISTENCIA = "memoria"; php -S 127.0.0.1:8080 -t public
```

## 6. Cambios que podrían pedirte en vivo

| Cambio solicitado | Cómo hacerlo |
|---|---|
| «Agrega una ruta nueva protegida» | En `Rutas::definir()` agregar `new Ruta('POST', '#^/api/v1/resultados$#', 'resultados.registrar', $operaciones->registrarResultado(...))`, crear el método `registrarResultado()` en `OperacionClinicaControlador` que devuelva `OperacionVista::registrada('Resultado')`. |
| «Cambia el mensaje del 403» | Solo en `ErrorVista::accesoDenegado()`. Ajustar HTTP-03/MVC-02 si comparan el texto. |
| «Agrega un campo obligatorio al formulario» | En `FormularioAsignacion::aComando()` leerlo y agregarlo a `$invalidos` si falta. El controlador no cambia. |
| «Usa el adaptador InMemory en lugar de PDO» | `MICROHIS_PERSISTENCIA=memoria`. Ningún archivo de código cambia. |
| «Escribe un adaptador nuevo» | Una clase en `src/Persistencia/...` que implemente el puerto. Agregarla a una fábrica y correr `contratoRepositorios('CON-NUEVO', ...)` en la suite de contrato. |
| «¿Dónde está la validación de negocio vs. la de formato?» | Formato: `FormularioAsignacion`. Negocio: `src/Dominio/Reglas/Asignacion/*`. |
