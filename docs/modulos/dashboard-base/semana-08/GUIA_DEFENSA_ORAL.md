# Guía de defensa oral — Semana 8

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Dashboard base UI/UX e implementación transversal · **Documento:** [semana-08-flujo-ux.md](semana-08-flujo-ux.md)

> «El personal clínico y el administrador entran al mismo dashboard, pero cada uno ve solo el menú y las tarjetas de su rol. Cada tarjeta carga y falla por su cuenta, “vacío” no se confunde con “error” y todo error se recupera sin salir del dashboard.»

**¿Por qué una opción sin permiso no aparece, en vez de verse deshabilitada?** Por R-02: el menú nunca ofrece algo que el backend rechazaría, y así no se revela qué secciones existen. Si alguien escribe la ruta a mano, W4 lo avisa sin sacarlo del dashboard (E6).

**¿Por qué no se reintenta solo?** Por R-08: un bucle automático carga el servidor cuando ya está fallando; el usuario decide con «Reintentar», que recarga solo esa tarjeta. **¿Qué datos protege?** Solo hay conteos (R-06), filtrados por el hospital de la sesión, y «Cerrar sesión» borra la sesión en equipos compartidos.
