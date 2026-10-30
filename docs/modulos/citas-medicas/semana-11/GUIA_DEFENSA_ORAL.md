# Guía de defensa oral — Semana 11

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Citas médicas y agenda · **Documento:** [semana-11-prototipo.md](semana-11-prototipo.md)

> «El prototipo agenda una cita en tres pasos, eligiendo solo entre horarios libres. Cada paso guarda su estado en la ruta, así que se puede recargar o volver atrás sin perder nada. El error crítico es que otra persona tome el horario mientras confirmo: la cita no se agenda, el motivo se conserva y se ofrecen otros horarios.»

**¿Por qué el estado va en la ruta?** Porque así cada paso es una URL propia: se recarga sin perder datos y «atrás» funciona. Además, las capturas pueden abrir cada paso directamente. **¿Cómo se probó que es usable en móvil?** El script fija 320, 360 y 430 px con el protocolo DevTools y mide `scrollWidth` frente a `clientWidth`: 15 de 15 sin scroll horizontal.
