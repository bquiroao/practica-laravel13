# Guía de defensa oral — Semana 15

## Presentación breve

«La suite prueba emisión, consulta de seguimiento y actualización de órdenes mediante los contratos del diseño SRP. Separé resultados de negocio —orden inválida o transición rechazada— de fallas técnicas y de conflictos de escritura. Los datos son sintéticos y las pruebas no llaman otros módulos.»

## Preguntas probables

1. **¿Qué prueba el caso de emisión?** Que la orden conserve paciente, episodio, solicitante, todas sus partidas y el estado inicial entregado por la política.
2. **¿Por qué diferenciar `EMPTY` de `UNAVAILABLE`?** Episodio sin órdenes es un resultado válido; un repositorio caído no permite afirmar que no existan.
3. **¿Cómo se prueban permisos?** Dobles configurables devuelven denegación y los contadores verifican que no haya consulta ni escritura posteriores.
4. **¿Los estados `TEST_NEXT` y `TEST_FINAL` son productivos?** No; solo permiten comprobar que la aplicación respeta una política inyectada.
5. **¿Qué no cubre la suite?** EMR real, base transaccional productiva, catálogo real y módulos de recepción/resultados.

## Comandos

```powershell
php docs/modulos/ordenes-laboratorio-emr/semana-15/pruebas/run.php
php docs/modulos/ordenes-laboratorio-emr/semana-02/ejemplo-srp/verificar.php
```

Las salidas y capturas están en `evidencia/`.
