# Matriz decisión → RF/RNF → evidencia (segundo parcial)

Continúa la numeración DEC-NN de la semana 6 (DEC-01 a DEC-13). Las de este parcial cubren componentes, UX, accesibilidad y movilidad.

| ID | Decisión | RF / RNF / RN | Evidencia verificable |
|---|---|---|---|
| DEC-14 | Centralizar «cama disponible» en `DisponibilidadCamas` | RF-03, RN-03, RN-04, RNF-06 | `semana-07/README.md` §3 y diagramas antes/después |
| DEC-15 | Componentes de UI sin reglas: solo muestran estados según el código de error de la API | RNF-02, RF-05 | `semana-07/README.md` §2 (contratos); `semana-05/contrato-api.md` §3 |
| DEC-16 | Paso de confirmación antes de registrar | RF-04, RNF-07 (3.3.4) | Prototipo, paso 3; `capturas/*-03-confirmar.png`; hallazgo H-04 → B-01 |
| DEC-17 | Validar el formato del código antes de buscar camas | RF-02, CA-02 | Prototipo: `recorrido.txt` («aria-invalid= true»); hallazgo H-01 → B-02 |
| DEC-18 | Error asociado al campo y foco gestionado | RNF-07 (3.3.1, 2.4.3) | `recorrido.txt` (foco = H1 o `#aviso`, nunca BODY); H-02 y H-03 → B-03 y B-04 |
| DEC-19 | El aviso de conflicto nombra la cama y ofrece la lista actualizada | RF-05, CU-05, RN-01 | `capturas/*-05-error-critico.png`; prueba «Web: cama tomada responde 409…» |
| DEC-20 | Sin doble envío en Confirmar | RN-02, RNF-08 | `recorrido.txt` («disabled= True texto= Guardando…»); H-05 → B-06 |
| DEC-21 | Médico sin acciones de admitir; trazabilidad solo para el supervisor | RN-06, RF-08, CA-08, CA-11 | Prototipo con el selector de rol; pruebas «MEDICO: no puede admitir (403)…» y «Trazabilidad por API…» |
| DEC-22 | Colores con contraste AA verificado | RNF-07 (1.4.3, 1.4.11) | `semana-09/contraste.php` → `evidencia/salida-contraste.txt` |
| DEC-23 | Móvil: una decisión por pantalla, tarjetas y áreas de 44 px o más | RNF-07 | `semana-10/README.md` §3; `capturas/movil-*.png` sin desplazamiento horizontal |
| DEC-24 | Ante un corte de red, no reintentar el POST solo y verificar el estado | RN-02, RNF-08 | `semana-10/README.md` E-M1; `semana-05/contrato-api.md` §4 |
| DEC-25 | Solo el código de paciente en pantallas y registros | RNF-03 | Wireframes W-01 a W-06; prueba «X-Request-Id inválido…» (registro sin `PAC-`) |
