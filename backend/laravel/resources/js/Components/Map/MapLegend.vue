<template>
  <div class="bg-white rounded-lg border border-slate-200 shadow-sm px-3 py-2.5 text-[11px]">
    <h4 class="font-bold text-slate-600 mb-1.5 uppercase tracking-wide text-[10px]">Legenda</h4>

    <div class="space-y-1">
      <div class="flex items-center gap-2">
        <span class="w-2.5 h-2.5 rounded-full inline-block shrink-0" style="background:#F59E0B"></span>
        <span class="text-slate-600">BM — Benchmark</span>
      </div>
      <div class="flex items-center gap-2">
        <span class="w-2.5 h-2.5 rounded-full inline-block shrink-0" style="background:#3B82F6"></span>
        <span class="text-slate-600">TP — Titik Ukur</span>
      </div>
      <div class="flex items-center gap-2">
        <span class="w-2.5 h-2.5 rounded-full inline-block shrink-0" style="background:#10B981"></span>
        <span class="text-slate-600">IS — Titik Antara</span>
      </div>
      <div class="flex items-center gap-2">
        <span class="w-2.5 h-2.5 rounded-full inline-block shrink-0" style="background:#8B5CF6"></span>
        <span class="text-slate-600">CP — Titik Kontrol</span>
      </div>
    </div>

    <div class="border-t border-slate-100 mt-2 pt-2 space-y-1">
      <div class="flex items-center gap-2">
        <span class="w-4 h-0 border-t-2 border-dashed inline-block shrink-0" style="border-color:#DC2626"></span>
        <span class="text-slate-600">Jalur pengukuran</span>
      </div>
      <div v-if="showNetworkLegend" class="flex items-center gap-2">
        <span class="w-4 h-0.5 inline-block shrink-0" :style="{ background: networkLegColor }"></span>
        <span class="text-slate-600">Jalur jaring ({{ networkLegLabel }})</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  projectStatus: { type: String, default: 'draft' },
  showNetworkLegend: { type: Boolean, default: false },
})

const NETWORK_LEG_COLOR = {
  draft: '#9CA3AF',
  calculated: '#3B82F6',
  accepted: '#10B981',
  rejected: '#EF4444',
}

const STATUS_LABEL = {
  draft: 'draft',
  calculated: 'terhitung',
  accepted: 'diterima',
  rejected: 'ditolak',
}

const networkLegColor = computed(() => NETWORK_LEG_COLOR[props.projectStatus] || NETWORK_LEG_COLOR.draft)
const networkLegLabel = computed(() => STATUS_LABEL[props.projectStatus] || props.projectStatus)
</script>
