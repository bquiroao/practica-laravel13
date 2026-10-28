# Guía de defensa oral — Semana 8

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Catálogo de salas, wards y camas · **Documento:** [semana-08-flujo-ux.md](semana-08-flujo-ux.md)

> «El administrador crea salas y camas y cambia estados; el supervisor solo alterna una cama entre disponible y mantenimiento. Cada pantalla muestra solo lo que el rol puede hacer, pide confirmación explícita y, si algo falla, conserva lo escrito y dice cómo recuperarse.»

**¿Por qué no aparece la opción «Ocupada»?** Porque solo admisión ocupa una cama (R-06). Si la mostrara deshabilitada sin explicación, el usuario no entendería; por eso para una cama ocupada se muestra «La gestiona admisión».

**¿Qué pasa si dos personas cambian la misma cama?** La segunda ve W5: «La cama cambió mientras la editabas», con el estado nuevo, su motivo conservado y el botón «Recargar». **¿Dónde está la protección de datos?** La lista solo muestra conteos y camas, nunca pacientes, y solo del hospital de la sesión.
