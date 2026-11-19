<template>
  <div class="space-y-3" data-testid="route-planner">
    <div class="flex items-center justify-between">
      <h2 class="text-sm font-bold text-slate-700">Rute ({{ routes.length }})</h2>
      <button v-if="!drawing" type="button" data-testid="route-draw" class="text-[11px] font-semibold text-blue-600 hover:underline"
              @click="startDrawing">+ Rancang Rute</button>
    </div>

    <!-- Editor -->
    <div v-if="drawing" class="rounded-md border border-orange-200 bg-orange-50 p-3 space-y-2">
      <p class="text-[11px] text-orange-800">
        {{ editingId ? 'Mengubah rute.' : 'Klik peta untuk menambah titik.' }}
        <span data-testid="route-vertex-count" class="font-mono">{{ vertices.length }}</span> titik,
        perkiraan <span class="font-mono">{{ formatDistance(estimateM) }}</span>.
      </p>
      <p v-if="vertices.length >= maxVertices" class="text-[11px] text-red-600">Batas jumlah titik rute tercapai.</p>

      <input v-model="name" type="text" maxlength="150" placeholder="Nama rute" data-testid="route-name"
             class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs text-slate-700" />
      <p v-if="errors.name" class="text-[11px] text-red-600">{{ errors.name }}</p>
      <textarea v-model="notes" maxlength="500" rows="2" placeholder="Catatan (opsional)"
                class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs text-slate-700"></textarea>
      <select v-model="projectId" class="w-full rounded-md border border-slate-300 px-2 py-1.5 text-xs text-slate-700">
        <option :value="null">Tanpa proyek</option>
        <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <p v-if="errors.general" class="text-[11px] text-red-600">{{ errors.general }}</p>

      <div class="flex flex-wrap gap-2">
        <button type="button" data-testid="route-undo" :disabled="vertices.length === 0" @click="undo"
                class="rounded-md bg-slate-200 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-300 disabled:opacity-40">Urungkan</button>
        <button type="button" data-testid="route-clear" :disabled="vertices.length === 0" @click="clearVertices"
                class="rounded-md bg-slate-200 px-2.5 py-1 text-xs font-medium text-slate-700 hover:bg-slate-300 disabled:opacity-40">Kosongkan</button>
        <button type="button" data-testid="route-save" :disabled="!canSave" @click="save"
                class="rounded-md bg-orange-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-orange-500 disabled:opacity-40">
          {{ saving ? 'Menyimpan…' : 'Simpan' }}
        </button>
        <button type="button" data-testid="route-cancel" @click="cancel"
                class="rounded-md bg-white border border-slate-300 px-2.5 py-1 text-xs font-medium text-slate-600 hover:bg-slate-50">Batal</button>
      </div>
    </div>

    <p v-if="message" class="text-xs" :class="message.type === 'error' ? 'text-red-600' : 'text-emerald-700'"
       data-testid="route-message">{{ message.text }}</p>

    <!-- Daftar -->
    <p v-if="routes.length === 0" class="text-xs text-slate-400">Belum ada rute.</p>
    <ul class="divide-y divide-slate-100 max-h-72 overflow-auto">
      <li v-for="r in routes" :key="r.id" class="py-2 flex items-center gap-2" data-testid="route-item">
        <input type="checkbox" :checked="visible.includes(r.id)" @change="toggleVisible(r.id)" />
        <div class="min-w-0 flex-1">
          <span class="block truncate text-xs font-medium text-slate-700">{{ r.name }}</span>
          <span class="font-mono text-[11px] text-slate-400">{{ formatDistance(Number(r.distance_m)) }}</span>
        </div>
        <button type="button" class="text-[11px] text-blue-600 hover:underline shrink-0" @click="edit(r)">Ubah</button>
        <button type="button" data-testid="route-delete" class="text-[11px] text-red-500 hover:underline shrink-0"
                @click="remove(r)">Hapus</button>
      </li>
    </ul>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import axios from 'axios'
