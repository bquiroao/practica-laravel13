# Auditoría automática de la web del Micro-HIS (semana 9). Requiere Python + Playwright y el servidor:
#   (en micro-his/) php -S 127.0.0.1:8087 public/index.php   con la base recién creada
#   (en semana-09/) python evidencia/auditoria-navegador.py
import json, sys
from playwright.sync_api import sync_playwright
B='http://127.0.0.1:8087'
EV='evidencia/'
out=[]
def p(*a): out.append(' '.join(str(x) for x in a))
with sync_playwright() as pw:
    b=pw.chromium.launch(); pg=b.new_page(viewport={'width':1280,'height':800})
    errores=[]; pg.on('pageerror', lambda e: errores.append(str(e)))
    pg.goto(B+'/')
    p('titulo:', pg.title(), '| lang:', pg.evaluate("document.documentElement.lang"))
    orden=[]
    for i in range(10):
        pg.keyboard.press('Tab')
        orden.append(pg.evaluate("(()=>{const e=document.activeElement;return e.tagName.toLowerCase()+(e.id?'#'+e.id:'')+(e.name?'[name='+e.name+']':'')})()"))
    p('orden de tabulacion W-01:', ' > '.join(orden))
    # foco visible en el botón
    pg.focus('button'); 
    p('outline del boton con foco (teclado):', pg.evaluate("getComputedStyle(document.querySelector('button')).outlineStyle+' '+getComputedStyle(document.querySelector('button')).outlineColor"))
    # etiquetas
    sin=pg.evaluate("""[...document.querySelectorAll('input,select')].filter(e=>e.type!=='hidden').filter(e=>!(e.labels&&e.labels.length)).map(e=>e.name)""")
    p('controles sin etiqueta:', sin)
    pg.screenshot(path=EV+'h-inicio-w01.png', full_page=True)
    # H-01: código inválido llega hasta la lista de camas
    pg.fill('#paciente','PAC-451'); pg.click('button')
    pg.wait_for_load_state()
    p('codigo PAC-451 -> pantalla:', pg.title(), '| radios de cama:', pg.locator('input[type=radio]').count())
    pg.screenshot(path=EV+'h-codigo-invalido-llega-a-camas.png', full_page=True)
    # H-02/H-03: error de formato al confirmar
    pg.click('button'); pg.wait_for_load_state()
    p('al confirmar con PAC-451 -> titulo:', pg.title())
    p('  foco activo:', pg.evaluate("document.activeElement.tagName"), '| input aria-invalid:', pg.evaluate("document.querySelector('#paciente')?.getAttribute('aria-invalid')"), '| aria-describedby:', pg.evaluate("document.querySelector('#paciente')?.getAttribute('aria-describedby')"))
    pg.screenshot(path=EV+'h-error-formato.png', full_page=True)
    # H-07: no hay confirmación previa; conflicto
    pg.goto(B+'/camas?usuario=usr-adm-01&paciente=PAC-00451&servicio=MEDICINA_INTERNA&tipo=GENERAL&origen=EMERGENCIA')
    p('botones en lista de camas:', pg.locator('button').all_inner_texts())
    pg.click('button'); pg.wait_for_load_state(); p('tras un clic -> titulo:', pg.title())
    pg.goto(B+'/camas?usuario=usr-adm-01&paciente=PAC-00452&servicio=MEDICINA_INTERNA&tipo=GENERAL&origen=EMERGENCIA')
    # otra admisión toma B-202 mientras tanto
    pg2=b.new_page(); pg2.goto(B+'/camas?usuario=usr-sup-01&paciente=PAC-00453&servicio=MEDICINA_INTERNA&tipo=GENERAL&origen=EMERGENCIA'); pg2.click('button'); pg2.wait_for_load_state()
    p('otra pestaña confirmo:', pg2.title())
    pg.click('button'); pg.wait_for_load_state()
    p('conflicto -> titulo:', pg.title(), '| alerta:', pg.locator('[role=alert] strong').inner_text())
    p('  foco activo tras conflicto:', pg.evaluate("document.activeElement.tagName"), '| codigo tecnico visible:', pg.locator('[role=alert] .aviso').inner_text())
    pg.screenshot(path=EV+'h-conflicto-cama.png', full_page=True)
    p('textos de servicio en la lista:', pg.locator('fieldset label').all_inner_texts()[:2])
    p('doble envio: boton deshabilitado al enviar?', pg.evaluate("document.querySelector('form')?.getAttribute('onsubmit')"))
    p('errores JavaScript:', errores)
    b.close()
print('\n'.join(out))
open('evidencia/auditoria-navegador.txt','w').write('\n'.join(out)+'\n')
