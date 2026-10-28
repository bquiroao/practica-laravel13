const fs=require('node:fs');const path=require('node:path');
const base=path.resolve(__dirname,'../..');
const files=fs.readdirSync(path.join(base,'semana-08/wireframes')).filter(f=>f.endsWith('.svg'));
const lines=['Inspección de fuentes SVG, 2026-10-28. No prueba de controles HTML ni dictamen WCAG.'];
for(const file of files){const s=fs.readFileSync(path.join(base,'semana-08/wireframes',file),'utf8');lines.push(`${file}: lienzo ${s.match(/width="(\d+)" height="(\d+)"/).slice(1).join('x')}; role img=${s.includes('role="img"')}; title/desc=${s.includes('<title')&&s.includes('<desc')}; tabindex=${s.includes('tabindex=')}; controles nativos HTML=${/<(input|button|select|textarea)\b/.test(s)}`);}
function lum(hex){const c=hex.match(/\w\w/g).map(x=>parseInt(x,16)/255).map(v=>v<=.04045?v/12.92:((v+.055)/1.055)**2.4);return c[0]*.2126+c[1]*.7152+c[2]*.0722;}
for(const [fg,bg] of [['ffffff','126574'],['243f47','ffffff'],['3c5962','f2f6f7'],['526b73','f2f6f7'],['d0e2e6','153e49']]){const a=lum(fg),b=lum(bg);lines.push(`Contraste #${fg}/#${bg}: ${((Math.max(a,b)+.05)/(Math.min(a,b)+.05)).toFixed(2)}:1`);}
fs.writeFileSync(path.join(base,'semana-09/evidencia/inspeccion.txt'),lines.join('\n'));console.log(lines.join('\n'));