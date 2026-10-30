# Matriz decisión → RF/RNF → evidencia (segundo parcial)

Rutas relativas a `docs/modulos/catalogo-medicamentos/`.

| ID | Decisión | RF / RNF / RN | Evidencia |
|---|---|---|---|
| D-UX-01 | Un solo acceso a la API en el frontend (`useCatalogoApi`) que traduce el formato único de error | RNF-05, RF-02 | `semana-07/contratos-componentes.md` §2; `semana-05/contrato-api.md` §5 |
| D-UX-02 | El tenant y el rol salen de la sesión; la UI solo oculta acciones | RNF-01, RNF-02 | `semana-08/README.md` §1; prototipo con CONSULTA_CLINICA (`semana-11/README.md` §4) |
| D-UX-03 | «Vigente al …» en toda lista y detalle | RF-04, RN-03 | W2, M2; `semana-11/capturas/desktop-06-encontrado.png` |
| D-UX-04 | Errores 422 juntos, resumen con enlaces y foco en el resumen | RF-02, RNF-07 (WCAG 2.4.3, 3.3.1) | `semana-09/hallazgos.md` H-02, H-05; prototipo: foco = `resumen` |
| D-UX-05 | 409 `DUPLICADO` como error recuperable con enlace al existente | RF-03, RN-04 | W4; `semana-11/capturas/desktop-07-error-duplicado.png` |
| D-UX-06 | Eliminar con confirmación escrita y bloqueo si fue usado | RF-08, RN-02 (WCAG 3.3.4) | W6, M4; `semana-11/capturas/movil-08-eliminar-bloqueado.png` |
| D-UX-07 | Confirmar la nueva versión diciendo qué se cierra y cuándo | RF-05, RN-01 | W5; `semana-07/refactor-antes-despues.md` |
| D-ACC-01 | Foco `#1d4ed8` de 3 px | RNF-07 (WCAG 1.4.11) | `semana-09/evidencia/calcular-contraste.php`: 6,70:1 |
| D-ACC-02 | «Saltar al contenido», `aria-required`, etiquetas legibles | RNF-07 (WCAG 2.4.1, 3.3.2) | `semana-11/README.md` §3 (B-07, B-09, B-10) |
| D-MOV-01 | Tarjetas en lugar de tabla a ≤ 430 px | RNF-07 (WCAG 1.4.10) | `semana-10/README.md` §2; sin desplazamiento horizontal en 8 pantallas a 375 px |
| D-MOV-02 | Ante un envío sin respuesta, consultar estado antes de reenviar | RF-06, RNF-04 | `semana-10/README.md` escenario E2; `semana-05/contrato-api.md` §6 |
| D-MOV-03 | Copia de la última búsqueda con su hora cuando no hay red | RF-04, RN-03 | M5; escenario E1 |

## Trazabilidad inversa (RF/RNF → decisiones)

| Requisito | Decisiones |
|---|---|
| RF-02 validar | D-UX-01, D-UX-04 |
| RF-03 duplicados | D-UX-05 |
| RF-04 búsqueda | D-UX-03, D-MOV-03 |
| RF-05 versión | D-UX-07 |
| RF-06 baja | D-MOV-02 |
| RF-08 eliminar sin uso | D-UX-06 |
| RNF-01 / RNF-02 | D-UX-02 |
| RNF-07 accesibilidad | D-UX-04, D-ACC-01, D-ACC-02, D-MOV-01 |
