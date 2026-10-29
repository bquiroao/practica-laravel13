# Guía de defensa oral — Semana 5

**Estudiante:** Josué David Morales Ramírez · **Módulo:** Contratos API / Centro de documentación y manuales por rol · **Documento:** [semana-05-cliente-servidor.md](semana-05-cliente-servidor.md)

> «El centro de documentación es un servidor HTTP con seis operaciones, descritas en un OpenAPI que pasa el lint. Una prueba de contrato hace pasar 16 escenarios reales por las rutas y el controlador. Evalué extraerlo como servicio de documentación con cuatro criterios medibles; hoy hay 9 contratos en `develop` y ninguno se publica por API, así que sigue como módulo.»

**¿Por qué el propio contrato de este módulo importa más que los otros?** Porque este módulo es el que revisa los contratos de los demás. Si su OpenAPI no pasara el lint, no tendría autoridad para exigírselo a nadie. El primer lint encontró un error real en una descripción con coma, y se corrigió antes de entregar.

**¿Qué cambia en la seguridad si se extrae?** Que el usuario y los roles salen del token y no de cabeceras que escribe el cliente. Hoy cualquiera puede escribir `X-Roles: admin` (hallazgo H-01). Con el token, la audiencia la decide RBAC, y lo ajeno sigue respondiendo 404, igual que lo inexistente.
