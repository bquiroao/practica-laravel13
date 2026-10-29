# Guía de defensa oral — Semana 7 (componentes y refactor)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA funcional, pruebas manuales y matriz de regresión · **Documento:** [semana-07-componentes-refactor.md](semana-07-componentes-refactor.md)

## 1. Resumen en 60 segundos

Identifiqué 6 componentes de frontend y 7 de backend, con el contrato de cada uno: entrada, salida, lo que nunca hace y la prueba que lo fija.

El punto más acoplado lo medí, no lo supuse: `RegistrarResultado`, con 22 dependencias, 67 ramas y 8 decisiones de QA en la capa de Aplicación. Pasé esas reglas a un objeto del Dominio, `SolicitudResultado`, y el caso de uso quedó orquestando el orden de los efectos. Resultado: dependencias −23 %, ramas −49 %, decisiones fuera del Dominio de 8 a 0.

El comportamiento no cambió:
- 37 escenarios grabados antes del refactor dan lo mismo;
- dos mutaciones que alteran el orden de las reglas se detectan;
- las demos de las semanas 4 y 6 salen idénticas.

El módulo creció 37 líneas (+1,1 %), sin funcionalidad nueva.

## 2. Orden sugerido

1. La tabla de acoplamiento: por qué `RegistrarResultado` y no `CerrarCampana` (§2).
2. El antes/después en diagrama y en código (§3).
3. **El riesgo del refactor es el orden**: un ejemplo de 413 frente a 422 (§4).
4. Correr `php tests/ejecutar.php --solo=CAR` y mostrar M34/M35 en `mutaciones.txt`.
5. La tabla de resultados, incluido el +1,1 % (§5).

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿`CerrarCampana` no está igual de acoplada? | Mezcla 5 preocupaciones, pero toma 0 decisiones: solo pide datos y llama a `Campana::cerrar`. Eso es coordinar. `RegistrarResultado` decidía 8 cosas que son reglas del dominio. |
| ¿Por qué un objeto de valor y no un servicio? | Las reglas no necesitan puertos. Un objeto inmutable que se construye validado se prueba sin dobles, y no puede quedar «a medio validar». |
| ¿Cómo sabe que no cambió nada? | Por la caracterización: 37 escenarios grabados ANTES, con efectos incluidos. Y porque M34/M35, que solo cambian el orden, la hacen fallar. |
| ¿No amplió el módulo? | No hay ninguna operación, regla ni tabla nueva. Son 37 líneas más (+1,1 %): una clase con nombre para algo que estaba escondido en un método privado. Lo reporto, no lo escondo. |
| ¿Qué queda por mejorar? | `Severidad::tryFrom` en `ClasificarDefecto` (Dq = 1). Es un caso pequeño; moverlo agregaría otra clase por una línea. |

## 4. Comandos

```bash
cd docs/modulos/qa-regresion-defectos/micro-his-qa
php bin/acoplamiento.php
php tests/ejecutar.php --solo=CAR,APP
diff <(php bin/demo-r15.php) <(tail -n +2 ../semana-06/evidencia/demo-r15.txt)
```
