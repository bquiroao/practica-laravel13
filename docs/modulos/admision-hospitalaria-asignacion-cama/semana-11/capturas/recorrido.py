# Recorrido automático del prototipo (semana 11): camino feliz y error crítico en 1280x800 y 375x760.
# Requiere Python + Playwright. Uso, desde semana-11/:  python capturas/recorrido.py
from playwright.sync_api import sync_playwright
import os
import pathlib
R=str(pathlib.Path(__file__).resolve().parent.parent)+'/'
URL='file://'+R+'prototipo/index.html'
log=[]
def p(*a): log.append(' '.join(str(x) for x in a))
def revisar(pg, nombre):
    sw=pg.evaluate('document.documentElement.scrollWidth'); cw=pg.evaluate('document.documentElement.clientWidth')
    foco=pg.evaluate('document.activeElement.tagName+(document.activeElement.id?"#"+document.activeElement.id:"")')
    p(f'  {nombre}: titulo="{pg.title()}" foco={foco} scroll_horizontal={"SI" if sw>cw else "no"} ({sw}/{cw})')
with sync_playwright() as pw:
    b=pw.chromium.launch()
    for disp,(w,h) in {'desktop':(1280,800),'movil':(375,760)}.items():
        pg=b.new_page(viewport={'width':w,'height':h})
        errs=[]; pg.on('pageerror',lambda e: errs.append(str(e))); pg.on('console',lambda m: errs.append(m.text) if m.type=='error' else None)
        pg.goto(URL); p(f'== {disp} {w}x{h}')
        # camino feliz
        pg.fill('#paciente','PAC-451'); pg.keyboard.press('Tab')
        p('  codigo invalido al salir del campo: aria-invalid=',pg.get_attribute('#paciente','aria-invalid'),'describedby=',pg.get_attribute('#paciente','aria-describedby'))
        pg.fill('#paciente','PAC-00451'); pg.keyboard.press('Tab')
        revisar(pg,'01 datos'); pg.screenshot(path=f'{R}capturas/{disp}-01-datos.png', full_page=True)
        pg.click('button[type=submit]'); pg.wait_for_selector('input[name=cama]')
        revisar(pg,'02 camas'); pg.screenshot(path=f'{R}capturas/{disp}-02-camas.png', full_page=True)
        pg.click('button[type=submit]'); revisar(pg,'03 confirmar'); pg.screenshot(path=f'{R}capturas/{disp}-03-confirmar.png', full_page=True)
        pg.click('#ok'); p('  boton tras clic: disabled=',pg.is_disabled('#ok'), 'texto=',pg.inner_text('#ok'))
        pg.wait_for_selector('.exito'); revisar(pg,'04 resultado'); pg.screenshot(path=f'{R}capturas/{disp}-04-resultado.png', full_page=True)
        # error crítico en una carga nueva: otra admisión toma la cama elegida (RN-01)
        pg.goto(URL); pg.fill('#paciente','PAC-00452'); pg.check('#demo-conflicto')
        pg.click('button[type=submit]'); pg.wait_for_selector('input[name=cama]')
        elegida=pg.inner_text('.tarjeta strong'); pg.click('button[type=submit]'); pg.click('#ok')
        pg.wait_for_selector('#aviso'); pg.wait_for_selector('input[name=cama]')
        revisar(pg,'05 error critico'); p('  aviso:',pg.inner_text('#aviso strong'),'| cama perdida:',elegida,'| alternativas:',pg.inner_text('fieldset legend'), pg.locator('.tarjeta strong').all_inner_texts())
        pg.screenshot(path=f'{R}capturas/{disp}-05-error-critico.png', full_page=True)
        pg.uncheck('#demo-conflicto'); pg.click('button[type=submit]'); pg.click('#ok'); pg.wait_for_selector('.exito')
        revisar(pg,'06 recuperado'); p('  resultado:', pg.inner_text('.exito p'))
        pg.screenshot(path=f'{R}capturas/{disp}-06-recuperado.png', full_page=True)
        p('  errores JavaScript/consola:', errs if errs else 'ninguno')
        pg.close()
    b.close()
open(R+'capturas/recorrido.txt','w').write('\n'.join(log)+'\n'); print('\n'.join(log))
