# Semana 14 — Confiabilidad y aseguramiento de la calidad (plan SQA) de Auditoría

| | |
|---|---|
| **Curso** | Análisis de Sistemas II — Proyecto Final 2026 |
| **Módulo** | Gobernanza y auditoría de movimientos del sistema |
| **Proceso** | «Registro inmutable y consulta filtrada de movimientos por usuario, módulo y fechas» |
| **Estudiante** | Boris Alexander Quiroa Orellana |

## Entregables

| Pedido | Dónde |
|---|---|
| Plan SQA con 5–7 métricas | [`plan-sqa-auditoria.md`](plan-sqa-auditoria.md): **7 métricas** con fuente, umbral, responsable y acción (§3), medidas sobre el código real |
| Matriz de riesgos | [`matriz-riesgos.md`](matriz-riesgos.md): 10 riesgos con probabilidad × impacto, control preventivo y detectivo, y responsable |
| Controles preventivos/detectivos | [Plan §4–§5](plan-sqa-auditoria.md#4-controles-preventivos): 6 preventivos y 7 detectivos; los detectivos SQL, probados sobre el esquema real |
| Responsables | [Plan §2](plan-sqa-auditoria.md#2-organización-y-responsables): desarrollador, QA, DBA, seguridad y líder técnico |
| Calendario de seguimiento | [Plan §8](plan-sqa-auditoria.md#8-calendario-de-seguimiento): cadencias por commit, diaria, semanal, por release y mensual, más los hitos con fecha; [tablero](plantillas/tablero-seguimiento.csv) |

## 1. Las 7 métricas y su línea base

`php herramientas/medir-sqa.php` con PostgreSQL ([evidencia](evidencia/linea-base-sqa.txt)):

| ID | Atributo | Métrica | Valor | Umbral | Estado |
|---|---|---|---|---|:-:|
| M1 | Calidad | Pruebas en verde | 98/98, 0 omitidas | 0 fallan, 0 omitidas | 🟢 |
| M2 | Calidad | Mutation score | 100 % (26/26) | 100 % | 🟢 |
| M3 | Confiabilidad | Cadenas íntegras | 3/3 · 4,2 ms por 1 000 movimientos | 100 % | 🟢 |
| M4 | Seguridad | Fugas bloqueadas | 12/12 sondas | 100 % | 🟢 |
| M5 | Trazabilidad | Contrato = código y códigos con prueba | 4/4 guardas · 16/16 códigos | 100 % | 🟢 |
| M6 | Riesgo | Hallazgos de la revisión abiertos | 1 crítico · 2 mayores · 2 menores | 0 críticos | 🔴 |
| M7 | Confiabilidad | Capacidad de la verificación (1 año) | 588 MB necesarios | ≤ 64 MB (50 % de 128) | 🔴 |

**Por qué hay dos en rojo, y por qué eso es correcto:** las dos vienen de la revisión de la semana 13.
- **M6:** los tokens demo siguen activos (AR-01).
- **M7:** la verificación carga toda la cadena (AR-02).

Un plan cuya línea base sale toda en verde suele medir lo que ya se sabía. Cada rojo tiene acción, responsable, fecha y verificación (plan §7).

## 2. Las métricas detectan defectos: comprobado

Se inyectó un defecto por copia del Micro-HIS y se midió con el mismo script ([evidencia](evidencia/deteccion-defectos.txt)):

| Defecto | Lo detecta |
|---|---|
| Fuga entre tenants en el detalle | M4 (11/12) y M1 |
| La cadena salta una secuencia | M3 (1/3) y M1 |
| Un código de error en el contrato sin prueba | **solo M5** (16/17) |
| Un emisor registra en otro hospital | M4 (11/12) y M1 |

**4 de 4 detectados.** El tercero muestra por qué existe M5: las guardas del contrato miran en una sola dirección.

## 3. Riesgos

- **3 riesgos altos:**
  - RK-01, tokens demo (6);
  - RK-02, la verificación no escala (9);
  - RK-05, recurso libre (6).

  Los tres salen de la autorrevisión y tienen fecha de cierre.
- **Los riesgos de manipulación directa en la base y de fuga entre tenants** quedan en nivel medio, porque tienen controles en las dos capas (preventiva y detectiva). Ver la [matriz](matriz-riesgos.md).

## 4. Cómo se verifica

```bash
# desde docs/modulos/gobernanza-auditoria/semana-14
AUDITORIA_DB_DSN=... php herramientas/medir-sqa.php      # 7 métricas; exit 1 mientras M6/M7 estén en rojo
php herramientas/probar-deteccion.php                     # 4 defectos inyectados, 0 sin detectar
psql -f herramientas/controles-detectivos.sql             # controles diarios del DBA (solo lectura)
```

## 5. Limitaciones

- **Los responsables distintos del desarrollador están «por designar»:** el plan necesita personas reales para funcionar.
- **El volumen de M7 (2 000 movimientos por día) es un supuesto.** C-D5 lo calibra en la primera semana de producción.
- **M3 y M4 se miden en el CI con datos sembrados.** En producción, M3 sale de la verificación diaria del DBA, y M4 debería repetirse contra el entorno desplegado.
- **No hay un CI configurado en el repositorio para el Micro-HIS:** las cadencias «por commit» son el diseño; hoy se ejecutan a mano.
