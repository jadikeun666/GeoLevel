<template>
  <button
    @click="trigger"
    :disabled="loading"
    :title="label"
    class="inline-flex items-center gap-1.5 border border-stone-200 text-stone-600 text-xs font-semibold px-3 py-1.5 rounded hover:border-stone-400 hover:text-stone-800 disabled:opacity-50 transition-colors"
  >
    <span>{{ icon }}</span>
    <span>{{ loading ? 'Mengirim…' : label }}</span>
  </button>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'

const props = defineProps({
  type:      { type: String, required: true }, // 'pdf' | 'excel' | 'csv'
  projectId: { type: [Number, String], required: true },
})

const loading = ref(false)

const icon = computed(() => ({ pdf: '📄', excel: '📊', csv: '📋' }[props.type] ?? '⬇'))
const label = computed(() => ({ pdf: 'PDF', excel: 'Excel', csv: 'CSV' }[props.type] ?? props.type.toUpperCase()))

async function trigger() {
  loading.value = true
  try {
    const { data } = await axios.get(`/projects/${props.projectId}/export/${props.type}`)
    if (data.success) {
      alert(data.message)
    } else {
      alert('Gagal: ' + data.message)
    }
  } catch (e) {
    alert('Terjadi kesalahan saat meminta export.')
  } finally {
    loading.value = false
  }
}
</script>