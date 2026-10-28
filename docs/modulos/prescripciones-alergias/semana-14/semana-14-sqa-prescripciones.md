# Semana 14 — Confiabilidad y aseguramiento de la calidad (plan SQA) de Prescripciones

| | |
|---|---|
| **Curso** | Análisis de Sistemas II — Proyecto Final 2026 |
| **Módulo** | Prescripciones electrónicas con validación de alergias (ASII-15) |
| **Proceso** | «Creación de prescripción con verificación previa de alergias» |
| **Estudiante** | Boris Alexander Quiroa Orellana |

## Entregables

| Pedido | Dónde |
|---|---|
| Plan SQA con 5–7 métricas | [`plan-sqa-prescripciones.md`](plan-sqa-prescripciones.md): **7 métricas** con fuente, umbral, responsable y acción (§3), medidas sobre el código real |
| Matriz de riesgos | [`matriz-riesgos.md`](matriz-riesgos.md): 11 riesgos con probabilidad × impacto, control preventivo y detectivo, y responsable |
| Controles preventivos/detectivos | [Plan §4–§5](plan-sqa-prescripciones.md#4-controles-preventivos): 7 preventivos y 7 detectivos; los detectivos SQL, probados sobre el esquema real |
| Responsables | [Plan §2](plan-sqa-prescripciones.md#2-organización-y-responsables): desarrollador, QA, **farmacéutico clínico**, DBA, seguridad y líder técnico |
| Calendario de seguimiento | [Plan §8](plan-sqa-prescripciones.md#8-calendario-de-seguimiento): cadencias por commit, diaria, semanal, por release y mensual, más los hitos con fecha; [tablero](plantillas/tablero-seguimiento.csv) |

## 1. Las 7 métricas y su línea base

`php herramientas/medir-sqa.php` con PostgreSQL y mutaciones ([evidencia](evidencia/linea-base-sqa.txt)):

| ID | Atributo | Métrica | Valor | Umbral | Estado |
|---|---|---|---|---|:-:|
| M1 | Calidad | Pruebas en verde | 111/111, 0 omitidas | 0 fallan, 0 omitidas | 🟢 |
| M2 | Calidad | Mutation score | 100 % (35/35) | 100 % | 🟢 |
| M3 | **Seguridad clínica** | Sensibilidad en un corpus de alergias | **9/12** · especificidad 2/2 | 100 % | 🔴 |
| M4 | Seguridad | Accesos indebidos bloqueados | **11/12** sondas | 100 % | 🔴 |
| M5 | Confiabilidad | Bloqueo seguro e idempotencia | 4/4 | 100 % | 🟢 |
| M6 | Trazabilidad | Contrato = código y códigos con prueba | 4/4 guardas · 20/23 códigos | 100 % | 🟡 |
| M7 | Riesgo | Hallazgos de la revisión abiertos | 1 crítico · 2 mayores · 3 menores · 1 obs. | 0 críticos | 🔴 |

**Los tres rojos son los tres hallazgos más graves de la semana 13, ahora medidos:**
- **M3:** una alergia grave escrita «Amoxicilina trihidrato», «Amoxicilina (sal sódica)» o «Penicilina G» no se detecta (AR-03).
- **M4:** otro médico reusa la clave de dr-lopez y recibe 200 (AR-02).
- **M7:** los tokens demo siguen activos (AR-01).

**El ámbar de M6 lo encontró la propia medición:**
- `RUTA_NO_ENCONTRADA` y `METODO_NO_PERMITIDO`: las pruebas verifican el estado (404/405), no el código.
- `SERVICIO_NO_DISPONIBLE`: está en el contrato y **ningún código del servidor lo emite**.

Un plan cuya línea base sale toda en verde suele medir lo que ya se sabía. Cada rojo y cada ámbar tiene acción, responsable, fecha y verificación (plan §7).

## 2. Las métricas detectan defectos: comprobado

Se inyectó un defecto por copia del Micro-HIS y se comparó cada valor con el de la copia sin defecto ([evidencia](evidencia/deteccion-defectos.txt)):

| Defecto | Lo detecta |
|---|---|
| La coincidencia por nombre deja de normalizar | M3 (9 → 7 de 12) y M1 |
| Fuga entre hospitales en el repositorio | M4 (11 → 9 de 12) y M1 |
| El Expediente caído se lee como «sin alergias» | M5 (4 → 2 de 4) y M1 |
| Un código de error en el contrato sin prueba | **solo M6** (20/23 → 20/24) |

**4 de 4 detectados.** Se compara el valor y no el color porque M3 y M4 ya estaban en rojo: un defecto nuevo no les cambiaría el color, pero sí el valor.

## 3. Riesgos

- **2 riesgos altos** ([matriz](matriz-riesgos.md)):
  - **RK-01**, falso negativo por alérgeno escrito de otra forma (**9**: probabilidad e impacto máximos);
  - **RK-02**, tokens demo (6).
- **El fail-open, la manipulación directa en la base y la fuga entre hospitales** quedan en nivel medio, porque tienen controles en las dos capas. Además, M4 y M5 demostraron que los detectan (D2 y D3).

## 4. Un error de la propia medición

La primera versión de M5 salió en **rojo**: «emisión con el Expediente caído → 503 y nada emitido». Antes de registrarlo como hallazgo lo reproduje aparte, y la emisión sí respondía 503 sin emitir. El error era de la herramienta: contaba las prescripciones **después** de los 50 reintentos de la comprobación siguiente, que sí agregan una. Corregido, M5 da 4/4. Queda anotado porque una métrica también puede dar falsos rojos.

## 5. Cómo se verifica

```bash
# desde docs/modulos/prescripciones-alergias/semana-14
PRESCRIPCIONES_DB_DSN=... php herramientas/medir-sqa.php   # 7 métricas; código 1 mientras M3, M4 o M7 estén en rojo
php herramientas/probar-deteccion.php                       # 4 defectos inyectados, 0 sin detectar
psql -f herramientas/controles-detectivos.sql               # controles del DBA (solo lectura)
```

## 6. Limitaciones

- **Los responsables distintos del desarrollador están «por designar»,** incluido el farmacéutico que debe ser dueño del corpus. El plan necesita personas reales para funcionar.
- **El corpus de M3 tiene 14 casos escritos por el autor:** mide los defectos que el autor ya imaginó. Su valor real llega cuando farmacia agregue casos del Expediente real (cadencia mensual).
- **M3, M4 y M5 se miden con datos sembrados en memoria.** En producción, C-D4 y C-D6 dan la exposición real, y M4 debería repetirse contra el entorno desplegado.
- **No hay un CI configurado en el repositorio para el Micro-HIS:** las cadencias «por commit» son el diseño; hoy se ejecutan a mano.
