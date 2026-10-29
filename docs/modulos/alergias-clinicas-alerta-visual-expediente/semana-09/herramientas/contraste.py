"""Contraste WCAG 2.2 de los colores de micro-his/src/Presentacion/Web/vistas/layout.php.
Uso: python contraste.py   (sale con 1 si algún par no cumple su mínimo)."""
import sys


def luminancia(hex_color: str) -> float:
    canales = [int(hex_color[i:i + 2], 16) / 255 for i in (1, 3, 5)]
    lineal = [c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4 for c in canales]
    return 0.2126 * lineal[0] + 0.7152 * lineal[1] + 0.0722 * lineal[2]


def contraste(a: str, b: str) -> float:
    claro, oscuro = sorted((luminancia(a), luminancia(b)), reverse=True)
    return (claro + 0.05) / (oscuro + 0.05)


# (descripción, color primer plano, fondo, mínimo WCAG)
PARES = [
    ("Texto sobre fondo CRITICA", "#1d2433", "#fde8e8", 4.5),
    ("Texto sobre fondo ALTA", "#1d2433", "#fff1e6", 4.5),
    ("Texto sobre fondo MODERADA", "#1d2433", "#fff8db", 4.5),
    ("Texto sobre fondo INFORMATIVA / SIN_REGISTRO", "#1d2433", "#f3f4f6", 4.5),
    ("Texto sobre blanco", "#1d2433", "#ffffff", 4.5),
    ("Mensaje de error sobre blanco", "#a4161a", "#ffffff", 4.5),
    ("Borde CRITICA sobre su fondo (no textual)", "#a4161a", "#fde8e8", 3.0),
    ("Borde ALTA sobre su fondo (no textual)", "#9a3412", "#fff1e6", 3.0),
    ("Borde MODERADA sobre su fondo (no textual)", "#8a6100", "#fff8db", 3.0),
    ("Borde INFORMATIVA sobre su fondo (no textual)", "#4b5563", "#f3f4f6", 3.0),
    ("Contorno de foco sobre blanco (no textual)", "#1d4ed8", "#ffffff", 3.0),
    ("Contorno de foco sobre fondo CRITICA (no textual)", "#1d4ed8", "#fde8e8", 3.0),
]

fallas = 0
for nombre, frente, fondo, minimo in PARES:
    valor = contraste(frente, fondo)
    ok = valor >= minimo
    fallas += 0 if ok else 1
    print(f"{'OK   ' if ok else 'FALLA'} {valor:5.2f}:1 (mín {minimo}) {nombre} {frente} / {fondo}")
print(f"RESULTADO: {len(PARES)} pares, {fallas} fallas")
sys.exit(1 if fallas else 0)
