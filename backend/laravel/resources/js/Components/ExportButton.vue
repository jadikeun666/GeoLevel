<template>
  <button
    @click="trigger"
    :title="label"
    class="inline-flex items-center gap-1.5 border border-stone-200 text-stone-600 text-xs font-semibold px-3 py-1.5 rounded hover:border-stone-400 hover:text-stone-800 disabled:opacity-50 transition-colors"
  >
    <span>{{ icon }}</span>
    <span>{{ label }}</span>
  </button>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  type:      { type: String, required: true }, // 'pdf' | 'excel' | 'csv'
  projectId: { type: [Number, String], required: true },
})


const icon = computed(() => ({ pdf: '📄', excel: '📊', csv: '📋' }[props.type] ?? '⬇'))
const label = computed(() => ({ pdf: 'PDF', excel: 'Excel', csv: 'CSV' }[props.type] ?? props.type.toUpperCase()))

function trigger() {
  window.location.href = `/projects/${props.projectId}/export/${props.type}`
}
</script>