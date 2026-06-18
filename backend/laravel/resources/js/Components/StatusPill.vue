<!-- ===== StatusPill.vue ===== -->
<template>
  <span class="inline-block px-2 py-0.5 rounded-md text-[10px] font-bold uppercase tracking-wider shrink-0 font-mono" :class="cls">
    {{ label }}
  </span>
</template>

<script setup>
import { computed } from 'vue'
const props = defineProps({ status: { type: String, required: true } })
const map = {
  draft:      { label: 'Draft',    cls: 'bg-amber-100 text-amber-700 ring-1 ring-amber-200'   },
  calculated: { label: 'Dihitung', cls: 'bg-blue-100 text-blue-700 ring-1 ring-blue-200'      },
  accepted:   { label: 'Diterima', cls: 'bg-emerald-100 text-emerald-700 ring-1 ring-emerald-200' },
  rejected:   { label: 'Ditolak',  cls: 'bg-red-100 text-red-700 ring-1 ring-red-200'         },
}
const label = computed(() => map[props.status]?.label ?? props.status)
const cls   = computed(() => map[props.status]?.cls   ?? 'bg-slate-100 text-slate-500')
</script>