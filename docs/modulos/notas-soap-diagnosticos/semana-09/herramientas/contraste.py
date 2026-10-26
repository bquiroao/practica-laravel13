"""Mide el contraste WCAG 2.2 de los pares de color de los wireframes de Notas SOAP (semana 8).

Uso:
    python herramientas/contraste.py                    # colores actuales y propuestos
    python herramientas/contraste.py --solo-propuestos  # solo las filas con propuesta

Termina con código 1 si algún color PROPUESTO no cumple su umbral, para que el
criterio de aceptación del backlog se pueda verificar con un comando.
"""
import sys

TEXTO_NORMAL = 4.5  # 1.4.3: texto < 18 pt (o < 14 pt en negrita)
TEXTO_GRANDE = 3.0  # 1.4.3: texto >= 18 pt (o >= 14 pt en negrita)
NO_TEXTO = 3.0      # 1.4.11: bordes de campos, indicadores de estado y de foco
EXENTO = 0.0        # 1.4.3 exime los componentes inactivos


def luminancia(hex_color: str) -> float:
    canales = [int(hex_color[i:i + 2], 16) / 255 for i in (0, 2, 4)]
    lineal = [c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4 for c in canales]
    return 0.2126 * lineal[0] + 0.7152 * lineal[1] + 0.0722 * lineal[2]


def contraste(a: str, b: str) -> float:
    la, lb = sorted((luminancia(a), luminancia(b)), reverse=True)
    return (la + 0.05) / (lb + 0.05)


def umbral_texto(pt: float, negrita: bool) -> float:
    return TEXTO_GRANDE if pt >= 18 or (negrita and pt >= 14) else TEXTO_NORMAL


# (id, uso, pantallas, primer plano, fondo, umbral, propuesta o None)
# Los colores salen tal cual del generador de wireframes de la semana 8.
PARES = [
    ("C-01", "Encabezados de tabla (10.5 pt negrita)", "W1", "6B7785", "F4F6F8", umbral_texto(10.5, True), "5A6472"),
    ("C-02", "Fila de versión corregida (10.5 pt)", "W1", "6B7785", "F7F7F7", umbral_texto(10.5, False), "5A6472"),
    ("C-03", "Etiqueta «Borrador» (10 pt negrita)", "W3", "C98A12", "FFF4E5", umbral_texto(10, True), "8A6100"),
    ("C-04", "Borde de campo de texto", "W2 W4", "A9B3BD", "FFFFFF", NO_TEXTO, "767F8A"),
    ("C-05", "Botón ocupado «Guardando…» (inactivo)", "W2", "FFFFFF", "7FA9A6", EXENTO, None),
    ("C-06", "Indicador de foco (borde gris 2.5 pt)", "W3", "8A96A3", "FFFFFF", NO_TEXTO, "1F6F6B"),
    ("C-07", "Etiquetas y pistas sobre blanco (10-10.5 pt)", "W1 W2 W3 W4", "6B7785", "FFFFFF", umbral_texto(10, False), "5A6472"),
    ("C-08", "Aviso de éxito (11.5 pt negrita)", "W2 W3 W4", "2E7D32", "E8F5E9", umbral_texto(11.5, True), "1B5E20"),
    ("C-09", "Texto general (11 pt)", "Todas", "24323F", "FFFFFF", umbral_texto(11, False), None),
    ("C-10", "Cabecera: hospital activo (12.5 pt negrita)", "Todas", "FFFFFF", "1F6F6B", umbral_texto(12.5, True), None),
    ("C-11", "Botón primario (11.5 pt negrita)", "W1-W5", "FFFFFF", "1F6F6B", umbral_texto(11.5, True), None),
    ("C-12", "Enlace «Ver» (10.5 pt negrita)", "W1", "1F6F6B", "FFFFFF", umbral_texto(10.5, True), None),
    ("C-13", "Error junto al campo (9.5 pt)", "W2 W4", "B3261E", "FFFFFF", umbral_texto(9.5, False), None),
    ("C-14", "Borde de campo con error", "W2 W4", "B3261E", "FFFFFF", NO_TEXTO, None),
    ("C-15", "Aviso de error (10-11.5 pt negrita)", "W1 W2 W3 W4 W5", "B3261E", "FBEAEA", umbral_texto(10, True), None),
    ("C-16", "Aviso de servicio no disponible (10 pt negrita)", "W5", "8A5A00", "FFF4E5", umbral_texto(10, True), None),
    ("C-17", "Aviso de versión más reciente (10.5 pt negrita)", "W3 W4", "2F5D8A", "EEF3FA", umbral_texto(10.5, True), None),
    ("C-18", "Filtro activo: borde (indicador de estado)", "W1", "1F6F6B", "FFFFFF", NO_TEXTO, None),
    ("C-19", "Letras S, O, A, P del detalle (13 pt negrita)", "W3", "1F6F6B", "F4F6F8", umbral_texto(13, True), None),
    ("C-20", "Texto sobre fondo gris claro (11 pt)", "W3 W4", "24323F", "F4F6F8", umbral_texto(11, False), None),
]


def main() -> int:
    solo_propuestos = "--solo-propuestos" in sys.argv
    fallas_propuestas = 0
    print(f"{'ID':5} {'Uso':56} {'Actual':>7} {'Mín.':>5} {'Estado':7} {'Propuesto':>9} {'Nuevo':>6} {'Estado':7}")
    print("-" * 110)
    for ident, uso, _pantallas, fg, bg, minimo, propuesta in PARES:
        if solo_propuestos and propuesta is None:
            continue
        actual = contraste(fg, bg)
        estado = "EXENTO" if minimo == EXENTO else ("CUMPLE" if actual >= minimo else "FALLA")
        if propuesta:
            nuevo = contraste(propuesta, bg)
            estado_nuevo = "CUMPLE" if nuevo >= minimo else "FALLA"
            fallas_propuestas += estado_nuevo == "FALLA"
            columnas = f"{'#' + propuesta:>9} {nuevo:6.2f} {estado_nuevo:7}"
        else:
            columnas = f"{'—':>9}"
        print(f"{ident:5} {uso:56} {actual:7.2f} {minimo:5.1f} {estado:7} {columnas}")
    actuales_fallan = sum(1 for p in PARES if p[5] != EXENTO and contraste(p[3], p[4]) < p[5])
    print("-" * 110)
    print(f"Pares medidos: {len(PARES)} · Fallan hoy: {actuales_fallan} · Propuestas que no cumplen: {fallas_propuestas}")
    return 1 if fallas_propuestas else 0


if __name__ == "__main__":
    sys.exit(main())
