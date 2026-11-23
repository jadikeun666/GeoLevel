<template>
  <div class="space-y-3" data-testid="converter">
    <h2 class="text-sm font-bold text-slate-700">Konverter Koordinat</h2>

    <div class="flex rounded-md overflow-hidden border border-slate-200 text-xs font-medium w-fit">
      <button type="button" data-testid="conv-mode-dd" @click="mode = 'dd'"
              :class="mode === 'dd' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600'" class="px-2.5 py-1">DD</button>
      <button type="button" data-testid="conv-mode-utm" @click="mode = 'utm'"
              :class="mode === 'utm' ? 'bg-blue-600 text-white' : 'bg-white text-slate-600'" class="px-2.5 py-1 border-l border-slate-200">UTM</button>
    </div>

    <div v-if="mode === 'dd'" class="grid grid-cols-2 gap-2">
      <input v-model="lat" data-testid="conv-lat" inputmode="decimal" placeholder="Lintang (-6.2088)" class="rounded-md border border-slate-300 px-2 py-1.5 text-xs" />
      <input v-model="lng" data-testid="conv-lng" inputmode="decimal" placeholder="Bujur (106.8456)" class="rounded-md border border-slate-300 px-2 py-1.5 text-xs" />
    </div>
    <div v-else class="grid grid-cols-2 gap-2">
      <input v-model="zone" data-testid="conv-zone" inputmode="numeric" placeholder="Zona (46-54)" class="rounded-md border border-slate-300 px-2 py-1.5 text-xs" />
      <select v-model="hemisphere" class="rounded-md border border-slate-300 px-2 py-1.5 text-xs">
        <option value="N">Utara (N)</option>
        <option value="S">Selatan (S)</option>
      </select>
      <input v-model="easting" data-testid="conv-easting" inputmode="decimal" placeholder="Easting (m)" class="rounded-md border border-slate-300 px-2 py-1.5 text-xs" />
      <input v-model="northing" data-testid="conv-northing" inputmode="decimal" placeholder="Northing (m)" class="rounded-md border border-slate-300 px-2 py-1.5 text-xs" />
    </div>

    <p v-for="msg in errorList" :key="msg" class="text-[11px] text-red-600">{{ msg }}</p>

    <button type="button" data-testid="conv-submit" :disabled="busy" @click="convert"
            class="w-full rounded-md bg-slate-700 px-3 py-1.5 text-xs font-semibold text-white hover:bg-slate-600 disabled:opacity-40">
      {{ busy ? 'Mengonversi…' : 'Konversi' }}
    </button>

    <dl v-if="rows.length" class="space-y-1.5">
      <div v-for="row in rows" :key="row.key" class="flex items-center gap-2">
        <dt class="w-10 shrink-0 text-[11px] font-semibold text-slate-400">{{ row.label }}</dt>
        <dd class="min-w-0 flex-1 break-all font-mono text-[11px] text-slate-700" :data-testid="'conv-' + row.key">{{ row.value }}</dd>
        <button type="button" :disabled="!row.copy" class="shrink-0 text-[11px] text-blue-600 hover:underline disabled:opacity-30"
                @click="copy(row)">{{ copied === row.key ? 'Tersalin' : 'Salin' }}</button>
      </div>
    </dl>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'

// Contract: docs/outdoor.md, 19.1. defaultFormat only picks the initial input mode.
const props = defineProps({ defaultFormat: { type: String, default: 'dd' } })

const mode       = ref(props.defaultFormat === 'utm' ? 'utm' : 'dd')
const lat        = ref('')
const lng        = ref('')
const zone       = ref('')
const hemisphere = ref('S')
const easting    = ref('')
const northing   = ref('')
const busy       = ref(false)
const errors     = ref({})
const result     = ref(null)
const copied     = ref('')

const errorList = computed(() => Object.values(errors.value).flat())

const rows = computed(() => {
  const r = result.value
  if (!r) return []
  return [
    { key: 'dd',   label: 'DD',   value: `${r.lat}, ${r.lng}` },
    { key: 'dms',  label: 'DMS',  value: `${r.dms.lat} ${r.dms.lng}` },
    { key: 'utm',  label: 'UTM',  value: `${r.utm.zone}${r.utm.hemisphere} ${r.utm.easting} ${r.utm.northing}` },
    { key: 'mgrs', label: 'MGRS', value: r.mgrs ?? '— (di luar -80..84)', copy: r.mgrs !== null },
  ].map(row => ({ copy: true, ...row }))
})

async function convert() {
  busy.value = true
  errors.value = {}
  result.value = null
  const body = mode.value === 'dd'
    ? { lat: lat.value, lng: lng.value }
    : { zone: zone.value, hemisphere: hemisphere.value, easting: easting.value, northing: northing.value }
  try {
    const { data } = await axios.post(route('outdoor.convert'), body)
    result.value = data.data
  } catch (e) {
    errors.value = e.response?.data?.errors ?? { general: [e.response?.data?.message ?? 'Gagal mengonversi.'] }
  } finally {
    busy.value = false
  }
}

async function copy(row) {
  try {
    await navigator.clipboard.writeText(row.value)
    copied.value = row.key
    setTimeout(() => { if (copied.value === row.key) copied.value = '' }, 1500)
  } catch (e) { /* clipboard unavailable: ignore */ }
}
</script>
