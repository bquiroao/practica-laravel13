# Guía de defensa oral — Semana 9

**Estudiante:** Joshua Eduardo García Reyes · **Módulo:** ASII-11 Notas SOAP y diagnósticos · **Documento:** [semana-09-evaluacion-ux-notas-soap.md](semana-09-evaluacion-ux-notas-soap.md)

## 1. Explicación en 3 minutos

1. **Qué evalué (30 s).** «Evalué mi propio diseño de la semana 8 con tres cosas: las 10 heurísticas de Nielsen, criterios de WCAG 2.2 nivel AA y un checklist con las reglas clínicas del módulo. Son 82 ítems.»
2. **Cómo medí (45 s).** «El contraste no lo estimé a ojo: escribí `herramientas/contraste.py`, que calcula la razón WCAG con los colores exactos de los wireframes. 4 de 20 pares fallan. El script también prueba los colores que propongo y termina con error si alguno no cumple.»
3. **Lo más grave (1 min).** Mostrar H-01 y H-02. «Al escribir una nota no se ve el paciente, y el diálogo de firma no dice qué nota se firma. El servidor no puede detectar que el médico se equivocó de paciente, así que la interfaz tiene que evitarlo.»
4. **Cómo prioricé (45 s).** «Puntaje = severidad de Nielsen por impacto clínico. Lo que puede dejar información en el paciente o la versión equivocada pesa triple. Así salen 6 P1, 5 P2 y 5 P3, y cada uno tiene una corrección y un criterio que se puede comprobar.»

## 2. Demostración

```powershell
cd docs\modulos\notas-soap-diagnosticos\semana-09
python herramientas\contraste.py
```

Debe terminar con «Propuestas que no cumplen: 0». Si se cambia un color propuesto por uno que no cumple, el script falla.

## 3. Preguntas probables

**¿Por qué evaluar tu propio diseño y no el de otro?**
Es lo que pide la tarea. La limitación está escrita: un solo evaluador encuentra menos problemas; Nielsen recomienda de 3 a 5.

**¿Por qué H-01 es P1 si el servidor ya valida el acceso?**
El servidor valida que el médico pueda escribir en ese expediente, no que sea el paciente que tiene enfrente. Si el médico tiene dos pacientes asignados, las dos opciones son válidas para el servidor.

**¿Por qué no guardar el borrador en el navegador para no perderlo (H-11)?**
Porque es texto clínico en equipos compartidos (D-5). La corrección propuesta pide confirmación antes de salir, sin guardar el texto en el equipo.

**¿Qué es contraste de no texto?**
WCAG 1.4.11 pide 3:1 para bordes de campos, indicadores de estado y de foco. Los bordes de mis campos dan 2.13:1 (H-09).

**¿Corregiste los wireframes?**
No. La tarea es evaluar y priorizar. Las correcciones quedan en el backlog con su criterio, y las de color ya se pueden comprobar con el script.
