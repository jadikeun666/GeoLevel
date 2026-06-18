<template>
  <div class="inline-flex items-center gap-2.5 rounded-lg border px-3 py-2 font-mono text-xs" :class="containerClass">
    <div class="w-2 h-2 rounded-full shrink-0" :class="dotClass" />
    <div>
      <p class="font-bold leading-tight" :class="labelClass">{{ label }}</p>
      <p v-if="showNumbers" class="mt-0.5 text-[10px] opacity-75 tabular-nums">
        fh {{ fmtM(closure_error) }} / tol {{ fmtM(allowed_tolerance) }}
      </p>
      <p v-else-if="status === 'draft'" class="mt-0.5 text-[10px] opacity-60">Belum dihitung</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
const props = defineProps({
  status:            { type: String, required: true },
  closure_error:     { type: Number, default: null },
  allowed_tolerance: { type: Number, default: null },
})
const showNumbers = computed(() => props.closure_error != null && props.allowed_tolerance != null)
const label = computed(() => ({
  draft: 'Draft', calculated: 'Sudah Dihitung', accepted: 'Diterima', rejected: 'Ditolak',
}[props.status] ?? props.status))
const containerClass = computed(() => ({
  draft:      'border-amber-200 bg-amber-50 text-amber-700',
  calculated: 'border-blue-200 bg-blue-50 text-blue-700',
  accepted:   'border-emerald-200 bg-emerald-50 text-emerald-700',
  rejected:   'border-red-200 bg-red-50 text-red-700',
}[props.status]))
const labelClass = computed(() => ({
  draft: 'text-amber-700', calculated: 'text-blue-700', accepted: 'text-emerald-700', rejected: 'text-red-700',
}[props.status]))
const dotClass = computed(() => ({
  draft: 'bg-amber-400', calculated: 'bg-blue-500 animate-pulse', accepted: 'bg-emerald-500', rejected: 'bg-red-500',
}[props.status]))
function fmtM(val) { return val != null ? Number(val).toFixed(6) + ' m' : '—' }
</script>