# Guía de defensa oral — Semana 13

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** RBAC · **Documento:** [semana-13-revision-tecnica-rbac.md](semana-13-revision-tecnica-rbac.md)

## 1. Resumen en 60 segundos

> «Preparé una inspección formal del módulo RBAC, con roles separados, preparación individual, reunión moderada y retrabajo verificado. Elegí inspección y no un recorrido informal porque este código decide quién accede a datos clínicos.
>
> El paquete son 3069 líneas del Micro-HIS. Lo partí en dos sesiones para no pasar de unas 1500 líneas por reunión: una de dominio e integridad, otra de contrato, permisos, tenant y dependencias.
>
> El checklist tiene 15 ítems. Cada uno es una afirmación de sí o no, con dónde mirar y cómo verificarlo.
>
> Los criterios de entrada no son una lista para marcar: un script los comprueba, y la primera vez falló porque todavía no había checklist.
>
> Antes de convocar hice la autorrevisión con el mismo formato y encontré 7 hallazgos reales; el más serio es que la aplicación siempre arranca con tokens de demostración. Los dejé abiertos a propósito para que el equipo los confirme en la reunión.»

## 2. Orden sugerido de la exposición

1. **Tipo y objetivo (plan §1):** por qué inspección; las 5 preguntas P1–P5.
2. **Paquete y sesiones (§2):** 3069 líneas → 2 sesiones; qué queda fuera.
3. **Roles (§3):** qué hace y qué **no** hace cada uno; el autor nunca modera.
4. **Checklist:** recorrer 3 ítems (RT-03, RT-09, RT-10) y mostrar «cómo se verifica».
5. **Criterios de entrada y salida:** correr `criterios-entrada.php` en vivo; reglas de decisión.
6. **Formato de hallazgos:** mostrar AR-02 completo y la escala de severidad.
7. **Autorrevisión:** los 7 hallazgos y `verificar-autorrevision.php`.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Inspección (IEEE 1028) | El código controla el acceso a datos clínicos: se necesita evidencia, roles separados y retrabajo verificado. |
| Dos sesiones | Por encima de ~200 LOC/h en la preparación o de 90–120 min de reunión, la detección baja. 3069 líneas no caben en una sesión. |
| Perspectivas por revisor | Reparten la atención (seguridad, contrato, dominio) sin que nadie deje de leer el paquete de su sesión. |
| Severidad por **impacto en RBAC** | Lo que importa es si permite acceso indebido o pierde evidencia, no lo difícil que sea corregirlo. |
| Criterios de entrada automatizados | No se gasta tiempo de cuatro personas en un paquete con pruebas rojas o sin congelar. |
| No se discuten soluciones en la reunión | Discutir soluciones consume el tiempo de encontrar defectos. Las decide el autor en el retrabajo. |
| No corregir los hallazgos del autor antes de la reunión | Rompería E-1 (paquete congelado) y quitaría al equipo la oportunidad de calibrar la severidad. |

## 4. Preguntas probables y respuestas

**¿Qué diferencia hay entre inspección, *walkthrough* y revisión técnica?**

| Tipo | Cómo es |
|---|---|
| *Walkthrough* | El autor guía y explica. Sirve para aprender. |
| Revisión técnica | Un grupo evalúa si el producto sirve para su propósito. |
| Inspección | Lo más formal: roles fijos, checklist, preparación individual, métricas, criterios de entrada y salida, y retrabajo verificado. El autor **no** dirige. |

**¿Por qué el autor no puede ser moderador?**
Porque tendería a defender el diseño y a bajar la severidad. Su papel es aclarar y luego corregir.

**¿Por qué tu hallazgo más grave es «Mayor» y no «Crítico»?**
AR-01 (tokens de demostración) es un riesgo de despliegue: hoy el Micro-HIS no está en producción y la identidad real la da JwtAuth en el HIS. Si el equipo decide que puede desplegarse así, sube a Crítico; esa calibración es justamente lo que hace la reunión.

**¿Cómo se decide si se aprueba?**
- Cero críticos abiertos.
- Los mayores corregidos o con decisión aprobada más un issue.
- La suite y el lint en verde tras el retrabajo.
- Cada corrección con su verificación.
- Re-inspección si hay un crítico, más de 3 mayores o más de 2 (Crítico + Mayor) por KLOC.

**¿Qué métricas se recogen y para qué?**
- Minutos de preparación (tasa LOC/h).
- Hallazgos por severidad, ítem y origen.
- Densidad por KLOC.

La línea base es la autorrevisión: 1.6 hallazgos (Crítico + Mayor + Menor) por KLOC.

**¿No es raro que tú mismo encuentres defectos en tu código?**
Es el objetivo de la autorrevisión. Además, uno de ellos (AR-03) lo introdujo **mi** cambio de la semana 12, y el checklist lo encontró. Eso muestra que el checklist sirve.

## 5. Demostración en vivo

```bash
cd docs/modulos/rbac/semana-13
php herramientas/criterios-entrada.php        # 6/6 PASA; muestra el paquete archivo por archivo
php herramientas/verificar-autorrevision.php  # AR-01: 201 con token demo · AR-02: 201 y 201 · AR-03: 0 vs 1 evento
```

Abrir [plantillas/registro-hallazgos.csv](plantillas/registro-hallazgos.csv) como hoja de cálculo (Google Sheets, o Excel con Datos → Desde texto/CSV y separador «coma») para mostrar el formato.

## 6. Cambios que podrían pedirte en vivo

| Pedido | Cómo hacerlo |
|---|---|
| «Agrega un ítem al checklist» | Fila RT-16 en `checklist-revision.md`. El criterio E-5 acepta hasta 15 ítems: habría que fusionar dos o ampliar el límite y justificarlo. |
| «Baja el límite por sesión a 1000 líneas» | `MAX_LINEAS_SESION` en `criterios-entrada.php`: E-4 fallará y habrá que hacer 3 sesiones. |
| «Clasifica este hallazgo» | Aplicar la tabla de severidad del formato: ¿permite acceso indebido o pierde evidencia? |
| «¿Qué pasa si alguien modifica el código antes de la reunión?» | E-1 falla (cambios sin confirmar) y la reunión se reprograma. |
