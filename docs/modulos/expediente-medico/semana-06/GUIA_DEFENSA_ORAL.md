# Guía de defensa oral — Semana 6

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Expediente médico electrónico base · **Documentos:** [presentación](semana-06-diapositivas.md) y [matriz y registro del cambio](semana-06-defensa-arquitectura.md)

> «El expediente tiene un hilo trazable: admisión abre y el profesional consulta, R-05, R-06 y R-08 controlan el acceso a datos sensibles, el contrato lo expone y cada capa hace una parte. El cambio práctico fue la auditoría: un intento de ver la historia de otro hospital con la base central caída ahora deja su evento E8. Entró en un solo método de la aplicación, sin tocar el dominio, la persistencia ni el contrato.»

**¿Qué principio SOLID defiendes y dónde se ve?** DIP: `ServicioExpediente` depende de cuatro puertos. HOSPITAL, CENTRAL, la auditoría y el reloj se inyectan, así que la política no sabe si hay PDO o memoria. Por eso el cambio vivió en la política y funcionó igual con los dos adaptadores.

**¿Por qué auditar un acceso que falló?** Porque la auditoría responde «quién intentó ver qué». Un intento de acceso a datos sensibles es información aunque la base central no haya contestado. RNF-04 dice que la auditoría no se omite en ningún camino.

**¿Qué pasa si la que se cae es la propia auditoría?** Se intenta registrar y, si falla, se relanza el error original: el cliente sigue viendo 503 E8 sin detalles técnicos. No se puede auditar en una base caída, pero tampoco se oculta el problema.

**¿Por qué no lo resolviste en la persistencia?** Porque saber qué operación se intentó y con qué resultado es una decisión de la política, no del adaptador. El adaptador solo guarda el evento.

**¿Qué falta?** Que la auditoría sea inmutable en el Micro-HIS (H-04), comprobar el rol al abrir (H-02) y el alcance del consentimiento (H-03).
