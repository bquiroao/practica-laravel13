# Guía de defensa oral — Semana 9 (evaluación UX y WCAG)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Gobernanza y auditoría · **Documento:** [semana-09-evaluacion-ux-auditoria.md](semana-09-evaluacion-ux-auditoria.md)

## 1. Resumen en 60 segundos

Evalué mis propios wireframes de la semana 8 con Nielsen y WCAG 2.2 AA, en 40 ítems. Encontré 15 hallazgos, 5 de ellos de severidad 3. Los tres que más importan:
- el diálogo de red se abre con solo mover las flechas (3.2.2);
- el 401 promete algo que la regla de protección de datos impide;
- las correcciones citan ids que no se ven.

El contraste se **midió**: fallan 9 pares, y con la paleta propuesta cumplen todos. El backlog tiene 15 ítems, cada uno con un criterio que se puede comprobar en el prototipo.

## 2. Orden sugerido

1. Método y priorización (§2).
2. `python herramientas/contraste.py`, en vivo (exit 0 con la propuesta).
3. H-01, H-02 y H-03 con sus recortes.
4. Backlog: cómo se comprueba cada corrección (B-01..B-05).
5. Limitaciones: un evaluador y wireframes.

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Cómo encuentras problemas en tu propio diseño? | Con criterios externos: cada hallazgo cita la heurística o el criterio WCAG que viola, y el contraste lo mide un script, no mi opinión. |
| ¿Por qué H-01 es severidad 3? | Con el teclado no se puede recorrer el grupo de radios sin que aparezca un diálogo; es un cambio de contexto no pedido. |
| ¿Por qué no guardar los filtros en `sessionStorage` para resolver H-02? | Contradice la protección de datos: la bitácora no debe quedar en el navegador. Renovar la sesión sin salir de la página resuelve las dos cosas. |
| ¿Por qué no deshabilitar «Verificar» si hay errores? | Un botón deshabilitado sale del orden de Tab y no explica nada. Es mejor dejarlo habilitado y mostrar un resumen de errores. |
| ¿Es válido con un solo evaluador? | Es una limitación declarada. Los criterios de verificación se aplicarán sobre el prototipo con axe y teclado. |

## 4. Demostración

```bash
cd docs/modulos/gobernanza-auditoria/semana-09
python herramientas/contraste.py                    # 9 FALLA con los colores actuales; 0 propuestas que fallen
python herramientas/recortes.py ../semana-08/wireframes evidencia/hallazgos   # regenera los 15 recortes
```
