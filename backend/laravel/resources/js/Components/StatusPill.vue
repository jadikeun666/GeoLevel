<template>
  <span
    class="inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider shrink-0"
    :class="cls"
  >{{ label }}</span>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: { type: String, required: true },
})

const map = {
  draft:      { label: 'Draft',      cls: 'bg-stone-100 text-stone-500' },
  calculated: { label: 'Dihitung',   cls: 'bg-blue-100 text-blue-700'  },
  accepted:   { label: 'Diterima',   cls: 'bg-green-100 text-green-700' },
  rejected:   { label: 'Ditolak',    cls: 'bg-red-100 text-red-700'    },
}

const label = computed(() => map[props.status]?.label ?? props.status)
const cls   = computed(() => map[props.status]?.cls   ?? 'bg-stone-100 text-stone-500')
</script>