# Semana 4 — Catálogo de medicamentos: MVC, patrón Repository y repositorio compartido

Continúa el MISMO Micro-HIS (`../micro-his/`) y el flujo «alta, búsqueda y control de vigencia».

## 1. Qué pide la tarea y dónde se cumple

| Pide | Dónde |
|---|---|
| Entrada organizada con MVC | `public/index.php` (front controller) → `Router` → `ControladorMedicamentos` → `Vista` + plantillas en `src/Presentation/Web/Vistas/` |
| Controlador sin SQL ni reglas de negocio | Cada acción llama a un caso de uso; `pruebas/unitarias/arquitectura.php` lo verifica sobre el código |
| Interfaz Repository en el límite apropiado | `src/Domain/Repositorio/` (en el dominio, no en Persistence): el dominio define lo que necesita |
| Adaptadores PDO e InMemory | `RepositorioMedicamentosPdo` (semana 3) y `RepositorioMedicamentosEnMemoria` (nuevo) |
| Mismo contrato | `pruebas/contrato/repositorio.php`: 7 casos × 2 adaptadores |
| Repositorio de datos compartido | Sección 5 y `diagramas/repositorio-compartido.png` |
| Capas, responsabilidades y objetos reutilizables | Secciones 2 y 3, `diagramas/mvc-capas.png` |

## 2. Capas y responsabilidades (con MVC)

| Capa | Objeto | Responsabilidad |
|---|---|---|
| Presentation | `public/index.php` | Arranca la sesión, pide los casos de uso al Composition Root y despacha. |
| | `Router` / `RutasWeb` | Relaciona método + ruta con una acción; 404 y 405. |
| | `ControladorMedicamentos` (C) | Lee la petición, llama al caso de uso, elige vista y código HTTP. |
| | `Vista` + plantillas (V) | Muestra datos con `$e()` (escape HTML); errores asociados a cada campo con `aria-describedby`. |
| | `Csrf`, `Peticion`, `Respuesta` | Token anti-CSRF, petición inmutable y respuesta probable sin servidor. |
| Application | Casos de uso (semana 3) | Sin cambios: la entrada web reutiliza exactamente los mismos. |
| Domain (M) | `Medicamento`, reglas, interfaces | Sin cambios. |
| Persistence | `RepositorioMedicamentosEnMemoria`, `UsoPrescripcionesEnMemoria` | Mismo contrato que PDO, sin base. |
| Composition Root | `bootstrap.php` | Nuevo parámetro `$almacen` (`pdo` o `memoria`): cambia el adaptador sin tocar nada más. |

## 3. Objetos reutilizables

| Objeto | Lo reutilizan | Por qué |
|---|---|---|
| Casos de uso de `src/Application` | Consola (S3), web (S4), API (S5) | Una sola implementación de cada flujo. |
| `MedicamentoVista` | Consola, plantillas, JSON de la API | Un solo formato plano de una versión. |
| `OpcionesCatalogo` | Formularios web y la API | Las listas cerradas salen del dominio sin que Presentation lo importe. |
| `CriterioBusqueda::coincide()` | InMemory y el doble de pruebas | La misma semántica que el SQL; el contrato lo comprueba. |
| `RepositorioMedicamentos` (interfaz) | PDO, InMemory y un futuro adaptador al repositorio compartido | Cambiar dónde están los datos no toca los casos de uso. |
| `Peticion`/`Respuesta`/`Router` | Web (S4) y API (S5) | La API agrega su controlador JSON encima. |

## 4. Prueba de contrato PDO = InMemory

`pruebas/contrato/repositorio.php` ejecuta los mismos casos contra ambos adaptadores, cada uno
cargado con los mismos datos ficticios:

