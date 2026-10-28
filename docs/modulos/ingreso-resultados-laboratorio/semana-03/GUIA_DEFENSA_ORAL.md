# Guía de defensa oral — Semana 3

## Resumen en 60 segundos

«Implementé un Micro-HIS CLI en PHP 8.2 por capas para capturar resultados pendientes y corregirlos sin sobrescribir historial. Presentation convierte opciones de consola en llamadas de aplicación; Application coordina el caso de uso y depende de puertos; Domain modela versiones inmutables pendientes y validadas; Persistence usa PDO con sentencias preparadas en SQLite. Una corrección requiere el identificador de la versión vigente, un valor y un motivo. El append condicional de SQL evita escribir si cambió la versión. Las pruebas usan dobles para comprobar captura, corrección, rechazo de validados, conflicto y error de persistencia.»

## Recorrido de demostración

1. Mostrar el diagrama de contexto y distinguir el prototipo de sus límites externos.
2. Revisar el diagrama de componentes y explicar la dependencia desde CLI hacia los puertos de Application.
3. Ejecutar `php tests/run.php` desde `semana-03/ejemplo/`.
4. Capturar un resultado sintético:

   ```powershell
   php bin/result.php --action=capture --item=ITEM-001 --value=5.4 --actor=TECH-01
   ```

5. Copiar el ID devuelto para mostrar la corrección con motivo. Usar un ID anterior después de corregir para comprobar el conflicto.

## Preguntas probables

**¿Se corrige en el mismo registro?** No. Se añade otra fila/version con vínculo a la anterior; el valor original permanece en el historial.

**¿Qué evita que se corrija una versión validada o antigua?** La aplicación exige una versión pendiente vigente y el repositorio verifica de forma condicional el ID esperado y el estado pendiente al insertar.

**¿Se validan rangos o unidades clínicas?** No. El valor es una cadena demostrativa. El módulo no define catálogo, unidad, rangos ni juicio bioquímico.

**¿El prototipo consulta la orden o autoriza al usuario?** No. Recibe referencias sintéticas. La integración con órdenes e identidad queda fuera del alcance.

**¿Cómo evita inyección SQL?** Usa PDO `prepare()` y parámetros enlazados; ningún dato de entrada se concatena en el SQL.

**¿Por qué las pruebas no necesitan SQLite?** Las pruebas unitarias usan dobles deterministas para reproducir el contrato y fallos de persistencia. La CLI demuestra el adaptador PDO SQLite en una ejecución aparte.

## Evidencia

- [Informe de arquitectura](semana-03-arquitectura-ingreso-resultados.md)
- [README del prototipo](ejemplo/README.md)
- [Diagrama de contexto](diagramas/semana-03-contexto.puml)
- [Diagrama de componentes](diagramas/semana-03-componentes.puml)
