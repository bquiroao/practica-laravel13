// DESPUÉS: único lugar del frontend donde un valor se convierte en texto.
export function formatearIndicador(valor, { decimales, unidad }) {
  const texto = new Intl.NumberFormat('es-GT', { minimumFractionDigits: decimales, maximumFractionDigits: decimales }).format(valor)
  return unidad === '%' ? `${texto} %` : `${texto} ${unidad}`
}

// Porcentaje de una parte, calculado desde conteos enteros y solo para mostrarlo.
export function porcentajeDeParte(parte, numerador, decimales = 1) {
  return formatearIndicador((parte / numerador) * 100, { decimales, unidad: '%' })
}
