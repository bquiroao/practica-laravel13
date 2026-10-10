# Guía de defensa oral — Semana 2

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** RBAC · **Documento:** [semana-02-ocp-requerimientos-diseno.md](semana-02-ocp-requerimientos-diseno.md)

## 1. Resumen en 60 segundos

> «Tomé el flujo que modelé en la semana 1 y lo convertí en 10 requerimientos funcionales, 9 no funcionales y 14 criterios de aceptación. Después apliqué el principio abierto/cerrado.
>
> Antes, una sola clase tenía todas las reglas en una cadena de `if/elseif` y escribía la bitácora en un archivo, así que cada regla nueva obligaba a modificarla. Ahora cada regla es una clase que implementa la interfaz `ReglaAutorizacion`, y el `Autorizador` solo recorre la lista que recibe, sin saber qué reglas son.
>
> Para demostrarlo agregué un requerimiento nuevo, que la prescripción solo se pueda crear desde la red del hospital: fue una clase nueva y una línea en la composición, sin tocar el `Autorizador`. Un script ejecuta 18 pruebas y todas pasan.»

## 2. Orden sugerido de la exposición

1. **Requerimientos:** mostrar 2 o 3 RF y su origen en la semana 1, y RNF-05 (extensibilidad), que es el que motiva OCP.
2. **Criterios de aceptación:** explicar el formato *Dado / Cuando / Entonces* con CA-09.
3. **OCP:** leer la definición de la fuente obligatoria.
4. **Antes:** diagrama + el `elseif` comentado «una regla nueva sería otro elseif aquí».
5. **Después:** diagrama, la interfaz `ReglaAutorizacion` y el `foreach` del `Autorizador`.
6. **Demostración en vivo** (sección 5 de esta guía).
7. **Responsabilidades y dependencias:** explicar por qué `composicion.php` es el único punto acoplado a las clases concretas.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Elegir OCP para este flujo | Las reglas de acceso **crecen** con el tiempo (turnos, red, consentimiento, tipo de paciente). OCP protege el código que ya funciona frente a ese crecimiento. |
| Usar interfaces y no herencia | La fuente dice «herencia y/o interfaces». Las reglas no comparten estado ni lógica, solo el contrato `evaluar()`, así que una clase base abstracta no aportaría nada y acoplaría más. |
| `evaluar()` devuelve `?string` | `null` = cumple; un texto = el motivo. Cada regla define su propio motivo, sin un catálogo central que habría que editar. |
| No usar un `enum` de motivos | Un `enum` con todos los motivos tendría que **modificarse** con cada regla nueva, lo que contradice OCP. |
| La primera regla que falla deniega | Es más simple, y el motivo registrado es el primero que se incumple. El orden se define en `composicion.php`. |
| `Autorizador` fail-closed | Sin reglas, con una regla que lanza una excepción o con un error al leer membresías, se deniega. En salud es preferible un falso negativo a un acceso indebido. |
| Dos `RegistroAuditoria` (principal + respaldo) | Cumple RNF-02: aunque la bitácora caiga, la denegación queda registrada en algún lugar y la operación sigue denegada. |
| `ServicioAsignacionRol` usa el `Autorizador` | Asignar roles también se autoriza (R-04). Se reutiliza el mismo mecanismo, sin duplicarlo. |
| `composicion.php` | Es la raíz de composición: el único lugar que conoce las clases concretas. Se modifica para *configurar*, no para *cambiar la lógica*. |
| Prueba de paridad PAR-01 | Una refactorización no debe cambiar el comportamiento. La prueba compara antes y después en 7 escenarios. |

## 4. Preguntas probables y respuestas

**¿Qué dice OCP en una frase?**
Que una clase debe estar abierta a extensión y cerrada a modificación: el comportamiento nuevo se agrega con código nuevo, no editando el que ya funciona.

**Pero sí modificaste `composicion.php`. ¿Eso no viola OCP?**
No. OCP aplica a las clases con **lógica de negocio**: `Autorizador`, las reglas y los servicios. La raíz de composición existe justamente para absorber los cambios de configuración. Que cambie una línea de configuración es lo esperado; lo que no debe cambiar es la lógica que decide.

**¿Cuál es la diferencia entre RF y RNF?**
Un RF dice **qué** hace el sistema; por ejemplo, RF-08: responder 403 con `correlation_id`. Un RNF dice **cómo de bien** lo hace, o con qué cualidad; por ejemplo, RNF-01 fail-closed o RNF-05 extensibilidad.

**¿Para qué sirven los criterios de aceptación?**
Para que un requerimiento sea **verificable**. Cada CA tiene condiciones concretas y un resultado esperado, y aquí cada uno tiene además una prueba automática con el mismo ID.

