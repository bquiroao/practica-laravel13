# ISP aplicado al flujo «alta, búsqueda y control de vigencia»

**Principio:** *Interface Segregation Principle*. Ningún cliente debe depender de métodos que no usa.

## 1. Problema (antes)

`diagramas/isp-antes.png` · código: `ejemplo-isp/antes.php`

Una sola interfaz, `RepositorioCatalogo`, con 12 métodos, la reciben todos los casos de uso. Mezcla:
- lectura (búsqueda, historial),
- escritura (alta, versión, baja, eliminación),
- una consulta que pertenece a otro módulo (uso en prescripciones),
- datos que no son del catálogo (precio y existencias, de farmacia/inventario).

Consecuencias concretas para este módulo:
1. `BuscarMedicamentosVigentes` usa 1 método y ve 12: un doble de prueba para la búsqueda tiene que implementar los 12.
2. Si inventario cambia la firma de `existencias()`, hay que revisar y recompilar casos de uso que solo buscan.
3. La frontera del módulo se rompe: el catálogo termina «sabiendo» de precios y existencias.

## 2. Diseño (después)

`diagramas/isp-despues.png` · código: `ejemplo-isp/despues.php`

| Interfaz de rol | Métodos | Quién la usa | Responsabilidad |
|---|---|---|---|
| `BusquedaCatalogo` | `buscarVigentes`, `vigentePorCodigo` | `BuscarMedicamentosVigentes`, lectura de otros módulos | Leer lo vigente a una fecha (RN-03). |
| `HistorialCatalogo` | `historial` | `ConsultarHistorial` | Todas las versiones de un código. |
| `ControlEquivalencia` | `equivalenteVigente` | `RegistrarMedicamento`, `RegistrarNuevaVersion` | Detectar duplicados vigentes (RN-04). |
| `EscrituraCatalogo` | `siguienteCodigo`, `agregar`, `reemplazarVigente`, `cerrar`, `eliminar` | Casos de uso que modifican | Cambios atómicos de versión y vigencia (RN-01). |
| `ConsultaUsoPrescripciones` (puerto) | `fueUsado` | `EliminarRegistroSinUso` | Preguntar a prescripciones si un código se usó (RN-02). |
| `RepositorioMedicamentos` | hereda las cuatro primeras | Adaptadores PDO e InMemory | La interfaz Repository del dominio. |

Decisiones y justificación:
- **Dependencias hacia abstracciones pequeñas:** cada caso de uso declara en su constructor solo las interfaces que usa, así se ve su dependencia real.
- **Una implementación, varias vistas:** los adaptadores implementan `RepositorioMedicamentos` completo. ISP separa lo que el cliente ve, no obliga a escribir más clases de persistencia.
- **El uso en prescripciones es un puerto aparte:** no lo implementa el repositorio del catálogo, sino un adaptador hacia `prescripciones-alergias`. El formato de esa consulta queda **pendiente de acordar** con el dueño de ese módulo.
- **Precio y existencias salen del módulo:** no se segregan, se **quitan**, porque no son responsabilidad del catálogo.
- **No se divide de más:** `buscarVigentes` y `vigentePorCodigo` comparten la misma regla de vigencia y casi siempre los usa el mismo cliente; separarlos agregaría interfaces sin beneficio.

## 3. Evidencia verificable

`ejemplo-isp/verificar-isp.php` carga los dos diseños y mide con *Reflection*, para cada caso de
uso, cuántos métodos ve por sus dependencias, cuántos llama y cuántos son ajenos al catálogo.

```powershell
cd docs/modulos/catalogo-medicamentos/semana-02/ejemplo-isp
php verificar-isp.php --evidencia=../evidencia/verificacion-isp.txt
cd ../../../../..
```

Resultado obtenido al preparar la entrega (PHP 8.3.6; tu salida debe dar los mismos números):

| Medida | Antes | Después |
|---|---|---|
| Métodos visibles no usados (suma de los 4 clientes) | 41 | 8 |
| Métodos ajenos al catálogo visibles | 8 | 0 |
| Métodos que debe implementar un doble para probar la búsqueda | 12 | 2 |
| Resultado del script | — | `CUMPLE`, código de salida 0 |

Los 8 métodos no usados que quedan son de `EscrituraCatalogo` (un caso de uso de escritura ve las
otras operaciones de escritura) y `vigentePorCodigo` en la búsqueda. Se aceptan por la última
decisión de la sección 2. El script falla (salida 1) si el diseño después deja de mejorar o vuelve a
mostrar métodos ajenos.

## 4. Continuidad

Las mismas interfaces se implementan en el Micro-HIS (con objetos de valor en lugar de arreglos y
textos): dominio en la semana 3, adaptador InMemory
y prueba de contrato en la semana 4.
