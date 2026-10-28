# Guía de defensa oral — Semana 13

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Citas médicas y agenda · **Documento:** [semana-13-revision-tecnica.md](semana-13-revision-tecnica.md)

> «Es una inspección del Micro-HIS de citas congelado en un commit de `develop`, con seis fases, roles separados y un checklist de 15 ítems, cada uno atado a una regla R-xx, a un caso de uso o a un RNF. La autorrevisión encontró cinco hallazgos mayores, así que, según el criterio S-04, la inspección se repite completa después de corregirlos.»

**¿Cómo se demuestra una carrera sin dos computadoras?** Se busca el punto exacto donde ocurre: entre `cruce()` y `guardar()`. Un repositorio de prueba mete ahí la otra solicitud y quedan dos citas del mismo médico en el mismo horario (H-02). Una carrera real ocurre en ese mismo punto.

**¿Por qué no basta con la exclusión de la base (RNF-04)?** Porque evita la cita doble, pero el error de la base llega como `PDOException` y se traduce a 503 E8: el usuario lee «la agenda no está disponible» en vez de «otra persona tomó el horario» (E6). Se probó en una copia con un trigger.

**¿Por qué el autor no modera?** Porque defendería su código y decidiría si su trabajo sale de la revisión. El moderador corta las discusiones de solución: en la reunión se encuentran errores, no se corrigen.
