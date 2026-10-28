<!-- DESPUÉS: recibe conteos enteros; comprueba RN-07 con enteros y solo formatea al mostrar. -->
<script setup>
import { computed } from 'vue'
import { porcentajeDeParte } from './formatearIndicador'

const props = defineProps({ desglose: Object, numerador: Number })
const emit = defineEmits(['abrirParte'])
const cuadra = computed(() => props.desglose.total === props.numerador)
</script>
<template>
  <table>
    <tr v-for="(conteo, parte) in desglose.partes" :key="parte">
      <td><button type="button" @click="emit('abrirParte', parte)">{{ parte }}</button></td>
      <td>{{ conteo }}</td>
      <td>{{ porcentajeDeParte(conteo, numerador) }}</td>
    </tr>
    <tr><th>Total</th><th>{{ desglose.total }}</th><th>{{ cuadra ? '100,0 %' : 'Revisar fuente' }}</th></tr>
  </table>
</template>
