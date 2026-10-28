# Guía de defensa oral — Semana 4

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Expediente médico electrónico base · **Documento:** [semana-04-mvc-repository.md](semana-04-mvc-repository.md)

> «La petición entra por un front controller. Las rutas distinguen abrir, consultar local y consultar longitudinal; el controlador llama al servicio y le pasa el resultado a la vista, que decide el código HTTP. La interfaz Repository es `RepositorioExpedientes`, en el límite de la base HOSPITAL, y la implementan un adaptador PDO y uno en memoria que pasan el mismo guion. CENTRAL queda como un puerto aparte, porque tiene otro dueño.»

**¿Por qué CENTRAL no entra en el Repository?** Porque es otra base con otro dueño, el índice maestro. Si las dos fueran una sola interfaz, cambiar CENTRAL obligaría a tocar el repositorio del hospital, y una caída de CENTRAL arrastraría la consulta local, que hoy sigue funcionando.

**¿Qué tiene que imitar el adaptador en memoria?** Lo mismo que el SQL: solo consentimientos activos, los otros hospitales sin el propio y en el mismo orden, y un evento de auditoría por operación. La mutación que incluye el propio hospital hace fallar 1 prueba.
