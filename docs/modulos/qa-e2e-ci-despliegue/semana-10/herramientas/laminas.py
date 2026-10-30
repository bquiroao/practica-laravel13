"""Arma una lámina por escenario móvil con las capturas de verificar-responsive.cjs (semana 10).

Uso:  python laminas.py <carpeta-capturas> <carpeta-de-salida>
"""
import sys
from pathlib import Path
from PIL import Image, ImageDraw, ImageFont

TITULO = ImageFont.truetype("C:/Windows/Fonts/calibrib.ttf", 34)
TEXTO = ImageFont.truetype("C:/Windows/Fonts/calibri.ttf", 24)

ESCENARIOS = [
    ("escenario-1-operador-360.png", "Escenario 1 · Operador de despliegue: registra el smoke desde el teléfono, en la sala de servidores (360 px)", [
        ("m1-versiones-ops-360", "1. Solo ve lo que puede desplegar: 2.4.1"),
        ("m4-desplegar-base-360", "2. Guía y digest aprobado completo"),
        ("m4-desplegar-digest-distinto-360", "3. Pegó otro digest: el bloque distinto, a la vista"),
        ("m4-desplegar-smoke-falla-360", "4. Digest correcto; el smoke falla"),
        ("m4-desplegar-confirmar-falla-360", "5. Confirma con la consecuencia"),
        ("m4-desplegar-revertido-360", "6. REVERTIDO a 2.4.0"),
    ]),
    ("escenario-2-release-390.png", "Escenario 2 · Responsable de release: sigue la ejecución y registra el gate camino a la reunión (390 px)", [
        ("m1-versiones-base-390", "1. Dos versiones piden atención"),
        ("m2-ejecucion-sin-conexion-390", "2. Sin señal: lo último, con su hora"),
        ("m2-ejecucion-nuevo-390", "3. Vuelve la señal: se anuncia solo lo nuevo"),
        ("m2-ejecucion-completada-390", "4. Completada → evaluar"),
        ("m3-gate-confirmar-390", "5. La decisión la calcula el servidor"),
        ("m3-gate-registrada-390", "6. Registrada, igual a la vista previa"),
    ]),
]


def main() -> int:
    origen, destino = Path(sys.argv[1]), Path(sys.argv[2])
    destino.mkdir(parents=True, exist_ok=True)
    for nombre, titulo, pasos in ESCENARIOS:
        ims = [Image.open(origen / f"{c}.png").convert("RGB") for c, _ in pasos]
        ancho = 340
        ims = [i.resize((ancho, round(i.height * ancho / i.width)), Image.LANCZOS) for i in ims]
        alto = max(i.height for i in ims)
        m, cab, pie = 20, 70, 70
        lamina = Image.new("RGB", (m + len(ims) * (ancho + m), cab + alto + pie), "white")
        d = ImageDraw.Draw(lamina)
        d.text((m, 18), titulo, fill=(61, 79, 107), font=TITULO)
        for k, (im, (_, leyenda)) in enumerate(zip(ims, pasos)):
            x = m + k * (ancho + m)
            lamina.paste(im, (x, cab))
            d.rectangle([x - 1, cab - 1, x + ancho, cab + im.height], outline=(201, 205, 210), width=2)
            lineas, actual = [], ""
            for palabra in leyenda.split():
                prueba = (actual + " " + palabra).strip()
                if d.textlength(prueba, font=TEXTO) > ancho:
                    lineas.append(actual)
                    actual = palabra
                else:
                    actual = prueba
            lineas.append(actual)
            for j, linea in enumerate(lineas[:2]):
                d.text((x, cab + alto + 8 + j * 28), linea, fill=(43, 47, 51), font=TEXTO)
        lamina.save(destino / nombre, optimize=True)
        print(nombre, lamina.size)
    return 0


if __name__ == "__main__":
    sys.exit(main())
