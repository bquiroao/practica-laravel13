# Guía de defensa oral — Semana 4

**Estudiante:** Boris Alexander Quiroa Orellana · **Módulo:** Gobernanza y auditoría · **Documento:** [semana-04-capas-mvc-repositorio.md](semana-04-capas-mvc-repositorio.md)

## 1. Resumen en 60 segundos

> «Seguí con el mismo micro-HIS. La entrada ahora es MVC: un formulario que solo valida el formato, un controlador que recibe únicamente casos de uso y una vista que es el único lugar con códigos HTTP. El controlador no tiene SQL ni reglas, y una prueba lo verifica leyendo el archivo. El Repository es la interfaz Bitacora, que está en el Dominio y no tiene métodos para modificar ni borrar. Tiene dos adaptadores de producción, PostgreSQL y memoria, y una misma suite de contrato de 8 verificaciones pasa en los dos. El Dominio y la Aplicación no cambiaron. Para el repositorio compartido analicé la base central del HIS y propongo integrarse por su bandeja central_inbox_messages, tomando el hospital del nodo de origen. Ejecuté ese SQL sobre las migraciones reales del proyecto y se cumplieron las 6 afirmaciones. En total son 62 pruebas, todas en verde con PostgreSQL, y 15 de 15 mutaciones detectadas.»

## 2. Orden sugerido de la exposición

1. **Qué cambió** (§1): solo Presentación y Persistencia.
2. **MVC** (§3): el controlador de 4 métodos y ARQ-07.
3. **Repository** (§4): por qué en el Dominio; la suite de contrato.
4. **Repositorio compartido** (§6): las 3 opciones, por qué la bandeja y la validación sobre migraciones reales.
5. **Demo sin base**: `php bin/demo.php`.

## 3. Decisiones clave y cómo justificarlas

| Decisión | Justificación |
|---|---|
| Puerto en el Dominio | Su vocabulario (cadena, sello, alcance) es del dominio; así el caso de uso ignora la tecnología. |
| InMemory de producción y no solo de prueba | La tarea lo pide, y además sirve como demo y para las pruebas de otros módulos. |
| Decorador de prueba sobre el adaptador real | Las pruebas ejercitan el código de producción; las fallas se agregan por fuera. |
| Suite de contrato única | Dos adaptadores sin contrato divergen; con contrato, la divergencia es una prueba roja (M14, M15). |
| Integración por bandeja, no `INSERT` directo | Una sola vía de escritura: si cada módulo insertara, se saltaría las reglas y rompería la cadena. |
| `usuario_id` sin FK | Un movimiento de un usuario aún no sincronizado no puede rechazarse: es evidencia. |

## 4. Preguntas probables y respuestas

**¿Qué diferencia hay entre capas y MVC?**
Las capas organizan todo el sistema. MVC organiza solo la entrada, dentro de Presentación. Aquí el Model es la Aplicación con el Dominio.

**¿El Repository es lo mismo que un DAO?**
Un DAO refleja tablas. Un Repository refleja el dominio: aquí no hay `insertRow`, hay `agregar(Movimiento)`, `cadena(hospital)` y `buscar(ConsultaMovimientos)`.

**¿Cómo sabe que el adaptador en memoria se comporta como la base?**
Por la suite de contrato. Si el adaptador en memoria ignora el límite (M14), falla CON-MEM-06 y la fila PDO sigue en verde.

**¿Qué es un repositorio de datos compartido y qué riesgos tiene?**
Varios componentes se integran por un almacén común. El riesgo principal es que cualquiera escriba cualquier cosa. Se mitiga con propiedad de los datos: un solo escritor, roles por módulo y el trigger.

**¿Por qué el hospital sale del nodo y no del mensaje?**
Por la misma razón que en la API sale del token: un hospital no puede escribir a nombre de otro.

## 5. Recorrido de demostración

```bash
cd docs/modulos/gobernanza-auditoria/micro-his-auditoria
php bin/demo.php                                   # sin base de datos
php tests/ejecutar.php                             # 48 pasan, 14 omitidas
AUDITORIA_DB_DSN=… php tests/ejecutar.php          # 62/62
AUDITORIA_DB_DSN=… php bin/validar-integracion-central.php
```
