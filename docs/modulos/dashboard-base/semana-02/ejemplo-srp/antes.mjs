// ANTES: una sola función hace todo para una tarjeta del dashboard: llamar a la API con
// límite de tiempo, decidir el estado, redactar el mensaje y cerrar la sesión ante un 401.
// Cambia por cuatro razones: contrato HTTP, reglas de estados UX, textos y manejo de sesión.
export async function cargarTarjetaAntes(tarjeta, { obtener, sesion, limiteMs = 10000 }) {
  let respuesta;
  try {
    respuesta = await Promise.race([
      obtener(`/dashboard/cards/${tarjeta.id}`),
      new Promise((_, rechazar) => setTimeout(() => rechazar(new Error('tiempo')), limiteMs)),
    ]);
  } catch {
    return { estado: 'error', texto: `No pudimos cargar ${tarjeta.titulo}. Intenta de nuevo.` };
  }
  if (respuesta.status === 401) {
    sesion.limpiar();
    return { estado: 'sesion_expirada', texto: 'Tu sesión terminó. Vuelve a iniciar sesión.' };
  }
  if (respuesta.status === 403) return { estado: 'oculta', texto: '' };
  if (respuesta.status !== 200) return { estado: 'error', texto: `No pudimos cargar ${tarjeta.titulo}. Intenta de nuevo.` };
  if (respuesta.total === 0) return { estado: 'vacio', texto: tarjeta.vacio };
  return { estado: 'resumen', texto: `${respuesta.total} ${tarjeta.unidad}` };
}
