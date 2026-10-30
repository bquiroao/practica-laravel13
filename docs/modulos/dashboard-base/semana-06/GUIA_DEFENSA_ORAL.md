# Guía de defensa oral — Semana 6

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard base UI/UX e implementación transversal · **Documentos:** [presentación](semana-06-diapositivas.md) y [matriz y registro del cambio](semana-06-defensa-arquitectura.md)

> «El dashboard tiene un hilo trazable: el usuario entra, ve sus tarjetas y navega, R-02 limita todo a su rol, el contrato lo expone y cada capa hace una parte. El cambio práctico fue la navegación: una ruta escrita a mano ya no salta el permiso, porque responde 403 E6, distinto del 404 de lo que no existe. La prueba de contrato de la semana 5 detectó la operación nueva sin documentar, así que el contrato subió a 0.6.0.»

**¿Qué principio SOLID defiendes y dónde se ve?** SRP: el catálogo es la única fuente de qué ve cada rol. `Catalogo::destino` usa el mismo `MENU` que arma el menú, así que el menú y la navegación nunca pueden contradecirse.

**¿Por qué 403 E6 y no 404 para un módulo sin permiso?** Porque el cliente debe decir «no tienes acceso» y no «no existe». Lo que no existe sí responde 404 NF. Mezclarlos es justo el problema de H-03 con las tarjetas.

**¿Qué demuestra que la prueba de contrato fallara?** Que funciona como red de seguridad. Agregué una acción al controlador y la prueba lo marcó como «sin documentar: navegar» antes de que el contrato quedara desactualizado. Lo corregí documentando la operación, E6 y NF.

**¿Por qué la persistencia no cambió?** Porque navegar no lee datos: es una regla sobre el rol. Eso confirma que las capas están bien separadas.

**¿Qué falta?** Que los permisos salgan del JWT y de RBAC (H-01, semana 15), y que una tarjeta inexistente responda 404 (H-03).
