# Guía de defensa oral — Semana 8

## Presentación breve

«Diseñé seis wireframes para el flujo de revisión de resultados por bioquímico: bandeja, revisión contextual, confirmación de validación, rechazo con motivo, decisión registrada y recuperación de errores. La experiencia prioriza la verificación de identidad, hospital y versión, distingue carga, vacío, éxito y error, y evita que un fallo se confunda con una decisión. La ayuda acompaña al profesional, pero no decide por valores ni inventa umbrales clínicos.»

## Preguntas para explicar el diseño

1. **¿Por qué hay seis pantallas?** Cubren el flujo principal y sus estados críticos sin dejar fuera confirmación, trazabilidad o recuperación de errores.
2. **¿Qué ocurre si el resultado cambió después de abrirlo?** Se informa conflicto, no se registra decisión y se exige recargar y volver a revisar la versión vigente.
3. **¿Qué regla valida el rechazo?** El motivo es obligatorio y no puede quedar vacío tras recortar espacios; el error queda junto al campo y conserva el texto.
4. **¿Cómo se protege la información?** La bandeja minimiza datos; el detalle, los motivos y el historial requieren autorización; no se filtran datos clínicos en URL o mensajes.
5. **¿La ayuda recomienda validar?** No. Enlaza el procedimiento institucional aplicable y deja la evaluación al bioquímico.
6. **¿La pantalla de éxito equivale a una firma digital?** No. Muestra una asociación auditable de actor, hospital, versión, decisión y hora, sin certificación criptográfica.
7. **¿Se probaron los wireframes con usuarios?** No; son diseños conceptuales pendientes de revisión con bioquímicos, privacidad y accesibilidad.

## Navegación de la evidencia

- [Informe y flujo completo](semana-08-ux-validacion-bioquimica.md)
- [WF-01 Bandeja](wireframes/01-bandeja-pendientes.svg)
- [WF-02 Revisión](wireframes/02-revision-contexto.svg)
- [WF-03 Confirmar validación](wireframes/03-confirmar-validacion.svg)
- [WF-04 Rechazo con motivo](wireframes/04-rechazo-motivo.svg)
- [WF-05 Decisión registrada](wireframes/05-decision-registrada.svg)
- [WF-06 Errores recuperables](wireframes/06-error-recuperable.svg)
