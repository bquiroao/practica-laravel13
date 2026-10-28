"""Mide el contraste WCAG 2.2 de los pares de color de los wireframes de Auditoría (semana 8).

Uso:
    python contraste.py            # tabla de los colores actuales y de los propuestos
    python contraste.py --solo-propuestos

Termina con código 1 si algún color PROPUESTO no cumple su umbral: el criterio de
aceptación del backlog (B-01, B-02) se verifica con un comando.
"""
import sys

# Umbrales WCAG 2.2 nivel AA
TEXTO_NORMAL = 4.5   # 1.4.3, texto < 18 pt (o < 14 pt en negrita)
TEXTO_GRANDE = 3.0   # 1.4.3, texto >= 18 pt (o >= 14 pt en negrita)
NO_TEXTO = 3.0       # 1.4.11, bordes de campos e indicadores de estado
EXENTO = 0.0         # 1.4.3 exime los componentes deshabilitados


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
    ("C-01", "Etiquetas de campo (10 pt negrita)", "W1 W3 W5", "8A8F96", "FFFFFF", umbral_texto(10, True), "5F6670"),
    ("C-02", "Encabezados de tabla (10 pt negrita)", "W2 W4", "8A8F96", "F2F3F5", umbral_texto(10, True), "5F6670"),
    ("C-03", "Pistas: zona horaria, ancla (10 pt cursiva)", "W1 W5", "8A8F96", "FFFFFF", umbral_texto(10, False), "5F6670"),
    ("C-04", "Títulos de estado INICIAL/CARGA/VACÍO (10 pt negrita)", "W1 W2 W5", "8A8F96", "F2F3F5", umbral_texto(10, True), "5F6670"),
    ("C-05", "Títulos de error (10 pt negrita) sobre rosa", "W1 W3 W6", "8A8F96", "FDECEC", umbral_texto(10, True), "5F6670"),
    ("C-06", "Contador «50 movimientos…» (10 pt)", "W2", "8A8F96", "FFFFFF", umbral_texto(10, False), "5F6670"),
    ("C-07", "Ícono de ayuda «?» (11 pt negrita)", "W1", "8A8F96", "FFFFFF", umbral_texto(11, True), "5F6670"),
    ("C-08", "Borde de campo de texto y de selector", "W1 W5", "9AA0A6", "FFFFFF", NO_TEXTO, "7D838A"),
    ("C-09", "Borde del radio de alcance", "W4", "9AA0A6", "FFFFFF", NO_TEXTO, "7D838A"),
    ("C-10", "Texto general (11 pt)", "Todas", "2B2F33", "FFFFFF", umbral_texto(11, False), None),
    ("C-11", "Cabecera: hospital o red (12 pt negrita)", "Todas", "FFFFFF", "3D4F6B", umbral_texto(12, True), None),
    ("C-12", "Botón primario «Buscar» (11 pt negrita)", "W1 W4", "FFFFFF", "3D4F6B", umbral_texto(11, True), None),
    ("C-13", "Aviso M-06 de meta-auditoría (10 pt)", "W1", "7A4B00", "FFF4E5", umbral_texto(10, False), None),
    ("C-14", "Insignia «Corrige a #n» (9 pt negrita)", "W2", "1F4E79", "E3F2FD", umbral_texto(9, True), None),
    ("C-15", "Insignia «Corregido por #n» (9 pt negrita)", "W2", "7A4B00", "FFF4E5", umbral_texto(9, True), None),
    ("C-16", "Resultado «denegado» (10 pt)", "W2", "A33A3A", "FFFFFF", umbral_texto(10, False), None),
    ("C-17", "Mensaje de error junto al campo (10 pt)", "W5", "A33A3A", "FFFFFF", umbral_texto(10, False), None),
    ("C-18", "Borde de campo con error", "W5", "A33A3A", "FFFFFF", NO_TEXTO, None),
    ("C-19", "Texto de resultado ÍNTEGRA (10.5 pt)", "W5", "2B2F33", "E7F4EC", umbral_texto(10.5, False), None),
    ("C-20", "Texto de resultado ALTERADA / errores (10.5 pt)", "W5 W6", "2B2F33", "FDECEC", umbral_texto(10.5, False), None),
    ("C-21", "Texto de resultado TRUNCADA (10.5 pt)", "W5", "2B2F33", "FFF4E5", umbral_texto(10.5, False), None),
    ("C-22", "Anillo de foco (2 pt)", "W4", "3D4F6B", "FFFFFF", NO_TEXTO, None),
    ("C-23", "Título «Protección de datos» (11 pt negrita)", "W6", "A33A3A", "FFFFFF", umbral_texto(11, True), None),
    ("C-24", "Botón deshabilitado «Verificar»", "W5", "9AA0A6", "E4E6EA", EXENTO, None),
]


def main() -> int:
    solo_propuestos = "--solo-propuestos" in sys.argv
    print(f"{'ID':5} {'Uso':52} {'Actual':>7} {'Mín.':>5} {'Estado':8} {'Propuesto':>10} {'Nuevo':>6} {'Estado':8}")
    print("-" * 108)
    fallan = 0
    for ident, uso, _pantallas, fg, bg, minimo, propuesta in PARES:
        if solo_propuestos and propuesta is None:
            continue
        actual = contraste(fg, bg)
        estado = "EXENTO" if minimo == EXENTO else ("CUMPLE" if actual >= minimo else "FALLA")
        if propuesta:
            nuevo = contraste(propuesta, bg)
            nuevo_estado = "CUMPLE" if nuevo >= minimo else "FALLA"
            fallan += nuevo_estado == "FALLA"
            print(f"{ident:5} {uso:52} {actual:7.2f} {minimo:5.1f} {estado:8} {'#' + propuesta:>10} {nuevo:6.2f} {nuevo_estado:8}")
        else:
            print(f"{ident:5} {uso:52} {actual:7.2f} {minimo:5.1f} {estado:8} {'—':>10} {'':6} {'':8}")
    print("-" * 108)
    print(f"Propuestas que no cumplen: {fallan}")
    return 1 if fallan else 0


if __name__ == "__main__":
    sys.exit(main())
