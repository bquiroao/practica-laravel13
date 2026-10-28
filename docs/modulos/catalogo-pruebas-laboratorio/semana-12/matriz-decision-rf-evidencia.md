# Matriz decisión → RF/RNF → evidencia

| # | Decisión | RF / RNF | Evidencia |
|---|---|---|---|
| D-B01 | Componentes de frontend que no deciden vigencia ni reglas; solo muestran lo que da la API | RF-05, RF-06, RNF-05 | S7 `contratos-entrada-salida.md`; S7 refactor antes/después |
| D-B02 | Regla de vigencia y listas de muestra definidas una sola vez | RF-02, RF-05, RNF-05 | S7 `refactor-despues.png`; prueba T-09 (mismo contrato en PDO e InMemory) |
| D-B03 | Confirmación obligatoria antes de guardar, con aviso de cierre de la versión anterior | RF-04 | S8 W-04; S11 captura `*-03-confirmar.png` |
| D-B04 | Errores por campo más un resumen anunciado y foco en el resumen | RF-01, RF-02, RF-03, RNF-04 | S9 H-02; S11 captura `*-05-error-vigencia-superpuesta.png` |
| D-B05 | Listas cerradas para sección, tipo de muestra y conservación | RF-02, RNF-04 | S9 H-01; prototipo (tipo de muestra como lista) |
| D-B06 | Botones de alta visibles solo con permiso; el servidor vuelve a verificar | RF-07, RNF-03 | S8 user flows; S11 cambio de rol; T-14 |
| D-B07 | Tarjetas por debajo de 768 px; alta en tres pasos en móvil | RNF-04 | S10 reglas de breakpoint; S11 capturas móviles |
| D-B08 | Recuperación sin conexión con datos fechados y reintento | RNF-01, RNF-04 | S10 escenarios 1 y 2; W-05 y M-04 |
| D-B09 | Contraste mínimo 4.5:1 y foco visible de 3 px | RNF-04 | S9 checklist (1.4.3); S11 README (valores calculados) |
| D-B10 | El catálogo no muestra datos de pacientes | RNF-03 | S8 sección «Protección de datos» |
