# Catálogo de pruebas de laboratorio — Semana 15

**Tema:** pruebas unitarias, caja blanca y caja negra
**Tarea:** diseñar y ejecutar pruebas trazadas a RF/RNF (unitarias de reglas, caja blanca de ramas del flujo y caja negra de equivalencia, límites, permisos y errores) sin probar módulos ajenos

Pruebas nuevas en el Micro-HIS:

| Archivo | Tipo | Cantidad |
|---|---|---|
| `micro-his/pruebas/PruebasUnitarias.php` | Unitarias | 4 (U-01 a U-04) |
| `micro-his/pruebas/PruebasCajaBlanca.php` | Caja blanca | 5 (CB-01 a CB-05) |
| `micro-his/pruebas/PruebasCajaNegra.php` | Caja negra | 8 (CN-01 a CN-08) |

Se suman a las T-01 a T-16 de las semanas 3 a 5. Todas usan solo el Catálogo: InMemory, la API del Micro-HIS o la base `micro_lab_test`.

## 1. Matriz RF/RNF → prueba

| Requisito | Unitarias | Caja blanca | Caja negra | Anteriores |
|---|---|---|---|---|
| RF-01 Datos generales | U-04 | — | CN-05 | T-01, T-11 |
| RF-02 Requisitos de muestra | U-03 | — | CN-01, CN-02, CN-06 | T-04, T-13 |
| RF-03 Vigencia | — | CB-05 | CN-03 | T-04 |
| RF-04 Versionado sin superposición | U-02 | CB-01, CB-02, CB-03, CB-04 | — | T-02, T-03, T-15 |
| RF-05 Consulta de vigentes | U-01 | — | CN-05, CN-08 | T-07, T-09, T-16 |
| RF-06 Detalle o no vigente | U-01 | — | — | T-06, T-16 |
| RF-07 Permisos y hospital | — | — | CN-04 | T-09, T-14 |
| RF-08 Quién registró cada versión | — | — | — | T-01 (usuario en la versión) |
| RNF-01 Integridad | — | CB-03 | CN-06, CN-07 | T-05, T-08 |
| RNF-03 Seguridad | — | — | CN-04 | T-14 |
| RNF-05 Mantenibilidad | — | — | — | T-09, T-12 |

**Sin prueba automática en este módulo:** RNF-02 (rendimiento, requiere medición con 500 pruebas), RNF-04 (accesibilidad, se evaluó en la semana 9) y RNF-06 (observabilidad: el registro `[catalogo-lab]` se revisa en la consola al correr T-13 a T-16).

## 2. Diseño de caja blanca

`RegistrarPrueba::ejecutar` tiene cuatro caminos: sin versión previa (CB-01), con versión previa que no se superpone (CB-02), superpuesta con fecha desde igual o anterior (CB-03) y superpuesta con fecha desde posterior (CB-04). Con esas cuatro pruebas se recorren todas sus ramas. CB-05 cubre las cuatro condiciones de `Vigencia::errores` y el caso válido en el límite (desde = hasta = hoy).

## 3. Diseño de caja negra

| Técnica | Pruebas | Clases o valores |
|---|---|---|
| Partición de equivalencia | CN-01 | Tipo de muestra en la lista (`suero`) y fuera de ella (`saliva`) |
| Valores límite | CN-02, CN-03, CN-06, CN-07 | Volumen 0 / 0.01 / 10000 mL; fecha ayer / hoy; nombre de 151 caracteres |
| Permisos | CN-04 | Cinco tokens × POST y cuatro × GET |
| Errores | CN-05, CN-08 | Cuerpo no JSON, ruta inexistente, fecha 2027-02-30, búsqueda con `%` |

## 4. Comando y resultados

```powershell
cd docs/modulos/catalogo-pruebas-laboratorio/micro-his
docker compose up -d
docker compose ps                      # esperar healthy
php scripts/crear-esquema.php
php pruebas/ejecutar.php --evidencia=../semana-15/evidencia/salida-pruebas.txt
```

Resultado esperado: **34 pruebas, 31 correctas y 3 fallidas**. Las tres fallidas son CN-06, CN-07 y CN-08, marcadas `[DEFECTO D-NN]`.

Resultado obtenido: ver [`evidencia/salida-pruebas.txt`](evidencia/salida-pruebas.txt).

Capturas para `evidencia/`:
- `consola-pruebas.png`: final de la salida con la línea «Resultado».
- `api-403.png`: `Invoke-RestMethod` con `tok-medico` haciendo POST (respuesta 403).

## 5. Registro de defectos

| ID | Defecto | Cómo se detectó | Severidad | Estado | Corrección propuesta |
|---|---|---|---|---|---|
| D-01 | La vista MVC fallaba con «Cannot modify readonly property» porque `extract()` recibía una propiedad readonly | Al probar el front controller durante la construcción de la semana 4 | Mayor | Corregido antes del PR de la semana 4 (copia del arreglo en `Vista::render`); T-10 ahora renderiza la vista | — |
| D-02 | Un volumen de 10000 mL o más pasa la validación, pero la columna `numeric(6,2)` lo rechaza: responde 500 en vez de 422 | CN-06 | Mayor | Abierto | Agregar en `RequisitoMuestra::errores` el máximo de 9999.99 mL |
| D-03 | Textos más largos que su columna (nombre > 150, contenedor > 80, instrucciones > 300) responden 500 en vez de 422 | CN-07 | Mayor | Abierto | Validar longitudes máximas en `LabTest::validar` y `RequisitoMuestra::errores` |
| D-04 | Buscar `%` o `_` en PDO actúa como comodín (encuentra todo) y en InMemory como texto literal (no encuentra nada): los adaptadores no cumplen el mismo contrato | CN-08 | Menor | Abierto | Escapar `%`, `_` y `\` antes del `ILIKE` en `PdoLabTestRepository::buscarVigentes` |

D-02 y D-03 rompen RNF-01 porque el usuario recibe un error de servidor por un dato que debía rechazarse con su mensaje. Se registran en el plan SQA (métrica M-03) y en la revisión técnica (ítem C-06).
