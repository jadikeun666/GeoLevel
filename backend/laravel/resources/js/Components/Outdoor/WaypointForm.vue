<template>
  <form class="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-2" @submit.prevent="submit">
    <p class="text-xs font-bold text-slate-700">{{ waypoint ? 'Ubah Waypoint' : 'Waypoint Baru' }}</p>

    <div>
      <label for="wpf-name" class="block text-[11px] text-slate-500 mb-0.5">Nama</label>
      <input id="wpf-name" v-model="form.name" type="text" maxlength="100" :class="inputClass" />
      <p v-if="errors.name" class="text-[11px] text-red-600 mt-0.5">{{ errors.name }}</p>
    </div>

    <div class="grid grid-cols-2 gap-2">
      <div>
        <label for="wpf-lat" class="block text-[11px] text-slate-500 mb-0.5">Latitude</label>
        <input id="wpf-lat" v-model="form.lat" type="text" inputmode="decimal" placeholder="-6.20000000" :class="inputClass" />
        <p v-if="errors.lat" class="text-[11px] text-red-600 mt-0.5">{{ errors.lat }}</p>
      </div>
      <div>
        <label for="wpf-lng" class="block text-[11px] text-slate-500 mb-0.5">Longitude</label>
        <input id="wpf-lng" v-model="form.lng" type="text" inputmode="decimal" placeholder="106.80000000" :class="inputClass" />
        <p v-if="errors.lng" class="text-[11px] text-red-600 mt-0.5">{{ errors.lng }}</p>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-2">
      <div>
        <label for="wpf-ele" class="block text-[11px] text-slate-500 mb-0.5">Elevasi GPS (m)</label>
        <input id="wpf-ele" v-model="form.elevation_ref" type="text" inputmode="decimal" :class="inputClass" />
        <p v-if="errors.elevation_ref" class="text-[11px] text-red-600 mt-0.5">{{ errors.elevation_ref }}</p>
      </div>
      <div>
        <label for="wpf-category" class="block text-[11px] text-slate-500 mb-0.5">Kategori</label>
        <select id="wpf-category" v-model="form.category" :class="inputClass">
          <option v-for="c in CATEGORY_OPTIONS" :key="c.value" :value="c.value">{{ c.label }}</option>
        </select>
        <p v-if="errors.category" class="text-[11px] text-red-600 mt-0.5">{{ errors.category }}</p>
      </div>
    </div>

    <div>
      <label for="wpf-project" class="block text-[11px] text-slate-500 mb-0.5">Proyek (opsional)</label>
      <select id="wpf-project" v-model="form.project_id" :class="inputClass">
        <option :value="null">Tanpa proyek</option>
        <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
      </select>
      <p v-if="errors.project_id" class="text-[11px] text-red-600 mt-0.5">{{ errors.project_id }}</p>
    </div>

    <div>
      <label for="wpf-notes" class="block text-[11px] text-slate-500 mb-0.5">Catatan</label>
      <textarea id="wpf-notes" v-model="form.notes" rows="2" maxlength="500" :class="inputClass"></textarea>
      <p v-if="errors.notes" class="text-[11px] text-red-600 mt-0.5">{{ errors.notes }}</p>
    </div>

    <p v-if="generalError" class="text-[11px] text-red-600">{{ generalError }}</p>

    <div class="flex gap-2 pt-1">
      <button type="submit" :disabled="saving"
              class="flex-1 rounded-md bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-500 disabled:opacity-40">
        {{ saving ? 'Menyimpan…' : 'Simpan' }}
      </button>
      <button type="button" :disabled="saving" @click="emit('cancel')"
              class="rounded-md border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-white disabled:opacity-40">
        Batal
      </button>
    </div>
  </form>
</template>

<script setup>
import { reactive, ref } from 'vue'
import axios from 'axios'
import { CATEGORY_OPTIONS } from './outdoorCategories'

// Contract (docs/outdoor.md, bagian 7):
//   props : waypoint Object|null (null = create), projects Array<{ id, name }>
//   emits : saved (waypoint), cancel
const props = defineProps({
  waypoint: { type: Object, default: null },
  projects: { type: Array,  default: () => [] },
})

const emit = defineEmits(['saved', 'cancel'])

const inputClass = 'w-full rounded-md border border-slate-300 bg-white px-2 py-1.5 text-xs text-slate-700 focus:border-blue-500 focus:outline-none'

const form = reactive({
  name:          props.waypoint?.name ?? '',
  lat:           props.waypoint?.lat ?? '',
  lng:           props.waypoint?.lng ?? '',
  elevation_ref: props.waypoint?.elevation_ref ?? '',
  category:      props.waypoint?.category ?? 'generic',
  notes:         props.waypoint?.notes ?? '',
  project_id:    props.waypoint?.project_id ?? null,
})

const errors       = reactive({})
const generalError = ref('')
const saving       = ref(false)

function emptyToNull(value) {
  return value === '' ? null : value
}

// Coordinates/elevation stay strings (NUMERIC precision); the server validates them.
function buildPayload() {
  return {
    name:          form.name.trim(),
    lat:           String(form.lat).trim(),
    lng:           String(form.lng).trim(),
    elevation_ref: emptyToNull(String(form.elevation_ref).trim()),
    category:      form.category,
    notes:         emptyToNull(form.notes.trim()),
    project_id:    form.project_id,
  }
}

async function submit() {
  if (saving.value) return
  saving.value = true
  generalError.value = ''
  Object.keys(errors).forEach(key => delete errors[key])

  try {
    const body = buildPayload()
    const { data } = props.waypoint
      ? await axios.put(route('outdoor.waypoints.update', props.waypoint.id), body)
      : await axios.post(route('outdoor.waypoints.store'), body)
    emit('saved', data.data)
  } catch (e) {
    const serverErrors = e.response?.data?.errors
    if (serverErrors) {
      Object.entries(serverErrors).forEach(([field, messages]) => { errors[field] = messages[0] })
    } else {
      generalError.value = e.response?.data?.message ?? 'Gagal menyimpan waypoint.'
    }
  } finally {
    saving.value = false
  }
}
</script>
