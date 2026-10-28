<!-- ANTES (acoplado): vuelve a calcular con valores ya redondeados y decide su propio formato. -->
<script setup>
const props = defineProps({ datos: Object })
const total = Object.values(props.datos.desglose).reduce((suma, texto) => suma + parseInt(texto), 0)
</script>
<template>
  <p class="valor">{{ datos.valor }}</p>
  <p v-if="total !== 100" class="alerta">El desglose no cuadra ({{ total }} %)</p>
</template>
