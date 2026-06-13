<template>
  <div class="inline-flex items-center gap-3 rounded-xl border px-4 py-2.5" :class="containerClass">
    <!-- Icon -->
    <span class="text-xl">{{ icon }}</span>

    <!-- Status + numeric info -->
    <div>
      <p class="text-sm font-semibold leading-tight" :class="labelClass">
        {{ label }}
      </p>
      <p v-if="showNumbers" class="text-xs mt-0.5" :class="subClass">
        fh = <strong>{{ fmtM(closure_error) }}</strong>
        &nbsp;/&nbsp;
        toleransi = <strong>{{ fmtM(allowed_tolerance) }}</strong>
      </p>
      <p v-else-if="status === 'draft'" class="text-xs mt-0.5 text-gray-400">
        Belum dihitung
      </p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  status: {
    type: String,
    required: true,
    validator: (v) => ['draft', 'calculated', 'accepted', 'rejected'].includes(v),
  },
  closure_error: {
    type: Number,
    default: null,
  },
  allowed_tolerance: {
    type: Number,
    default: null,
  },
})

const showNumbers = computed(() =>
  props.closure_error != null && props.allowed_tolerance != null
)

const icon = computed(() => ({
  draft:      '📋',
  calculated: '🔢',
  accepted:   '✅',
  rejected:   '❌',
}[props.status] ?? '❓'))

const label = computed(() => ({
  draft:      'Draft',
  calculated: 'Sudah Dihitung',
  accepted:   'Diterima',
  rejected:   'Ditolak',
}[props.status] ?? props.status))

const containerClass = computed(() => ({
  draft:      'border-gray-200 bg-gray-50',
  calculated: 'border-blue-200 bg-blue-50',
  accepted:   'border-green-200 bg-green-50',
  rejected:   'border-red-200 bg-red-50',
}[props.status]))

const labelClass = computed(() => ({
  draft:      'text-gray-600',
  calculated: 'text-blue-700',
  accepted:   'text-green-700',
  rejected:   'text-red-700',
}[props.status]))

const subClass = computed(() => ({
  draft:      'text-gray-500',
  calculated: 'text-blue-600',
  accepted:   'text-green-600',
  rejected:   'text-red-600',
}[props.status]))

function fmtM(val) {
  if (val == null) return '—'
  return Number(val).toFixed(6) + ' m'
}
</script>