**¿Qué otros principios SOLID se ven en el diseño?**
- **SRP:** cada regla tiene una sola razón para cambiar.
- **DIP:** los servicios dependen de interfaces y no de implementaciones; por ejemplo, `RegistroAuditoria` en lugar de `file_put_contents`.
- **LSP:** cualquier `ReglaAutorizacion` puede sustituir a otra en la lista sin romper al `Autorizador`.

El foco de la tarea es OCP.

**¿Cómo pruebas que el `Autorizador` no cambió?**
- La prueba OCP-02 lee el código fuente y confirma que no importa ni crea reglas concretas.
- OCP-01 prueba la regla nueva y OCP-03 prueba quitarla, ambas sin tocar el `Autorizador`.
- Además, se puede desactivar la extensión en `composicion.php` y ver que solo falla OCP-01.

**¿Qué pasa si dos reglas fallan al mismo tiempo?**
Se registra el motivo de la primera, según el orden de `composicion.php`. Es una decisión consciente. Si se quisieran todos los motivos, se cambiaría la política del `Autorizador`, que es su única razón para cambiar.

**¿Por qué RNF-09 (rendimiento) dice «pendiente»?**
Porque medir 50 ms en memoria no significaría nada; hay que medirlo con PostgreSQL real. Prefiero declararlo pendiente para la semana 15 antes que inventar el dato.

**¿Por qué PHP sin framework y no Laravel?**
Para que el principio se vea sin la «magia» del framework y porque la semana 3 pide un micro-HIS en PHP vanilla. Las interfaces se podrán conectar a Laravel después; por ejemplo, `CatalogoPermisos` sobre Spatie Permission.

**¿Qué es el `correlation_id`?**
Un identificador único por denegación. El usuario lo ve en el mensaje de error y el auditor lo usa para encontrar el evento exacto (CA-14), sin que el 403 revele detalles internos.

## 5. Demostración en vivo

```bash
# 1. Todas las pruebas pasan
php docs/modulos/rbac/semana-02/ejemplo-ocp/verificar.php

# 2. Quitar la extensión: comentar en despues/composicion.php la línea
#        new ReglaRedHospitalaria(['prescripciones.crear'], '10.20.0.0/16'),
#    y volver a ejecutar → solo falla OCP-01 (17/18)
php docs/modulos/rbac/semana-02/ejemplo-ocp/verificar.php

# 3. Restaurar la línea → 18/18 otra vez
```

Mientras corre, mostrar `despues/src/Servicios/Autorizador.php` y señalar que no hay ningún `new Regla...` ni ningún `use Rbac\Reglas\...`.

## 6. Cambios que podrían pedirte en vivo

| Cambio solicitado | Cómo hacerlo |
|---|---|
| «Agrega una regla: un usuario inactivo no puede operar» | Crear `src/Reglas/ReglaUsuarioActivo.php` que implemente `ReglaAutorizacion`, reciba `DirectorioUsuarios` y devuelva `'USUARIO_INACTIVO'` si el estado no es `active`. Agregar `new ReglaUsuarioActivo($usuarios)` en `reglasDeAutorizacion()` (habrá que pasarle `$usuarios`). El `Autorizador` no se toca. |
| «Restringe por horario de turno» | La misma receta: `ReglaHorarioTurno` con hora de inicio y fin; compara `$contexto->solicitud->ahora`. |
| «Agrega una validación de asignación: la vigencia no puede superar un año» | Crear `ReglaVigenciaMaxima` que implemente `ReglaAsignacion` y devuelva `new ErrorAsignacion('VIGENCIA_EXCEDIDA', 422)`. Agregarla en `reglasDeAsignacion()`. |
| «Cambia el orden de las reglas» | Reordenar el arreglo en `composicion.php`. |
| «Escribe la bitácora en un archivo JSON» | Crear `RegistroAuditoriaArchivo implements RegistroAuditoria` y pasarlo al `Autorizador` en la composición. Eso es DIP + OCP. |
| «Escribe la prueba de tu regla nueva» | Copiar el patrón de OCP-01 en `verificar.php`: `prueba('OCP-04', '...', function () { ... igual('MOTIVO', $d->motivo, '...'); });` |

**Plantilla mínima de una regla nueva:**

```php
<?php
declare(strict_types=1);

namespace Rbac\Extension;

use Rbac\Contratos\ReglaAutorizacion;
use Rbac\Dominio\ContextoAutorizacion;

final class ReglaNueva implements ReglaAutorizacion
{
    public function evaluar(ContextoAutorizacion $contexto): ?string
    {
        return /* condición se cumple */ true ? null : 'MOTIVO_NUEVO';
    }
}
```
