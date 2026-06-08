<template>
  <div class="overflow-x-auto rounded-lg border border-gray-200">
    <!-- Loading skeleton -->
    <div v-if="loading" class="flex items-center justify-center py-16 text-gray-400">
      <svg class="animate-spin h-6 w-6 mr-2" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
      </svg>
      Memuat data...
    </div>

    <table v-else class="min-w-full text-sm text-gray-700">
      <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider sticky top-0">
        <tr>
          <th class="px-3 py-2 text-right">No</th>
          <th class="px-3 py-2 text-left">Titik</th>
          <th class="px-3 py-2 text-right">Jarak (m)</th>
          <th class="px-3 py-2 text-right">BA</th>
          <th class="px-3 py-2 text-right">BT</th>
          <th class="px-3 py-2 text-right">BB</th>
          <th class="px-3 py-2 text-center">Tipe</th>
          <th class="px-3 py-2 text-right">HI</th>
          <th class="px-3 py-2 text-right">ΔH</th>
          <th class="px-3 py-2 text-right">Elev. Sementara</th>
          <th class="px-3 py-2 text-right">Koreksi</th>
          <th class="px-3 py-2 text-right font-semibold text-gray-800">Elev. Tetap</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-100">
        <tr
          v-for="row in rows"
          :key="row.sequence_no"
          :class="rowClass(row.reading_type)"
          class="hover:bg-blue-50 transition-colors"
        >
          <td class="px-3 py-1.5 text-right text-gray-400">{{ row.sequence_no }}</td>
          <td class="px-3 py-1.5 font-medium">{{ row.point_name }}</td>
          <td class="px-3 py-1.5 text-right tabular-nums">
            {{ row.distance_m != null ? fmt(row.distance_m, 3) : '—' }}
          </td>
          <td class="px-3 py-1.5 text-right tabular-nums">{{ fmt(row.ba) }}</td>
          <td class="px-3 py-1.5 text-right tabular-nums">{{ fmt(row.bt) }}</td>
          <td class="px-3 py-1.5 text-right tabular-nums">{{ fmt(row.bb) }}</td>
          <td class="px-3 py-1.5 text-center">
            <span :class="typeBadge(row.reading_type)" class="px-2 py-0.5 rounded text-xs font-semibold">
              {{ row.reading_type }}
            </span>
          </td>
          <td class="px-3 py-1.5 text-right tabular-nums">
            {{ row.hi != null ? fmt(row.hi) : '—' }}
          </td>
          <td class="px-3 py-1.5 text-right tabular-nums">
            {{ row.delta_h != null ? fmtSigned(row.delta_h) : '—' }}
          </td>
          <td class="px-3 py-1.5 text-right tabular-nums text-gray-500">{{ fmt(row.raw_elevation) }}</td>
          <td class="px-3 py-1.5 text-right tabular-nums text-xs"
              :class="row.correction !== 0 ? 'text-amber-600' : 'text-gray-400'">
            {{ row.correction !== 0 ? fmtSigned(row.correction, 6) : '0' }}
          </td>
          <td class="px-3 py-1.5 text-right tabular-nums font-semibold text-gray-900">
            {{ fmt(row.adjusted_elevation) }}
          </td>
        </tr>
        <tr v-if="rows.length === 0">
          <td colspan="12" class="px-4 py-10 text-center text-gray-400">
            Belum ada data bacaan.
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup>
const props = defineProps({
  rows: {
    type: Array,
    required: true,
    // Each row: { sequence_no, point_name, distance_m, ba, bt, bb,
    //             reading_type, hi, delta_h, raw_elevation, correction, adjusted_elevation }
  },
  loading: {
    type: Boolean,
    default: false,
  },
})

function fmt(val, decimals = 4) {
  if (val == null) return '—'
  return Number(val).toFixed(decimals)
}

function fmtSigned(val, decimals = 4) {
  if (val == null) return '—'
  const n = Number(val)
  return (n >= 0 ? '+' : '') + n.toFixed(decimals)
}

function rowClass(type) {
  return {
    'bg-blue-50/40':  type === 'BS',
    'bg-amber-50/40': type === 'IS',
    'bg-rose-50/40':  type === 'FS',
  }
}

function typeBadge(type) {
  return {
    BS: 'bg-blue-100 text-blue-700',
    IS: 'bg-amber-100 text-amber-700',
    FS: 'bg-rose-100 text-rose-700',
  }[type] ?? 'bg-gray-100 text-gray-600'
}
</script>