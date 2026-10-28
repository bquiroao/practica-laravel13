# Guía de defensa oral — Semana 13 (revisión técnica formal)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Prescripciones electrónicas con validación de alergias · **Documento:** [semana-13-revision-tecnica-prescripciones.md](semana-13-revision-tecnica-prescripciones.md)

## 1. Resumen en 60 segundos

Preparé una **inspección formal** del Micro-HIS, con lectura basada en perspectivas:
- **15 ítems,** cada uno escrito como una afirmación que el revisor intenta refutar. Cada ítem trae la prueba que hoy lo respalda y un caso que esa prueba no cubre.
- **Roles:** autor, moderador, registrador, lector y cuatro revisores por perspectiva. El de dominio tiene que tener formación clínica.
- **Paquete:** tres sesiones de menos de 1600 líneas, congeladas en un commit.
- **Criterios de entrada:** un script los verifica. Sin checklist ni autorrevisión falla, y la reunión no se convoca.

Antes de convocar hice la autorrevisión: **7 hallazgos**, todos reproducidos contra el kernel real. El más grave para un paciente es AR-03: una alergia grave registrada como «Amoxicilina trihidrato» **no bloquea** la Amoxicilina. Ninguna de las 111 pruebas lo cubría. Hay además un Crítico, los tokens de demostración siempre activos, así que hoy la decisión sería «re-revisión».

## 2. Orden sugerido

1. Tipo de revisión y por qué inspección (plan §1).
2. Checklist: leer RT-02 y su columna «qué buscar además».
3. Correr `criterios-entrada.php` en vivo y mostrar el «antes» (E-5 y E-6 fallan).
4. Correr `verificar-autorrevision.php` y detenerse en AR-03 y AR-02.
5. Criterios de salida y la decisión con lo conocido hoy (plan §7).

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué no corrigió los hallazgos? | El proceso separa encontrar de corregir: primero se registran y clasifican, después se corrigen con prueba y mutación. Además, AR-03 y AR-07 necesitan una decisión de farmacia. |
| ¿Cómo se le escaparon a 111 pruebas y 35 mutaciones? | Las pruebas usan los nombres del catálogo. Las mutaciones miden si las pruebas detectan cambios en el **código**, no si el código cubre todos los casos del **mundo**. Eso es lo que busca la revisión. |
| ¿Por qué AR-02 es Mayor y no Crítico? | No se crea ninguna prescripción indebida ni se rompe el tenant. Pero el médico recibe una respuesta falsa y no queda traza. La colisión exige una clave repetida. |
| ¿Por qué un farmacéutico como revisor? | Porque la severidad de AR-03 o qué hacer con una intolerancia (AR-07) son decisiones clínicas. El autor no las puede tomar. |
| ¿Qué pasa si E-8 no se cumple? | No hay reunión. Se reprograma y se anota el motivo en el acta. |

## 4. Cambios que podrían pedir en vivo

| Pedido | Respuesta |
|---|---|
| «Agregar un ítem sobre rendimiento» | No cabe sin quitar otro: la tarea pide 12–15 y hoy hay 15. Habría que fusionar dos, por ejemplo RT-08 con RT-09. |
| «Reproducir AR-03 con otro nombre» | Se agrega a la lista del bloque AR-03 de `verificar-autorrevision.php` y se corre. |
| «Bajar el límite de líneas por sesión» | Es la constante `MAX_LINEAS_SESION`. Con 1000, S2 (1241) se dividiría en dos sesiones. |
