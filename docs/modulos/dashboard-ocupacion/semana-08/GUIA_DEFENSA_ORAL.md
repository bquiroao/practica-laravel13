# Guía de defensa oral — Semana 8

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard de ocupación hospitalaria · **Documento:** [semana-08-flujo-ux.md](semana-08-flujo-ux.md)

> «La jefatura de enfermería consulta qué salas tienen camas libres y el administrador, además, revisa inconsistencias y puede pedir un corte nuevo. Todas las cifras llevan su hora y su fuente, y si algo falla se conserva el corte anterior en vez de mostrar números vacíos.»

**¿Por qué cada pantalla muestra la hora del corte?** Porque la ocupación cambia: una cifra sin hora parece actual aunque tenga una hora de antigüedad (R-05). Si pasan más de 15 minutos sin corte nuevo, W5 lo avisa (R-06).

**¿Por qué el tablero no corrige las inconsistencias?** Porque no es dueño de esos datos: las camas son del catálogo y las estancias de admisión. Solo las marca y dice dónde se corrigen (R-04, R-09). **¿Qué datos protege?** Solo cifras por sala, nunca pacientes (R-07), y solo del hospital de la sesión (R-08).
