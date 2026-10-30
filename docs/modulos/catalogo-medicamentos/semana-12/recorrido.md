# Recorrido navegable para la defensa (8 minutos)

Abrir `../semana-11/prototipo/index.html` en Chrome. Ventana a 1280 px; para la parte móvil usar
las herramientas de desarrollo (F12 → modo dispositivo, 375×760).

| Min. | Paso | Qué decir (decisión) | Qué se ve | RF/RNF |
|---|---|---|---|---|
| 0:00 | Abrir `#/buscar`, pulsar Tab | Primer Tab: «Saltar al contenido» (B-10) | Enlace visible con foco azul 6,70:1 | RNF-07 |
| 0:30 | Buscar «lorat» | Siempre se dice «vigente al …»; vacío con salida | Estado vacío + «Registrar medicamento» | RF-04 |
| 1:15 | Registrar → enviar vacío | Todos los errores a la vez; foco en el resumen con enlaces (B-02, B-03) | 7 errores enlazados | RF-02, RNF-07 |
| 2:15 | Completar Loratadina 10 mg tableta → Registrar | Carga visible, botón deshabilitado; éxito con `role="status"` | «Registrado MED-000107» y detalle | RF-01 |
| 3:00 | Buscar «loratadina» | El nuevo aparece; búsqueda sin acentos en el prototipo (B-05) | Tarjeta MED-000107 | RF-04 |
| 3:30 | **Error crítico:** registrar amoxicilina 500 mg cápsula oral | Unicidad (RN-04): error recuperable con «Ver» y «Corregir» | 409 con MED-000102 | RF-03 |
| 4:30 | Abrir MED-000101 → «Eliminar registro erróneo» | RN-02: no se borra lo usado; se ofrece la baja | Bloqueado → «Ir a dar de baja» | RF-08 |
| 5:15 | Dar de baja MED-000106 con fecha 2026-01-01 | Mensaje con la salida correcta (B-06) | «Si el registro empezó hoy, use Eliminar…» | RF-06 |
| 6:00 | Cambiar rol a CONSULTA_CLINICA | La UI oculta; el servidor decide (403) | Sin historial ni acciones; `#/nuevo` = «Sin permiso» | RNF-01 |
| 6:45 | Modo dispositivo 375 px | Tarjetas, versión vigente primero, hoja inferior | Sin desplazamiento horizontal | RNF-07, S10 |
| 7:30 | Cierre | Riesgos y cambio solicitado | Diapositivas 6 y 7 | — |

## Plan B

Si el navegador del aula no abre archivos locales con JavaScript, usar las capturas de
`../semana-11/capturas/` en el mismo orden (desktop 01–08 y móvil 05 y 08).

## Datos para la demostración (ficticios)

| Código | Medicamento | Estado |
|---|---|---|
| MED-000101 | Paracetamol 500 mg tableta | Vigente; usado en la prescripción ficticia PRE-000231 |
| MED-000102 | Amoxicilina 500 mg cápsula | Vigente (sirve para el duplicado) |
| MED-000104 | Ibuprofeno 400 mg tableta | Versión 1 cerrada el 2026-07-01; versión 2 vigente |
| MED-000105 | Omeprazol 20 mg cápsula | Dado de baja el 2026-09-01 |
