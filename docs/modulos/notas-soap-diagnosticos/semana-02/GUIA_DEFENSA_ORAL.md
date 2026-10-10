# Guía de defensa oral — Semana 2

**Estudiante:** Joshua Eduardo García Reyes · **Módulo:** ASII-11 Notas SOAP y diagnósticos · **Documento:** [semana-02-srp-requerimientos-diseno.md](semana-02-srp-requerimientos-diseno.md)

## 1. Resumen en 60 segundos

> «Convertí las reglas de la semana 1 en diez requerimientos funcionales, siete no funcionales y doce criterios de aceptación que se pueden probar. Después apliqué SRP al registro de la nota. Antes, una sola clase hacía todo: leía el token, revisaba permisos, validaba la nota y el diagnóstico, guardaba, escribía la bitácora y armaba la respuesta. Eso son ocho razones para cambiar. Además tenía tres defectos: guardaba la nota antes de validar el diagnóstico, no usaba transacción y aceptaba el autor que mandaba el cliente. Lo separé en controlador, servicio coordinador, reglas de dominio e interfaces de persistencia. Un script corre 20 pruebas y demuestra que los criterios se cumplen, que las responsabilidades están separadas y que los tres defectos desaparecieron.»

## 2. Orden sugerido de la exposición

1. **Requerimientos:** mostrar dos RF y cómo salen de una regla de la semana 1 (por ejemplo, RF-05 de R-06).
2. **Criterios de aceptación:** explicar el formato Dado/Cuando/Entonces con CA-08.
3. **SRP:** leer la definición de MVP Cluster y explicar "una sola razón para cambiar".
4. **Antes:** abrir `antes/ServicioNotaSoap.php` y señalar las ocho responsabilidades y dónde está la nota huérfana.
5. **Después:** recorrer `RegistrarNotaConDiagnostico` y mostrar que solo coordina.
6. **Evidencia:** ejecutar `verificar.php` en vivo.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Elegir SRP | El flujo junta reglas que cambian por motivos distintos (acceso, nota, diagnóstico, base de datos, API). Es el principio que más problemas resolvía aquí. |
| `SeccionesSoap` separada de `NotaSoap` | La regla de "nota completa" puede cambiar sin que cambie la firma. Cada una tiene su motivo. |
| El servicio no valida | Las reglas viven en el dominio. El servicio solo decide el orden: validar todo y después guardar todo. |
| Interfaces para la persistencia | El servicio no cambia si se pasa de memoria a PostgreSQL. También permite simular una falla de la base (CA-10). |
| El autor sale del token | Que el autor dependa del cuerpo de la petición es un problema de seguridad (DEF-03). |
| `composicion.php` | Es el único lugar que conoce las clases concretas; cambiar un adaptador no toca la lógica. |
| Mantener PAR-01 | Demuestra que la refactorización no cambió el comportamiento que ya estaba bien. |

## 4. Preguntas probables y respuestas

**¿Qué es un requerimiento no funcional?**
Describe *cómo* debe comportarse el sistema, no *qué* hace: integridad, seguridad, mantenibilidad, rendimiento. Por ejemplo, RNF-01 dice que nunca puede quedar una nota sin diagnóstico.

**¿Qué diferencia hay entre un RF y un criterio de aceptación?**
El RF dice qué debe hacer el sistema. El criterio dice cómo comprobar que lo hace, con un caso concreto: dado, cuando, entonces.

**¿Cómo sabes que una clase tiene una sola responsabilidad?**
Le pregunto por qué tendría que cambiar. `Diagnostico` solo cambia si cambia la regla del diagnóstico. `ServicioNotaSoap` cambiaba por ocho motivos distintos.

**¿SRP no hace que haya demasiadas clases?**
Hay más clases, pero cada una es corta y se prueba sola. El costo es que hay que navegar más archivos; a cambio, un cambio toca un solo lugar (tabla de la sección 9).

**¿Cómo se relaciona SRP con los defectos que encontraste?**
Con todo mezclado no había un lugar claro para la transacción ni para decidir quién es el autor. Al separar, cada cosa quedó en su sitio y el orden correcto se ve.

**¿Qué hace `verificar.php` exactamente?**
Arma un entorno nuevo en memoria para cada prueba, manda peticiones simuladas al controlador y compara el resultado con lo esperado. También revisa el código fuente (SRP-01, SRP-02) y compara el diseño antes con el después.

**¿Por qué una base en memoria y no PostgreSQL?**
La semana pide demostrar el diseño. Como el servicio depende de interfaces, la misma lógica funciona con PostgreSQL cambiando solo los adaptadores en `composicion.php`.

**¿Por qué el expediente cerrado responde 409 y no 404?**
Porque la petición es válida, pero choca con el estado del expediente. Si el equipo prefiere 404 para expedientes inexistentes, el cambio está solo en el controlador.

## 5. Recorrido de demostración

1. `php docs/modulos/notas-soap-diagnosticos/semana-02/ejemplo-srp/verificar.php` → 20 PASA.
2. Abrir `despues/src/Dominio/Diagnostico.php` y cambiar `J18.9` a un código inválido en una prueba para mostrar una FALLA.
3. Deshacer el cambio y volver a correr el script.

## 6. Cambios que podrían pedirte en vivo

| Cambio pedido | Qué modificar |
|---|---|
| «Que el CIE-10 acepte códigos de 4 caracteres después del punto» | Solo la constante `FORMATO_CIE10` en `Diagnostico.php`; volver a correr el script. |
| «Que el expediente inexistente responda 404» | Solo `NotaSoapController`, con una excepción distinta para "no existe" y "cerrado". |
| «Que la nota acepte varios diagnósticos» | `SolicitudRegistroNota` recibe una lista y el servicio guarda cada uno dentro de la misma transacción. |
| «Que enfermería pueda escribir notas» | Solo `VerificadorAccesoEnMemoria` (y su versión real), sin tocar el servicio. |
| «Mostrar otro principio, por ejemplo DIP» | Señalar que el servicio depende de interfaces de `Contratos/` y no de clases concretas. |