import { haversineM } from './recorderFilter'

// Contract: docs/outdoor.md, 17.9. Server geometry is [lat, lng]; the draft (vertices) is [lng, lat].
const props = defineProps({
  routes:      { type: Array,   default: () => [] },
  projects:    { type: Array,   default: () => [] },
  maxVertices: { type: Number,  required: true },
  vertices:    { type: Array,   default: () => [] },
  drawing:     { type: Boolean, default: false },
})
const emit = defineEmits(['update:vertices', 'update:drawing', 'visible-change', 'saved'])

const name      = ref('')
const notes     = ref('')
const projectId = ref(null)
const editingId = ref(null)
const saving    = ref(false)
const errors    = ref({})
const message   = ref(null)
const visible   = ref([])

const canSave = computed(() => name.value.trim() !== '' && props.vertices.length >= 2 && !saving.value)

const estimateM = computed(() => {
  let m = 0
  for (let i = 1; i < props.vertices.length; i++) {
    const [lng1, lat1] = props.vertices[i - 1]
    const [lng2, lat2] = props.vertices[i]
    m += haversineM({ lat: lat1, lng: lng1 }, { lat: lat2, lng: lng2 })
  }
  return m
})

function resetForm() {
  name.value = ''
  notes.value = ''
  projectId.value = null
  editingId.value = null
  errors.value = {}
}

function startDrawing() {
  message.value = null
  emit('update:drawing', true)
}

function undo() { emit('update:vertices', props.vertices.slice(0, -1)) }
function clearVertices() { emit('update:vertices', []) }

function cancel() {
  resetForm()
  emit('update:vertices', [])
  emit('update:drawing', false)
}

function toggleVisible(id) {
  visible.value = visible.value.includes(id) ? visible.value.filter(v => v !== id) : [...visible.value, id]
  emit('visible-change', visible.value)
}

function edit(route) {
  message.value = null
  resetForm()
  editingId.value = route.id
  name.value = route.name
  notes.value = route.notes ?? ''
  projectId.value = route.project_id ?? null
  emit('update:vertices', route.geometry.map(([lat, lng]) => [lng, lat]))
  emit('update:drawing', true)
}

async function save() {
  if (!canSave.value) return
  saving.value = true
  errors.value = {}
  message.value = null

  const body = {
    name: name.value.trim(),
    geometry: props.vertices.map(([lng, lat]) => [lat, lng]),
    notes: notes.value.trim() || null,
    project_id: projectId.value || null,
  }

  try {
    const { data } = editingId.value
      ? await axios.put(route('outdoor.routes.update', editingId.value), body)
      : await axios.post(route('outdoor.routes.store'), body)
    resetForm()
    emit('update:vertices', [])
    emit('update:drawing', false)
    message.value = { type: 'success', text: 'Rute tersimpan.' }
    emit('saved', data.data)
  } catch (e) {
    const server = e.response?.data?.errors
    if (server) {
      const { name: nameErr, ...rest } = server
      errors.value = {
        name: nameErr?.[0] ?? '',
        general: Object.values(rest).flat().join(' '),
      }
    } else {
      errors.value = { general: e.response?.data?.message ?? 'Gagal menyimpan rute.' }
    }
  } finally {
    saving.value = false
  }
}

async function remove(r) {
  if (!confirm(`Hapus rute "${r.name}"?`)) return
  try {
    await axios.delete(route('outdoor.routes.destroy', r.id))
    if (visible.value.includes(r.id)) {
      visible.value = visible.value.filter(v => v !== r.id)
      emit('visible-change', visible.value)
    }
    if (editingId.value === r.id) cancel()
    message.value = { type: 'success', text: 'Rute dihapus.' }
    emit('saved', null)
  } catch (e) {
    message.value = { type: 'error', text: 'Gagal menghapus rute.' }
  }
}

function formatDistance(m) {
  return m >= 1000 ? `${(m / 1000).toFixed(2)} km` : `${m.toFixed(0)} m`
}
</script>
