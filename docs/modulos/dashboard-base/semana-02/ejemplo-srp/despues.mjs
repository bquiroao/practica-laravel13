// DESPUÉS (SRP): una pieza por razón de cambio.

/** Contrato HTTP con límite de tiempo (R-04). Cambia solo si cambia la API. */
export function crearClienteTarjetas(obtener, limiteMs = 10000) {
  return async (id) => {
    try {
      return await Promise.race([
        obtener(`/dashboard/cards/${id}`),
        new Promise((_, rechazar) => setTimeout(() => rechazar(new Error('tiempo')), limiteMs)),
      ]);
    } catch {
      return { status: 'sin_respuesta' };
    }
  };
}

/** Reglas de estados (R-04, R-05, R-07). Cambia solo si cambia la experiencia acordada. Pura. */
export function estadoDeRespuesta(respuesta) {
  if (respuesta.status === 401) return 'sesion_expirada';
  if (respuesta.status === 403) return 'oculta';
  if (respuesta.status !== 200) return 'error';
  return respuesta.total === 0 ? 'vacio' : 'resumen';
}

/** Textos sin detalles técnicos (R-09). Cambia solo si cambia la redacción. Pura. */
export function textoDeEstado(estado, tarjeta, respuesta) {
  const textos = {
    resumen: () => `${respuesta.total} ${tarjeta.unidad}`,
    vacio: () => tarjeta.vacio,
    error: () => `No pudimos cargar ${tarjeta.titulo}. Intenta de nuevo.`,
    sesion_expirada: () => 'Tu sesión terminó. Vuelve a iniciar sesión.',
    oculta: () => '',
  };
  return textos[estado]();
}

/** Orquesta una tarjeta (CU-02). Cambia solo si cambia el flujo. La sesión se recibe ya hecha. */
export async function cargarTarjeta(tarjeta, { cliente, sesion }) {
  const respuesta = await cliente(tarjeta.id);
  const estado = estadoDeRespuesta(respuesta);
  if (estado === 'sesion_expirada') sesion.limpiar();
  return { estado, texto: textoDeEstado(estado, tarjeta, respuesta) };
}
