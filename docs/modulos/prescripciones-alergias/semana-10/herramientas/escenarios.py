"""Arma las láminas de los dos escenarios móviles con las capturas de verificar-responsive.cjs.

Uso (desde semana-10/):
    python herramientas/escenarios.py capturas escenarios
"""
import sys
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

CAPTURAS = Path(sys.argv[1])
SALIDA = Path(sys.argv[2])

ESCENARIOS = {
    "e1-medico-ronda-senal-debil-360": [
        ("1 · Ítems y alergias", "m1-prescribir-base-360"),
        ("2 · Advertencia", "m2-verificacion-advertencia-360"),
        ("3 · Justificar", "m2-verificacion-justificar-360"),
        ("4 · Conexión lenta", "m3-emision-lenta-360"),
        ("5 · Incierto", "m3-emision-incierto-360"),
        ("6 · Reintento: YA_EMITIDA", "m3-emision-ya-emitida-360"),
    ],
    "e2-guardia-anula-red-intermitente-320": [
        ("1 · Prescripción #123", "m4-prescripcion-medico-320"),
        ("2 · Motivo corto", "m4-prescripcion-anular-error-320"),
        ("3 · Motivo válido", "m4-prescripcion-anular-320"),
        ("4 · Corte: incierto", "m4-prescripcion-anular-incierto-320"),
        ("5 · 409 reconciliado", "m4-prescripcion-anulada-por-usted-320"),
    ],
}

try:
    FUENTE = ImageFont.truetype("C:/Windows/Fonts/segoeuib.ttf", 30)
except OSError:
    FUENTE = ImageFont.load_default()

SALIDA.mkdir(parents=True, exist_ok=True)
for nombre, pasos in ESCENARIOS.items():
    imagenes = [Image.open(CAPTURAS / f"{archivo}.png").convert("RGB") for _, archivo in pasos]
    alto = max(i.height for i in imagenes)
    ancho = imagenes[0].width
    margen, cabecera = 24, 64
    lamina = Image.new("RGB", (len(imagenes) * (ancho + margen) + margen, alto + cabecera + margen), "white")
    dibujo = ImageDraw.Draw(lamina)
    for n, ((titulo, _), img) in enumerate(zip(pasos, imagenes)):
        x = margen + n * (ancho + margen)
        dibujo.text((x, 16), titulo, fill=(61, 79, 107), font=FUENTE)
        lamina.paste(img, (x, cabecera))
        dibujo.rectangle([x - 1, cabecera - 1, x + ancho, cabecera + img.height], outline=(201, 205, 210), width=2)
    destino = SALIDA / f"{nombre}.png"
    lamina.save(destino, optimize=True)
    print(destino.name, lamina.size)
