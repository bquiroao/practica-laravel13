# Guía de defensa oral — Semana 8

**Estudiante:** Joshua Eduardo García Reyes · **Módulo:** ASII-11 Notas SOAP y diagnósticos · **Documento:** [semana-08-ux-notas-soap.md](semana-08-ux-notas-soap.md)

## 1. Explicación en 3 minutos

1. **Quién usa el módulo (30 s).** «Hay dos perfiles: el médico tratante, que escribe, firma y corrige, y enfermería, que solo consulta. Cada uno tiene su user flow. Hay un tercer caso: el médico que tiene el rol, pero no está asignado al episodio; el servidor lo rechaza y la pantalla W5 se lo explica sin dar detalles.»
2. **El flujo principal (1 min).** Abrir W2. «El médico escribe las cuatro secciones y el diagnóstico. La pantalla valida con las mismas reglas del servidor, pero quien decide es el servidor. Al guardar, el botón se bloquea y la petición lleva una `Idempotency-Key`: si la red falla y se reintenta, no se duplica la nota. Si algo sale mal, el texto se queda en pantalla.»
3. **Lo irreversible (1 min).** Abrir W3 y W4. «Firmar pide confirmación porque no se puede deshacer. Una nota firmada no se edita: se corrige con una versión nueva y un motivo. La anterior queda igual y en la línea de tiempo aparece como "Corregida → v2".»
4. **Los mensajes (30 s).** «Cada mensaje sale de un código real del contrato. Los 18 códigos de error están cubiertos. El 403 nunca dice por qué se negó: solo da una referencia para soporte.»

## 2. Preguntas probables

**¿Por qué enfermería no ve el botón «Nueva nota»?**
Porque su rol solo tiene `notas_soap.consultar`. Pero ocultarlo es comodidad, no seguridad: si alguien llama a la API directo, el servidor responde 403.

**¿Por qué no se guarda el borrador en el navegador para no perderlo?**
Porque es texto clínico y los equipos del hospital son compartidos. Se guarda en memoria mientras la pestaña está abierta. Si se cierra, se pierde; preferimos eso a dejar datos de un paciente en `localStorage` (D-5).

**¿Por qué la firma no se reintenta sola, si el registro sí?**
El registro tiene `Idempotency-Key`: repetirlo devuelve la misma nota. La firma no: si la primera llegó, la segunda daría 409 «ya está firmada» y confundiría al médico. Por eso, ante la duda, se vuelve a consultar la nota.

**¿Qué pasa si dos médicos corrigen la misma versión a la vez?**
La base de datos acepta solo una (UNIQUE de la semana 6). Al segundo le sale M-07: le abrimos la versión vigente y su texto queda para que lo copie.

**¿Qué es una brecha en este documento?**
Algo que la pantalla necesita y el contrato todavía no da. Por ejemplo, la API devuelve «dra-mendez» y no el nombre del médico (B-1), o no hay catálogo CIE-10 para autocompletar (B-2). Se documentan, no se resuelven esta semana.

**¿Cómo se relaciona con la semana 7?**
Cada pantalla usa los componentes que diseñé ahí: W2 y W4 son el mismo `NotaSoapFormulario` en modo registro y en modo corrección; W5 es `ErrorNotaAviso`; los botones se muestran u ocultan con `usePermisosNotas`.

## 3. Si piden un cambio en vivo

| Pedido | Dónde |
|---|---|
| «Agregue un segundo diagnóstico» | W2: botón «+ Agregar diagnóstico» y repetir el bloque. El contrato acepta uno por envío, así que sería una brecha nueva (B-6). |
| «Que enfermería pueda escribir notas de enfermería» | Sería otro tipo de nota con su propio permiso; no cabe en este proceso (R-02). |
| «Muestre el motivo de la corrección en la línea de tiempo» | El resumen no trae `correction_reason`; se ve en W3. Agregarlo a W1 sería otra brecha del contrato. |
