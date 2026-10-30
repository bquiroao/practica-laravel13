# Guía de defensa oral — Semana 4 (capas, MVC y Repository)

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** QA funcional, pruebas manuales y matriz de regresión · **Documento:** [semana-04-capas-mvc-repositorio.md](semana-04-capas-mvc-repositorio.md)

## 1. Resumen en 60 segundos

**Entrada.** Separé la clase de acciones de la semana 3 en MVC:
- **formularios**, que solo validan la forma (400);
- **controladores**, que siempre hacen formulario → caso de uso → vista;
- **vistas**, lo único que conoce los códigos HTTP.

Una prueba de arquitectura lee los controladores y falla si tienen SQL o reglas.

**Repository.** Las interfaces están en el Dominio y tienen dos adaptadores, PDO e InMemory. Los de memoria ya no son dobles de prueba: son de producción y permiten correr el sistema sin base. Una misma suite de contrato corre contra los dos, y en su primera corrida encontró dos diferencias reales: el orden de los requisitos y el error de un caso duplicado.

**Repositorio compartido.** Para QA es la base **central** de la red. Allí, el acceso de un tester lo decide su membresía en `hospital_memberships`, con un trigger. Lo verifiqué sobre las migraciones reales C0 y C1: 18 de 18.

## 2. Orden sugerido

1. Tabla «qué cambió» y por qué el comportamiento no cambió: los mismos 23 códigos HTTP (§1).
2. Diagrama de capas: un objeto reutilizable por capa (§2).
3. MVC: forma (400) frente a regla (422/409). Mostrar MVC-01 (§3).
4. Repository: por qué en `Dominio\Puertos` y por qué no hay `borrarIntento` (§4.1).
5. **La suite de contrato en rojo** (`6-contrato-en-rojo.txt`): 2 diferencias reales y 4 errores míos (§7).
6. Repositorio compartido: por qué la central y por qué la opción B (§6).

## 3. Preguntas probables

| Pregunta | Respuesta corta |
|---|---|
| ¿Por qué el formulario no valida que el título no esté vacío? | Porque eso es una regla (R-01) y ya vive en el dominio. Si el formulario la repitiera, habría dos lugares que mantener y podrían divergir. El formulario solo responde «no puedo leer esto» (400). |
| ¿Para qué un adaptador en memoria en producción? | Para demos y desarrollo sin base (`QA_PERSISTENCIA=memoria`), y como pieza reutilizable por las pruebas de otros módulos. Las fallas a pedido no están en producción: son decoradores en `tests/`. |
| ¿Cómo sé que los dos adaptadores se comportan igual? | Con la suite de contrato: 7 pruebas × 2 adaptadores. Encontró que PDO daba 503 donde memoria daba 409. M19 y M20 reintroducen esas diferencias y se detectan. |
| ¿Por qué el repositorio va en el Dominio y no en Persistencia? | Habla en términos del modelo y expresa reglas por omisión (no hay método para cambiar un intento). Si estuviera en Persistencia, el dominio dependería de la infraestructura. |
| ¿No es peligroso un `SECURITY DEFINER`? | Lo sería si devolviera datos. Este devuelve solo un booleano, con `search_path` fijo. El validador prueba que el rol del módulo sigue sin poder leer `central_users`. |
| ¿Por qué no una API de Identidad? | Por la revocación: en la misma base, quitar una membresía corta el siguiente INSERT. Con API y caché hay una ventana en la que un acceso revocado sigue funcionando. |

## 4. Cambios que podrían pedir en vivo

| Cambio | Dónde | Cómo verificar |
|---|---|---|
| Rechazar más de 10 evidencias por intento (forma) | `FormularioResultado` | Copia de MVC-02 → 400 |
| Un repositorio nuevo (p. ej. matriz editable) | Puerto en `Dominio\Puertos` + 2 adaptadores | Agregar CON-08 a la suite de contrato |
| Que un `qa_analyst` también pueda registrar resultados | `qa_exigir_membresia()`: `ARRAY['qa_tester', 'qa_analyst']` | Una afirmación más en el validador |

## 5. Comandos

```bash
cd docs/modulos/qa-regresion-defectos/micro-his-qa
php tests/ejecutar.php --solo=MVC,ARQ,CON
QA_DB_DSN="pgsql:host=127.0.0.1;port=55432;dbname=micro_his_qa" php tests/ejecutar.php
php bin/demo.php
QA_DB_DSN=... php bin/validar-integracion-central.php
```
