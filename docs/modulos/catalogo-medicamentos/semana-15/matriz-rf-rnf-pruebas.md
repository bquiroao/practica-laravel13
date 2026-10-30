# Matriz RF/RNF → prueba

Archivos en `micro-his/pruebas/`. Tipo: **U** unitaria · **CB** caja blanca · **CN** caja negra ·
**I** integración con base · **C** contrato PDO = InMemory · **A** API. Solo se prueba este módulo.

| Requisito | Qué se verifica | Pruebas (semana 15 en negrita) | Tipo | Resultado |
|---|---|---|---|---|
| RF-01 alta | Código siguiente, versión 1, vigencia desde hoy | `casos-de-uso` CA-01; `persistencia-pdo` CA-01; `api` «POST alta 201»; **`semana15-reglas` [DEFECTO D-03]** | U, I, A | Pasa · **D-03 abierto** |
| RF-02 validar | Todos los errores a la vez; límites | `dominio` RN-05; **CN-01**, **CN-02**; **[DEFECTO D-01]** | U, CN | Pasa · **D-01 abierto** |
| RF-03 duplicados | Clave normalizada; otro código; otra fecha | `casos-de-uso` CA-04, CA-05; **U-02**; **CB-02 C6** | U, CB | Pasa |
| RF-04 búsqueda | Vigentes a fecha, límites de `q`, `%`/`_` | `contrato` casos 1–4; `persistencia-pdo` CA-06..08; **CN-03**; **CB-03** | C, I, CN, CB | Pasa |
| RF-05 versión | Cierra la anterior; caminos de error | `dominio` CA-09, CA-10; **CB-02 (7 caminos)**; **U-04** | U, CB | Pasa |
| RF-06 baja | Fecha y motivo; no se repite | `dominio` RN-02; **CN-08** | U, CN | Pasa |
| RF-07 historial | Orden por versión; permiso | `contrato` caso 5; **CN-04** | C, CN | Pasa |
| RF-08 eliminar sin uso | 409 `EN_USO`; borra solo ese hospital | `casos-de-uso` CA-11; `persistencia-pdo` CA-11; **CN-08** | U, I, CN | Pasa |
| RF-09 consulta por código | Vigente a una fecha; 404 en otro hospital | `contrato` caso 5; **CN-05** | C, CN | Pasa |
| RNF-01 permisos | Matriz de 4 roles | `api` RNF-01; **CN-04** | A, CN | Pasa |
| RNF-02 tenant | Del token; 404 en otro hospital | `persistencia-pdo` RNF-02; `api` RNF-02; **CN-05** | I, A, CN | Pasa |
| RNF-03 rendimiento | p95 ≤ 300 ms con 5 000 versiones | `scripts/medir-busqueda.php` (medición, no prueba automática) | Medición | p95 = 6,3 ms al preparar la entrega |
| RNF-04 integridad | Restricciones de la base → 409 | `persistencia-pdo` RNF-04 (2); `contrato` caso 6; **CB-01** | I, C, CB | Pasa |
| RNF-05 trazabilidad | `X-Request-Id` en toda respuesta | `api` observabilidad; **CN-06**; **[DEFECTO D-02]** | A, CN | Pasa · **D-02 abierto** |
| RNF-06 mantenibilidad | Dependencias; mismo contrato en dos adaptadores | `arquitectura` (3); `contrato` (7 × 2); **U-05** | U, C | Pasa |
| RNF-07 accesibilidad | Foco, contraste, errores, móvil | Fuera del Micro-HIS: Playwright sobre el prototipo (`semana-11/README.md` §4) y `calcular-contraste.php` | Manual/automatizada | Cumple en el prototipo |
| RNF-08 sin datos técnicos en errores | 5xx sin SQL ni rutas | `api` CA-13; **CN-07** | A, CN | Pasa |

## Resumen de la ejecución

| Medida | Valor al preparar la entrega |
|---|---|
| Pruebas totales | 88 (69 anteriores + 19 de la semana 15) |
| Correctas | 85 |
| Fallas | 0 |
| Defectos abiertos documentados (`[DEFECTO D-NN]`) | 3 (D-01, D-02, D-03) |
| Trazabilidad M-02 (`semana-14/evidencia/medir-trazabilidad.php`) | 25 de 27 = 93 % (meta ≥ 90 %); faltan RNF-03 y RNF-07, que se verifican con medición y con Playwright |
