# Refactor del punto con mayor acoplamiento: la nueva versión de un medicamento

## 1. Por qué este punto

La operación «registrar nueva versión» (CU-03) toca **a la vez** las cinco preocupaciones del
módulo: medicamento (ficha), presentación (lo que suele cambiar), vigencia (cerrar la anterior),
búsqueda (lo que se verá vigente) y unicidad (RN-04). En un controlador típico de Laravel todo esto
queda en un solo método; es donde más se repiten reglas y donde un error deja dos versiones vigentes.

## 2. Antes (conceptual, estilo Laravel)

`diagramas/refactor-antes.png`. Código ilustrativo, **no** está en el repositorio:

```php
public function nuevaVersion(Request $request, string $codigo)
{
    $hospital = $request->input('hospital_id');                       // tenant del cuerpo (RNF-02 roto)
    if (strlen($request->presentacion) > 100) {                        // límite distinto a la columna
        return response()->json(['error' => 'Muy largo'], 400);
    }
    $actual = Medicamento::where('hospital_id', $hospital)->where('codigo', $codigo)
        ->whereNull('vigente_hasta')->first();                         // consulta en el controlador
    $dup = Medicamento::where('hospital_id', $hospital)
        ->where('principio_activo', $request->principio_activo)        // sin normalizar (RN-04 débil)
        ->where('codigo', '<>', $codigo)->whereNull('vigente_hasta')->exists();
    if ($dup) { return response()->json(['error' => 'Duplicado'], 409); }
    $actual->update(['vigente_hasta' => $request->vigente_desde]);    // sin transacción
    Medicamento::create($request->all() + ['version' => $actual->version + 1]);
    return response()->json(['ok' => true], 201);
}
```

Problemas medibles:

| Medida | Antes |
|---|---|
| Responsabilidades en la clase | 5 (formato, tenant, consulta, regla de vigencia, respuesta HTTP) |
| Dependencias concretas | 3 (Request, modelo Eloquent, DB) |
| Lugares donde vive RN-01 | 2 (este método y `store()`) |
| ¿Se puede probar la regla sin base? | No |
| ¿Atómico? | No: si `create()` falla, la versión anterior queda cerrada sin reemplazo |

## 3. Después (implementado en el Micro-HIS)

`diagramas/refactor-despues.png`:

| Capa | Clase | Responsabilidad única |
|---|---|---|
| UI | `src/Presentation/Api/ApiMedicamentos.php::registrarVersion` | Permiso + llamar al caso de uso + 201 |
| Aplicación | `src/Application/RegistrarNuevaVersion.php` | Orquestar: leer historial, pedir al dominio la versión nueva, verificar equivalencia, guardar |
| Dominio | `Medicamento::nuevaVersion()` + `Vigencia` | RN-01: la nueva empieza después; la anterior se cierra ese día |
| Dominio | `ClaveEquivalencia` | RN-04 con principio activo normalizado |
| Persistencia | `RepositorioMedicamentosPdo::reemplazarVigente()` | Cerrar + insertar en **una** transacción; la base repite RN-01/RN-04 |

| Medida | Antes | Después |
|---|---|---|
| Responsabilidades por clase | 5 | 1 |
| Dependencias concretas del caso de uso | 3 | 0 (5 interfaces) |
| Lugares donde vive RN-01 | 2 | 1 (`Medicamento::nuevaVersion`) + defensa en la base |
| ¿Se prueba sin base? | No | Sí (`pruebas/unitarias/dominio.php`, `casos-de-uso.php`) |
| ¿Atómico? | No | Sí (prueba «reemplazarVigente es atómico») |
| Tenant | Del cuerpo | Del token |

## 4. Cómo se verifica que el comportamiento se conserva

```powershell
cd docs/modulos/catalogo-medicamentos/micro-his
php pruebas/ejecutar.php
cd ../../../..
```

Pruebas que cubren este flujo: «RN-01 la nueva versión cierra la anterior» (CA-09), «RN-01 una
versión que no empieza después de la vigente es SOLAPE_VIGENCIA» (CA-10), «CU-03 nueva versión
equivalente a OTRO código vigente es DUPLICADO», «reemplazarVigente es atómico» (PDO e InMemory) y
«Versión 201» en la API. Al preparar la entrega: 69 de 69 correctas.

## 5. Lo que no se hizo (sin ampliar el módulo)

- No se agregaron presentaciones múltiples (eso es el cambio práctico de la semana 6, aún no aprobado).
- No se movió nada de prescripciones al catálogo; el uso se sigue preguntando por el puerto.
