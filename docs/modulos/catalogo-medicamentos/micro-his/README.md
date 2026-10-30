# Micro-HIS Catálogo de medicamentos

Prototipo en PHP 8.2+ **sin framework** del flujo «alta, búsqueda y control de vigencia de un
medicamento». Es el mismo código en las semanas 3, 4 y 5; cada semana agrega una capa de entrada.

| Semana | Qué agrega |
|---|---|
| 3 | Capas Presentation (consola), Application, Domain y Persistence (PDO); Composition Root; pruebas. |
| 4 | Entrada MVC web y adaptador InMemory con prueba de contrato. |
| 5 | API REST JSON con roles, tenant y errores uniformes. |

## Requisitos
- PHP 8.2 o superior con `pdo_pgsql` y `mbstring`.
- Docker Desktop (PostgreSQL 16 en el puerto **55445**).

## Levantar la base y el esquema
Desde esta carpeta (`docs/modulos/catalogo-medicamentos/micro-his`):

```powershell
docker compose up -d
docker compose ps                      # esperar STATUS = healthy
php scripts/crear-esquema.php          # crea micro_medicamentos y micro_medicamentos_test
```

Las credenciales de `config.php` y `docker-compose.yml` son **de desarrollo y ficticias**. Se pueden
cambiar con las variables `CATALOGO_BD_HOST`, `CATALOGO_BD_PUERTO`, `CATALOGO_BD_NOMBRE`,
`CATALOGO_BD_PRUEBA`, `CATALOGO_BD_USUARIO` y `CATALOGO_BD_CLAVE`.

## Consola (semana 3)

```powershell
php bin/catalogo.php buscar amoxi
php bin/catalogo.php historial MED-000104
php bin/catalogo.php buscar "% s"
php bin/catalogo.php eliminar MED-000101          # 409 EN_USO: está en una prescripción ficticia
php bin/catalogo.php buscar tab --hospital=sur
```

## Pruebas

```powershell
php pruebas/ejecutar.php                          # todas (necesita la base)
php pruebas/ejecutar.php --sin-bd                 # sin la base: omite pruebas/integracion
php pruebas/ejecutar.php --evidencia=../semana-03/evidencia/salida-pruebas.txt
```

`--evidencia` escribe el archivo en UTF-8 desde PHP; no use `>` en PowerShell 5 (guarda UTF-16).

## Estructura

```
src/Domain/        Modelo, reglas RN-01..RN-06, errores e interfaces Repository (ISP)
src/Application/   Casos de uso CU-01..CU-06 y RF-09
src/Persistence/   Adaptadores PDO, reloj, UUID y datos ficticios de ejemplo
src/Presentation/  Entradas (consola en semana 3)
bootstrap.php      Composition Root
scripts/           crear-esquema.php
pruebas/           Ejecutor propio, dobles, unitarias e integración
```

## Esquema (propuesta del Micro-HIS)
No se pudo consultar el esquema real de HOSPITAL/CENTRAL. La tabla `medicamentos` es una
**propuesta**: se ubicaría en **HOSPITAL** porque cada hospital (tenant) administra su catálogo.
`referencias_prescripcion` es solo una **simulación** de lo que publicaría `prescripciones-alergias`.