1. búsqueda por texto, mayúsculas y acentos (`CÁPSULA` encuentra `cápsula`; `capsula` no);
2. `%`, `_` y `!` como texto literal;
3. vigencia a una fecha y filtro por forma;
4. límite de 50 resultados con el mismo orden (`strcmp` = `COLLATE "C"`);
5. consulta por código, historial, equivalencia y siguiente código por hospital;
6. restricciones con los mismos códigos de error (`DUPLICADO`, `SOLAPE_VIGENCIA`, `CONFLICTO_CONCURRENTE`) y escritura atómica;
7. eliminar solo afecta a ese código de ese hospital.

Para devolver los mismos códigos de error, InMemory revisa las restricciones en el mismo orden en
que PostgreSQL revisa sus índices (primero `UNIQUE`, después los dos `EXCLUDE`); el caso 6 lo comprueba.

```powershell
cd docs/modulos/catalogo-medicamentos/micro-his
php pruebas/ejecutar.php --evidencia=../semana-04/evidencia/salida-pruebas.txt
php pruebas/ejecutar.php --sin-bd            # contrato solo contra InMemory
cd ../../../..
```

**Resultado al preparar la entrega:** 59 pruebas, 59 correctas (PHP 8.3.6, PostgreSQL 16.15 local).

## 5. Repositorio de datos compartido (análisis, propuesta)

El HIS usa PostgreSQL federado (bases CENTRAL y HOSPITAL). Cómo encaja el catálogo:

| Opción | Cómo sería | Ventaja | Riesgo | Decisión |
|---|---|---|---|---|
| A. Tabla compartida que todos leen y escriben | Prescripciones y farmacia hacen `UPDATE` en `medicamentos` | Simple al inicio | Cualquiera rompe RN-01/RN-04; el esquema no puede cambiar | **Rechazada** |
| B. Dueño único + vista de solo lectura | Solo el catálogo escribe; publica `v_medicamentos_vigentes` y da `SELECT` a un rol de lectura | Reglas en un solo lugar; contrato de datos estable | Hay que versionar la vista | **Propuesta** |
| C. Solo API (semana 5) | Los demás leen por HTTP | Desacople total | Latencia y disponibilidad | Para lectura remota o si se separa en servicio |

Detalles de la opción B (`diagramas/repositorio-compartido.png`):
- El catálogo vive en un esquema propio de **HOSPITAL** (cada hospital administra su catálogo).
- `medicamentos_codigos (hospital_id, codigo)` es el ancla de las claves foráneas: una fila por código. Si `prescripciones-alergias` referencia esa tabla con `ON DELETE RESTRICT`, la base también impide borrar un código usado (RN-02 en dos niveles).
- `v_medicamentos_vigentes` expone solo columnas estables; los otros módulos no leen la tabla de versiones.
- Un catálogo de referencia en **CENTRAL** (principios activos) es opcional y se leería con `postgres_fdw`.
- En el Micro-HIS esto no cambia los casos de uso: bastaría otro adaptador de `RepositorioMedicamentos` y de `ConsultaUsoPrescripciones`.

Todo esto es **propuesta**: depende de lo que acuerden los dueños de `prescripciones-alergias` y de
farmacia, y de los nombres reales de esquemas en HOSPITAL/CENTRAL, que no se pudieron consultar.

## 6. Cómo probar la entrada web

```powershell
cd docs/modulos/catalogo-medicamentos/micro-his
php -S 127.0.0.1:8085 -t public
# otra terminal, sin base:  $env:CATALOGO_ALMACEN = "memoria"; php -S 127.0.0.1:8085 -t public
```

Abrir `http://127.0.0.1:8085/medicamentos?q=amoxi`. Con `memoria` los datos vuelven al estado
inicial en cada petición (cada petición crea un proceso nuevo).

## 7. Riesgos y pendientes
- La web usa un usuario fijo de demostración (`qf.norte`, Hospital Norte); la autenticación por token llega en la semana 5.
- Un medicamento registrado hoy no se puede dar de baja hoy (la vigencia quedaría vacía): se corrige con «Eliminar registro erróneo». Se revisará en el diseño UX (semana 8).
- `medicamentos_codigos` y `v_medicamentos_vigentes` no se implementaron en el Micro-HIS; son parte de la propuesta.
