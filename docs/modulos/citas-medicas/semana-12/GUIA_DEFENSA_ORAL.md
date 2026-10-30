# Guía de defensa oral — Semana 12

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Citas médicas y agenda · **Documentos:** [presentación](semana-12-presentacion.md) y [recorrido, matriz y cambio](semana-12-defensa-prototipo.md)

> «Con el prototipo muestro el flujo en 10 pasos: agendar en un asistente de 3 pasos, el horario tomado sin perder el motivo y, como cambio solicitado, cancelar desde la agenda. La médica solo ve y cancela sus citas, una cancelada ya no tiene acción y el motivo es obligatorio. La acción destructiva está en rojo y separada de "Volver", y en ningún ancho medido hay scroll horizontal.»

**¿Por qué el botón dice «Cancelar la cita de A. Pérez» y no solo «Cancelar»?** Porque «Cancelar» en un formulario suele significar «salir sin guardar». Eso fue B-02 de la semana 9: una confusión que libera un horario que el paciente sí iba a usar. El texto completo dice qué se va a perder.

**¿Por qué el `aria-label` en el enlace de cada fila?** Porque con lector de pantalla todas las filas dirían «Cancelar». Con la hora y el paciente se sabe cuál es (B-03, P1).

**¿Qué pasa con el horario al cancelar?** Vuelve a la lista de libres, como pide R-08: cancelar libera el horario para otro paciente.

**¿El prototipo reemplaza las reglas del backend?** No. Muestra lo que el backend valida: el motivo (E2), el estado (E7) y, en la semana 15, que la médica solo cancele lo suyo (R-09).

**¿Cómo demostraste que el camino de agendar no se rompió?** Con el mismo `capturar.mjs`: la agenda y las rutas nuevas, en 3 anchos, sin scroll y con el foco donde corresponde. Los pasos 1 a 5 siguen siendo los de la semana 11.
