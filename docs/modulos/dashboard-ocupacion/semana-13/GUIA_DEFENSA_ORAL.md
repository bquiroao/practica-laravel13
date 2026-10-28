# Guía de defensa oral — Semana 13

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard de ocupación hospitalaria · **Documento:** [semana-13-revision-tecnica.md](semana-13-revision-tecnica.md)

> «Es una inspección del Micro-HIS de ocupación, congelado en un commit de `develop`, con seis fases, roles separados y un checklist de 15 ítems que cubre la fórmula, las salas, los estados de cama, el corte y su hora, la procedencia, el permiso y los fallos. La autorrevisión encontró dos mayores y tres menores.»

**¿Por qué dos estancias en una cama es mayor?** Porque son dos pacientes en un mismo lugar, y R-04 pide marcar toda diferencia entre la cama y sus estancias. `EXISTS` solo pregunta si hay alguna, no cuántas, y así la cifra se ve correcta aunque el dato está roto.

**¿Qué tiene de malo que una sala inactiva no aparezca?** Nada, si está vacía (R-03). Pero si tiene un paciente, el tablero lo esconde sin avisar. El catálogo prohíbe desactivar una sala con camas ocupadas; si aun así pasa, el tablero es quien debe hacerlo visible.

**¿Por qué la mutación hizo fallar las pruebas?** Porque dos pruebas del Micro-HIS esperan los códigos E3 y E8. Eso muestra que corregir H-02 no es solo cambiar el controlador: también hay que actualizar las pruebas, y el criterio E-02 lo detecta.
