<template>
  <div class="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-sm">
    <!-- Loading -->
    <div v-if="loading" class="flex items-center justify-center py-16 text-slate-400 gap-2 text-sm">
      <svg class="animate-spin h-5 w-5" fill="none" viewBox="0 0 24 24">
        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
      </svg>
      Memuat data elevasi…
    </div>

    <div v-else class="overflow-x-auto">
      <table class="min-w-full font-mono text-xs text-slate-700">
        <thead>
          <tr class="bg-[#1A1A2E] text-[10px] tracking-wider">
            <th class="px-3 py-2.5 text-right text-slate-400 font-medium">No</th>
            <th class="px-3 py-2.5 text-left text-slate-200 font-semibold">Titik</th>
            <th class="px-3 py-2.5 text-right text-slate-400 font-medium">Jarak (m)</th>
            <th class="px-3 py-2.5 text-right text-slate-400 font-medium">BA</th>
            <th class="px-3 py-2.5 text-right text-slate-400 font-medium">BT</th>
            <th class="px-3 py-2.5 text-right text-slate-400 font-medium">BB</th>
            <th class="px-3 py-2.5 text-center text-slate-300 font-semibold">Tipe</th>
            <th class="px-3 py-2.5 text-right text-slate-300 font-semibold">HI</th>
            <th class="px-3 py-2.5 text-right text-slate-300 font-semibold">ΔH</th>
            <th class="px-3 py-2.5 text-right text-slate-300 font-semibold">Elev. Mentah</th>
            <th class="px-3 py-2.5 text-right text-amber-400 font-semibold">Koreksi</th>
            <th class="px-3 py-2.5 text-right text-emerald-400 font-bold">Elev. Tetap</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
          <tr v-for="row in rows" :key="row.sequence_no"
            :class="[rowClass(row.reading_type), 'hover:brightness-[0.97] transition-all']">
            <td class="px-3 py-1.5 text-right text-slate-400">{{ row.sequence_no }}</td>
            <td class="px-3 py-1.5 font-semibold text-slate-800">{{ row.point_name }}</td>
            <td class="px-3 py-1.5 text-right tabular-nums text-slate-500">
              {{ row.distance_m != null ? fmt(row.distance_m, 3) : '—' }}
            </td>
            <td class="px-3 py-1.5 text-right tabular-nums">{{ fmt(row.ba) }}</td>
            <td class="px-3 py-1.5 text-right tabular-nums">{{ fmt(row.bt) }}</td>
            <td class="px-3 py-1.5 text-right tabular-nums">{{ fmt(row.bb) }}</td>
            <td class="px-3 py-1.5 text-center">
              <span :class="typeBadge(row.reading_type)" class="px-2 py-0.5 rounded text-[10px] font-bold uppercase">{{ row.reading_type }}</span>
            </td>
            <td class="px-3 py-1.5 text-right tabular-nums text-slate-500">{{ row.hi != null ? fmt(row.hi) : '—' }}</td>
            <td class="px-3 py-1.5 text-right tabular-nums" :class="row.delta_h != null && row.delta_h >= 0 ? 'text-emerald-600' : 'text-red-500'">
              {{ row.delta_h != null ? fmtSigned(row.delta_h) : '—' }}
            </td>
            <td class="px-3 py-1.5 text-right tabular-nums text-slate-400">{{ fmt(row.raw_elevation) }}</td>
            <td class="px-3 py-1.5 text-right tabular-nums text-xs" :class="row.correction !== 0 ? 'text-amber-600 font-semibold' : 'text-slate-300'">
              {{ row.correction !== 0 ? fmtSigned(row.correction, 6) : '0' }}
            </td>
            <td class="px-3 py-1.5 text-right tabular-nums font-bold text-slate-900">{{ fmt(row.adjusted_elevation) }}</td>
          </tr>
          <tr v-if="rows.length === 0">
            <td colspan="12" class="px-4 py-12 text-center text-slate-400">Belum ada data elevasi.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
defineProps({
  rows:    { type: Array,   required: true },
  loading: { type: Boolean, default: false },
})
function fmt(val, d = 4)       { return val == null ? '—' : Number(val).toFixed(d) }
function fmtSigned(val, d = 4) { if (val == null) return '—'; const n = Number(val); return (n >= 0 ? '+' : '') + n.toFixed(d) }
function rowClass(type) {
  return { BS: 'bg-blue-50/50', IS: 'bg-amber-50/50', FS: 'bg-rose-50/50' }[type] ?? ''
}
function typeBadge(type) {
  return { BS: 'bg-blue-100 text-blue-700', IS: 'bg-amber-100 text-amber-700', FS: 'bg-rose-100 text-rose-700' }[type] ?? 'bg-slate-100 text-slate-600'
}
</script>