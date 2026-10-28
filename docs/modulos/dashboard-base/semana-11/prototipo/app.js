'use strict';
// Prototipo navegable del dashboard (semana 11): rutas por hash, menú y tarjetas por permiso (R-02), tarjetas que cargan
// y fallan por su cuenta (R-03), carga con límite de 10 s (R-04), vacío distinto de error (R-05) y reintento a pedido (R-08).
const MODULOS = { pacientes: 'Pacientes', citas: 'Citas', camas: 'Camas', usuarios: 'Usuarios' };
const PERMISOS = { enfermeria: ['camas', 'pacientes', 'citas'], admin: ['pacientes', 'citas', 'camas', 'usuarios'] };
const ROLES = { enfermeria: 'enfermería', admin: 'administrador' };
const TARJETAS = { // textos de Dominio/Dashboard.php del Micro-HIS
  camas: { titulo: 'Camas disponibles', nombre: 'las camas disponibles', unidad: ['cama disponible', 'camas disponibles'], vacio: 'No hay camas disponibles ahora.' },
  pacientes: { titulo: 'Pacientes', nombre: 'pacientes', unidad: ['paciente activo', 'pacientes activos'], vacio: 'Aún no hay pacientes activos: registra el primero.' },
  citas: { titulo: 'Citas de hoy', nombre: 'las citas de hoy', unidad: ['cita hoy', 'citas hoy'], vacio: 'No hay citas hoy: agenda una.' },
};
const CONTEOS = { camas: 7, pacientes: 42, citas: 18 };
const LIMITE_MS = 10000;
let rol = 'enfermeria';

const permitido = (modulo) => PERMISOS[rol].includes(modulo);
const tarjetasDelRol = () => PERMISOS[rol].filter((m) => TARJETAS[m]); // el orden de PERMISOS es el orden del rol

/** Simula la API: «Citas de hoy» responde según el panel; las demás tarjetas responden bien. */
function pedirConteo(id) {
  const modo = id === 'citas' ? document.getElementById('simular').value : 'normal';
  if (modo === 'lenta') return new Promise(() => {}); // nunca responde: la corta el límite de 10 s
  return new Promise((ok, falla) => setTimeout(() => (modo === 'falla' ? falla(new Error('E5')) : ok(modo === 'vacio' ? 0 : CONTEOS[id])), 100));
}

function cargarTarjeta(id) {
  const tarjeta = document.getElementById(`tarjeta-${id}`);
  const cuerpo = tarjeta.querySelector('.cuerpo');
  const t = TARJETAS[id];
  tarjeta.setAttribute('aria-busy', 'true');
  cuerpo.innerHTML = '<div class="esqueleto"></div><span class="oculto">Cargando…</span>';
  const limite = new Promise((_, falla) => setTimeout(() => falla(new Error('E2')), LIMITE_MS));
  return Promise.race([pedirConteo(id), limite]).then((total) => {
    cuerpo.innerHTML = total === 0
      ? `<p>${t.vacio}</p><a class="boton" href="#modulo/${id}">Ir a ${MODULOS[id]}</a>`
      : `<p><span class="numero">${total}</span> ${t.unidad[total === 1 ? 0 : 1]}</p><a class="boton" href="#modulo/${id}">Ver ${MODULOS[id].toLowerCase()}</a>`;
  }, () => {
    cuerpo.innerHTML = `<p>No pudimos cargar ${t.nombre}. Intenta de nuevo.</p><button type="button" class="reintentar">Reintentar</button>`;
    cuerpo.querySelector('.reintentar').addEventListener('click', (e) => reintentar(id, e.currentTarget));
  }).finally(() => tarjeta.setAttribute('aria-busy', 'false'));
}

/** R-08: solo esa tarjeta, y solo cuando el usuario lo pide; el foco no se pierde mientras carga (semana 9, B-05). */
function reintentar(id, boton) {
  boton.setAttribute('aria-disabled', 'true');
  boton.textContent = 'Cargando…';
  cargarTarjeta(id).then(() => document.querySelector(`#tarjeta-${id} h2`)?.focus());
}

const vistas = {
  inicio: () => ['Inicio', `<h1 tabindex="-1">Inicio</h1><div class="tarjetas">
    ${tarjetasDelRol().map((id) => `<section class="tarjeta" id="tarjeta-${id}" aria-busy="true">
      <h2 tabindex="-1">${TARJETAS[id].titulo}</h2><div class="cuerpo" aria-live="polite"></div></section>`).join('')}</div>`,
    () => tarjetasDelRol().forEach(cargarTarjeta)],

  modulo: (id) => {
    if (!MODULOS[id]) return vistas.inicio();
    if (!permitido(id)) return ['Sin acceso', `<h1 tabindex="-1">No tienes acceso a esa sección</h1>
      <p>Tu rol no incluye «${MODULOS[id]}». Si lo necesitas, pídelo al administrador del hospital.</p>
      <div class="acciones"><a class="boton principal" href="#inicio">Volver al inicio</a></div>`];
    return [MODULOS[id], `<h1 tabindex="-1">${MODULOS[id]}</h1><p>El contenido de este módulo está fuera del prototipo.</p>
      <div class="acciones"><a class="boton" href="#inicio">Volver al inicio</a></div>`];
  },
};

function pintarMenu(actual) {
  const enlaces = [['inicio', 'Inicio'], ...PERMISOS[rol].map((m) => [`modulo/${m}`, MODULOS[m]])];
  document.getElementById('menu').innerHTML = enlaces.map(([ruta, texto]) =>
    `<a href="#${ruta}"${ruta === actual ? ' aria-current="page"' : ''}>${texto}</a>`).join('');
}

function mostrar() {
  const ruta = location.hash.slice(1) || 'inicio';
  const [nombre, ...args] = ruta.split('/');
  const [titulo, html, despues] = (vistas[nombre] ?? vistas.inicio)(...args);
  document.getElementById('contenido').innerHTML = html;
  pintarMenu(ruta);
  document.title = `${titulo} · Hospital HIS`;
  document.querySelector('main h1').focus();
  despues?.();
}

document.getElementById('rol').addEventListener('change', (e) => {
  rol = e.target.value;
  document.getElementById('rol-actual').textContent = ROLES[rol];
  mostrar();
});
document.querySelector('.saltar').addEventListener('click', (e) => { e.preventDefault(); document.getElementById('contenido').focus(); });
document.getElementById('rol-actual').textContent = ROLES[rol];
window.addEventListener('hashchange', mostrar);
mostrar();
