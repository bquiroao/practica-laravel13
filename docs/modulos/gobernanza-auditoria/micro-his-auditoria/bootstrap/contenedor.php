<?php

declare(strict_types=1);

/*
 * Raíz de composición: el ÚNICO lugar que conoce las clases concretas de las cuatro capas.
 * Las pruebas arman el mismo grafo con dobles; public/index.php lo arma con PDO.
 */

require __DIR__ . '/autoload.php';

use Auditoria\Aplicacion\CasosDeUso\ConsultarMovimientos;
use Auditoria\Aplicacion\CasosDeUso\RegistrarMovimiento;
use Auditoria\Aplicacion\CasosDeUso\VerDetalleMovimiento;
use Auditoria\Aplicacion\CasosDeUso\VerificarIntegridad;
use Auditoria\Aplicacion\Puertos\PoliticaAcceso;
use Auditoria\Aplicacion\Puertos\Transaccion;
use Auditoria\Aplicacion\Servicios\CadenaSellada;
use Auditoria\Dominio\Filtros\FiltroAccion;
use Auditoria\Dominio\Filtros\FiltroModulo;
use Auditoria\Dominio\Filtros\FiltroMovimiento;
use Auditoria\Dominio\Filtros\FiltroRangoFechas;
use Auditoria\Dominio\Filtros\FiltroResultado;
use Auditoria\Dominio\Filtros\FiltroUsuario;
use Auditoria\Dominio\Modelo\CatalogoAcciones;
use Auditoria\Dominio\Puertos\Bitacora;
use Auditoria\Dominio\Puertos\Reloj;
use Auditoria\Dominio\Reglas\ReglaAccionEnCatalogo;
use Auditoria\Dominio\Reglas\ReglaCamposObligatorios;
use Auditoria\Dominio\Reglas\ReglaFechaNoFutura;
use Auditoria\Dominio\Reglas\ReglaFormatoCampos;
use Auditoria\Dominio\Reglas\ReglaModuloDelEmisor;
use Auditoria\Dominio\Reglas\ReglaMovimiento;
use Auditoria\Dominio\Reglas\ReglaResultadoValido;
use Auditoria\Dominio\Reglas\ReglaSinDatosProhibidos;
use Auditoria\Dominio\Reglas\ReglaTenantDelEmisor;
use Auditoria\Persistencia\Configuracion\PoliticaAccesoEstatica;
use Auditoria\Persistencia\Configuracion\RelojSistema;
use Auditoria\Persistencia\ConjuntoAdaptadores;
use Auditoria\Persistencia\FabricaAdaptadores;
use Auditoria\Presentacion\Http\Autenticacion\Autenticador;
use Auditoria\Presentacion\Http\Autenticacion\AutenticadorTokenDemo;
use Auditoria\Presentacion\Http\Controladores\AuditoriaControlador;
use Auditoria\Presentacion\Http\Kernel;
use Auditoria\Presentacion\Http\Rutas;

/** @param array<string, mixed> $config @return list<ReglaMovimiento> el ORDEN importa: el tenant va primero */
function reglasDeRegistro(array $config, Reloj $reloj): array
{
    return [
        new ReglaTenantDelEmisor(),
        new ReglaCamposObligatorios(),
        new ReglaModuloDelEmisor($config['modulo_de_emisor']),
        new ReglaFormatoCampos(),
        new ReglaAccionEnCatalogo(new CatalogoAcciones($config['catalogo'])),
        new ReglaResultadoValido(),
        new ReglaFechaNoFutura($reloj, 5),
        new ReglaSinDatosProhibidos(),
    ];
}

/** @return list<FiltroMovimiento> */
function filtrosDeConsulta(array $config): array
{
    return [new FiltroRangoFechas($config['zona_horaria']), new FiltroUsuario(), new FiltroModulo(), new FiltroAccion(), new FiltroResultado()];
}

/**
 * @param array<string, mixed> $config
 * @param \Closure(): string|null $nuevoEventId
 * @return array{registrar: RegistrarMovimiento, consultar: ConsultarMovimientos, detalle: VerDetalleMovimiento, verificar: VerificarIntegridad}
 */
function construirCasosDeUso(array $config, Bitacora $bitacora, Transaccion $transaccion, PoliticaAcceso $acceso, Reloj $reloj,
    ?\Closure $nuevoEventId = null): array
{
    $cadena = new CadenaSellada($bitacora, $reloj, $nuevoEventId ?? static fn (): string => bin2hex(random_bytes(16)));

    return [
        'registrar' => new RegistrarMovimiento(reglasDeRegistro($config, $reloj), $bitacora, $cadena, $transaccion, $acceso),
        'consultar' => new ConsultarMovimientos(filtrosDeConsulta($config), $bitacora, $cadena, $transaccion, $acceso),
        'detalle' => new VerDetalleMovimiento($bitacora, $cadena, $transaccion, $acceso),
        'verificar' => new VerificarIntegridad($bitacora, $cadena, $transaccion, $acceso),
    ];
}

/** MVC: el Controlador recibe solo casos de uso (el Modelo); las rutas lo conectan con el Kernel. */
function construirKernel(array $casos, Autenticador $autenticador): Kernel
{
    $controlador = new AuditoriaControlador($casos['registrar'], $casos['consultar'], $casos['detalle'], $casos['verificar']);

    return new Kernel(Rutas::definir($controlador), $autenticador);
}

/** Punto de entrada real. AUDITORIA_PERSISTENCIA elige PostgreSQL («pdo», por defecto) o «memoria» (demo sin base). */
function crearKernel(array $config, ?ConjuntoAdaptadores $adaptadores = null): Kernel
{
    $adaptadores ??= FabricaAdaptadores::crear($config);
    $casos = construirCasosDeUso($config, $adaptadores->bitacora, $adaptadores->transaccion, new PoliticaAccesoEstatica($config['permisos']),
        new RelojSistema());

    return construirKernel($casos, new AutenticadorTokenDemo($config['tokens_demo']));
}
