# Guía de defensa oral — Semana 13

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Catálogo de salas, wards y camas · **Documento:** [semana-13-revision-tecnica.md](semana-13-revision-tecnica.md)

> «Es una inspección del Micro-HIS congelado en un commit de `develop`, con seis fases, roles separados y un checklist de 15 ítems, cada uno atado a una regla R-xx o a un caso de uso. Antes de convocar, el autor hace su autorrevisión con un script que reproduce cada hallazgo; la revisión solo termina cuando no queda ningún hallazgo crítico ni mayor abierto.»

**¿Por qué el autor no modera?** Porque defendería su propio código y decidiría si su trabajo sale de la revisión. El moderador es neutral y corta las discusiones de solución: en la reunión se encuentran errores, no se corrigen.

**¿Por qué un script para los hallazgos?** Porque un hallazgo sin evidencia se discute por opinión. `verificar-entrada.php` muestra, por ejemplo, que «A» y « A» se registran las dos (H-03), y al corregirlo la línea cambia a «no reproducido». Eso sirve también para el criterio de salida S-05.

**¿Por qué H-01 es mayor y no crítico?** Porque hoy el Micro-HIS no tiene datos de varios hospitales que puedan cruzarse. Sería crítico en cuanto el catálogo atienda a más de un hospital, y por eso se pospone con fecha (semana 15, RNF-01) y no se descarta.
