# Guía de defensa oral — Semana 1

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard de ocupación hospitalaria · **Documento:** [semana-01-uml-ocupacion.md](semana-01-uml-ocupacion.md)

## 1. Resumen en 60 segundos

> «Cada 5 minutos un temporizador consolida la ocupación: lee las salas activas, cuenta las camas por estado y calcula ocupadas entre disponibles más ocupadas. También revisa que cada cama ocupada tenga una estancia abierta y marca las diferencias. Guarda un corte con su hora. La jefatura consulta ese corte; si tiene más de 15 minutos, ve un aviso. Solo hay cifras, nunca datos de pacientes, y el tablero no cambia ningún estado.»

## 2. Decisiones clave

| Decisión | Justificación |
|---|---|
| Temporizador como actor de tiempo | Inicia la consolidación sin que nadie lo pida; UML lo permite. |
| Catálogo y admisión no son actores | El tablero solo lee sus tablas; no se comunica con ellos. |
| Mantenimiento e inactivas fuera del cálculo (R-01) | No se pueden asignar: contarlas haría ver más espacio del que hay. |
| Consolidar aparte de consultar | La consulta es rápida, y si la lectura falla se conserva el corte anterior (R-09). |

## 3. Preguntas probables

**¿Qué pasa con una sala que solo tiene camas en mantenimiento?** No tiene camas operativas: muestra «no aplica» en vez de dividir entre cero (D4, R-02).

**¿Por qué el tablero no corrige una inconsistencia cama-estancia?** Porque no es dueño de esos datos: el estado lo cambian el catálogo y admisión. El tablero la marca (E1) para que ellos la resuelvan (R-04, R-09).

**¿Cómo sabe el usuario si el dato es viejo?** Cada corte guarda su hora (R-05); con más de 15 minutos se agrega un aviso (D8, R-06).
