# Guía de defensa oral — Semana 6

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Citas médicas y agenda · **Documentos:** [presentación](semana-06-diapositivas.md) y [matriz y registro del cambio](semana-06-defensa-arquitectura.md)

> «La agenda tiene un hilo trazable: la recepcionista pide CU-01, RF-02 y RF-03 prohíben los cruces, `POST /citas` lo expone, y cada capa hace una parte. El cambio práctico fue la concurrencia: ahora la barrera de cruces va dentro del `INSERT`, la segunda solicitud del mismo horario recibe 409 E6, y el hallazgo H-02 de la semana 13 ya no se reproduce. Ni el dominio ni el controlador cambiaron.»

**¿Qué principio SOLID defiendes y dónde se ve?** DIP: `ServicioCitas` depende de los puertos `RepositorioCitas` y `Reloj`, no de PDO ni de la hora real. Por eso la misma política corre con PDO y con InMemory, y el cambio de concurrencia entró en los adaptadores sin tocar la regla.

**¿Por qué no bastaba con `cruce()`?** Porque entre la consulta y el `INSERT` puede entrar otra solicitud. Es una condición de carrera: las dos ven el horario libre. La única forma de cerrarla es que la base compruebe y escriba en la misma operación.

**¿Por qué `HorarioTomado` es una excepción y no un `false`?** Para no cambiar la firma de `guardar`, que devuelve el id. Así los dobles que ya implementan el puerto, como el de la S13, siguen compilando. La aplicación la traduce a E6, y solo la vista conoce el 409.

**¿Cómo pruebas una carrera sin dos procesos?** Con un repositorio que intercala la segunda solicitud justo después de la consulta de cruces. Antes del cambio quedaban 2 citas activas cruzadas; después queda 1 y la primera recibe 409 E6.

**¿Qué falta para producción?** Traducir la violación de la exclusión `gist` de PostgreSQL a `HorarioTomado`, documentar E6 en el OpenAPI del PR #351 y la autorización (H-01) en la semana 15.
