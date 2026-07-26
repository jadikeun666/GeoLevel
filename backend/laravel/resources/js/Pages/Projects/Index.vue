<template>
  <AppLayout title="Proyek Survei">

    <!-- ── Hero header with grid texture ──────────────────────── -->
    <header class="relative overflow-hidden bg-[#1A1A2E] px-6 py-8">
      <!-- Blueprint grid overlay -->
      <div class="pointer-events-none absolute inset-0 opacity-[0.06]"
           style="background-image: linear-gradient(#60A5FA 1px, transparent 1px), linear-gradient(90deg, #60A5FA 1px, transparent 1px); background-size: 32px 32px;" />

      <div class="relative flex items-end justify-between gap-4 flex-wrap">
        <div>
          <p class="text-[10px] font-mono tracking-[0.25em] text-blue-400 uppercase mb-1">GeoLevel · Sistem Survei Waterpass</p>
          <h1 class="text-2xl font-bold text-white tracking-tight">Proyek Survei</h1>
          <p class="mt-1 text-sm text-slate-400">Manajemen jalur sipat datar dan perhitungan elevasi</p>
        </div>
        <button
          @click="openCreate"
          class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-500 active:bg-blue-700 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors shadow-lg shadow-blue-900/30 shrink-0"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
          Proyek Baru
        </button>
      </div>
    </header>

    <!-- ── Stats strip ─────────────────────────────────────────── -->
    <div class="bg-white border-b border-slate-200 px-6 py-0">
      <div class="flex items-center gap-0 divide-x divide-slate-100 -mx-6">
        <StatTile label="Total" :value="projects.length" color="text-slate-700" />
        <StatTile label="Diterima" :value="countByStatus('accepted')" color="text-emerald-600" />
        <StatTile label="Ditolak" :value="countByStatus('rejected')" color="text-red-500" />
        <StatTile label="Dihitung" :value="countByStatus('calculated')" color="text-blue-600" />
        <StatTile label="Draft" :value="countByStatus('draft')" color="text-amber-500" />
      </div>
    </div>

    <!-- ── Mini-map overview ───────────────────────────────────── -->
    <div v-if="mapPoints.length" class="bg-white border-b border-slate-100 px-6 py-4">
      <ProjectsOverviewMap :map-points="mapPoints" />
    </div>

    <!-- ── Filter / search bar ─────────────────────────────────── -->
    <div class="bg-white border-b border-slate-100 px-6 py-3 flex items-center gap-3 flex-wrap">
      <div class="flex gap-1.5">
        <button
          v-for="opt in statusOpts"
          :key="opt.value"
          @click="filterStatus = opt.value"
          :class="[
            'px-3 py-1 rounded-full text-[11px] font-semibold transition-colors',
            filterStatus === opt.value
              ? 'bg-[#1A1A2E] text-white'
              : 'bg-slate-100 text-slate-500 hover:bg-slate-200'
          ]"
        >{{ opt.label }}</button>
      </div>
      <div class="ml-auto relative">
        <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <input
          v-model="search"
          type="search"
          placeholder="Cari nama / lokasi…"
          class="pl-8 pr-3 py-1.5 text-xs border border-slate-200 rounded-lg bg-slate-50 text-slate-700 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-400 w-52"
        />
      </div>
    </div>

    <!-- ── Content ─────────────────────────────────────────────── -->
    <div class="px-6 py-6 min-h-[60vh] bg-[#F7F6F3]">

      <!-- Empty state -->
      <div
        v-if="filtered.length === 0"
        class="flex flex-col items-center justify-center py-24 text-center"
      >
        <div class="w-16 h-16 rounded-2xl bg-white border border-slate-200 flex items-center justify-center mb-4 shadow-sm">
          <svg class="w-7 h-7 text-slate-300" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 20.25H5.25A2.25 2.25 0 013 18V5.25A2.25 2.25 0 015.25 3H15l5.25 5.25V18a2.25 2.25 0 01-2.25 2.25H15M9 15l3 3 6-6"/>
          </svg>
        </div>
        <p class="text-sm font-semibold text-slate-600">
          {{ projects.length === 0 ? 'Belum ada proyek survei' : 'Tidak ada proyek yang cocok' }}
        </p>
        <p class="text-xs text-slate-400 mt-1">
          {{ projects.length === 0 ? 'Klik Proyek Baru untuk memulai jalur sipat datar pertama.' : 'Coba ubah filter atau kata kunci pencarian.' }}
        </p>
        <button v-if="projects.length === 0" @click="openCreate"
          class="mt-5 text-xs bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-500 transition-colors">
          + Proyek Baru
        </button>
      </div>

      <!-- Project grid -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
        <div
          v-for="p in filtered"
          :key="p.id"
          class="group relative bg-white rounded-xl shadow-sm hover:shadow-md transition-all border border-slate-200 hover:border-slate-300 overflow-hidden"
        >
          <!-- Status left-border accent -->
          <div class="absolute inset-y-0 left-0 w-1 rounded-l-xl" :class="statusAccent(p.status)" />

          <!-- Hover actions -->
          <div class="absolute top-3 right-3 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity z-10">
            <button @click.prevent="openEdit(p)" title="Edit"
              class="w-7 h-7 flex items-center justify-center rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-500 text-xs transition-colors">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
            </button>
            <button @click.prevent="confirmDelete(p)" title="Hapus"
              class="w-7 h-7 flex items-center justify-center rounded-lg bg-red-50 hover:bg-red-100 text-red-400 text-xs transition-colors">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
            </button>
          </div>

          <!-- Card content -->
          <Link :href="route('projects.show', p.id)" class="block p-5 pl-6">
            <!-- Name + status -->
            <div class="flex items-start justify-between gap-2 mb-3 pr-14">
              <h2 class="text-sm font-bold text-slate-800 leading-snug">{{ p.name }}</h2>
              <StatusPill :status="p.status" />
            </div>

            <!-- Meta rows -->
            <dl class="space-y-1.5 font-mono text-[11px]">
              <div class="flex gap-2">
                <dt class="text-slate-400 w-20 shrink-0">Lokasi</dt>
                <dd class="text-slate-600 truncate">{{ p.location }}</dd>
              </div>
              <div class="flex gap-2">
                <dt class="text-slate-400 w-20 shrink-0">Tanggal</dt>
                <dd class="text-slate-600">{{ formatDate(p.survey_date) }}</dd>
              </div>
              <div class="flex gap-2">
                <dt class="text-slate-400 w-20 shrink-0">Benchmark</dt>
                <dd class="text-slate-600">{{ p.benchmark_name }} <span class="text-slate-400">({{ p.benchmark_elevation }} m)</span></dd>
              </div>
              <div v-if="p.closure_error != null" class="flex gap-2">
                <dt class="text-slate-400 w-20 shrink-0">fh</dt>
                <dd class="font-semibold tabular-nums" :class="p.status === 'accepted' ? 'text-emerald-600' : 'text-red-500'">
                  {{ Number(p.closure_error).toFixed(6) }} m
                </dd>
              </div>
            </dl>

            <!-- Footer -->
            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
              <span class="font-mono text-[10px] text-slate-400 uppercase tracking-wider">
                {{ p.tolerance_class }} · {{ p.adjustment_method }}
              </span>
              <span class="text-[11px] text-slate-400 group-hover:text-blue-600 transition-colors flex items-center gap-1">
                Buka
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
              </span>
            </div>
          </Link>
        </div>
      </div>
    </div>

    <!-- ── Create / Edit Modal ──────────────────────────────────── -->
    <Teleport to="body">
      <Transition enter-active-class="transition duration-150 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100"
                  leave-active-class="transition duration-100 ease-in"  leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg overflow-hidden">

            <!-- Modal header -->
            <div class="bg-[#1A1A2E] px-6 py-4 flex items-center justify-between">
              <div>
                <p class="font-mono text-[10px] text-blue-400 tracking-widest uppercase">{{ editTarget ? 'Edit' : 'Baru' }}</p>
                <h2 class="font-bold text-white text-base">{{ editTarget ? 'Edit Proyek' : 'Proyek Survei Baru' }}</h2>
              </div>
              <button @click="closeForm" class="w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors text-xl leading-none">×</button>
            </div>

            <form @submit.prevent="submitForm" class="px-6 py-5 space-y-4">
              <Field label="Nama Proyek" required>
                <input v-model="form.name" type="text" v-bind="fieldAttrs" placeholder="Jalur Sipat Datar STA 0+000 s/d 1+000" />
              </Field>
              <div class="grid grid-cols-2 gap-4">
                <Field label="Lokasi" required>
                  <input v-model="form.location" type="text" v-bind="fieldAttrs" placeholder="Bandar Lampung" />
                </Field>
                <Field label="Tanggal Survei" required>
                  <input v-model="form.survey_date" type="date" v-bind="fieldAttrs" />
                </Field>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <Field label="Nama Benchmark" required>
                  <input v-model="form.benchmark_name" type="text" v-bind="fieldAttrs" placeholder="BM-A" />
                </Field>
                <Field label="Elevasi Benchmark (m)" required>
                  <input v-model="form.benchmark_elevation" type="number" step="0.0001" v-bind="fieldAttrs" placeholder="100.0000" />
                </Field>
              </div>
              <div class="grid grid-cols-2 gap-4">
                <Field label="Kelas Toleransi">
                  <select v-model="form.tolerance_class" v-bind="fieldAttrs">
                    <option value="LAA">LAA — c = 2 mm</option>
                    <option value="LA">LA — c = 4 mm (default)</option>
                    <option value="LB">LB — c = 8 mm</option>
                    <option value="LC">LC — c = 12 mm</option>
                  </select>
                </Field>
                <Field label="Metode Perataan">
                  <select v-model="form.adjustment_method" v-bind="fieldAttrs">
                    <option value="equal">Equal Distribution</option>
                    <option value="bowditch">Bowditch</option>
                    <option value="least_squares">Least Squares</option>
                  </select>
                </Field>
              </div>
              <Field label="Deskripsi">
                <textarea v-model="form.description" v-bind="fieldAttrs" rows="2" placeholder="Opsional…" />
              </Field>

              <!-- Errors -->
              <div v-if="formErrors" class="bg-red-50 border border-red-200 rounded-lg px-4 py-3 space-y-1">
                <p v-for="(msgs, field) in formErrors" :key="field" class="text-xs text-red-700">
                  <strong class="capitalize">{{ field }}</strong>: {{ Array.isArray(msgs) ? msgs[0] : msgs }}
                </p>
              </div>

              <div class="flex justify-end gap-3 pt-2">
                <button type="button" @click="closeForm" class="text-sm text-slate-500 hover:text-slate-700 px-4 py-2 transition-colors">Batal</button>
                <button type="submit" :disabled="submitting"
                  class="bg-blue-600 hover:bg-blue-500 disabled:opacity-50 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors">
                  {{ submitting ? 'Menyimpan…' : (editTarget ? 'Simpan Perubahan' : 'Simpan Proyek') }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ── Delete Confirm Modal ──────────────────────────────────── -->
    <Teleport to="body">
      <Transition enter-active-class="transition duration-150 ease-out" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100"
                  leave-active-class="transition duration-100 ease-in"  leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
        <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
          <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden">
            <div class="bg-red-600 px-6 py-4">
              <p class="font-mono text-[10px] text-red-200 tracking-widest uppercase mb-0.5">Konfirmasi</p>
              <h2 class="font-bold text-white">Hapus Proyek?</h2>
            </div>
            <div class="px-6 py-5">
              <p class="text-sm text-slate-600 mb-1">
                Proyek <strong class="text-slate-800">{{ deleteTarget.name }}</strong> akan dihapus beserta semua bacaan dan elevasi.
              </p>
              <p class="text-xs text-red-600 font-medium mb-5">Tindakan ini tidak dapat dibatalkan.</p>
              <div class="flex justify-end gap-3">
                <button @click="deleteTarget = null" class="text-sm text-slate-500 hover:text-slate-700 px-4 py-2">Batal</button>
                <button @click="submitDelete" :disabled="submitting"
                  class="bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white text-sm font-semibold px-5 py-2.5 rounded-lg transition-colors">
                  {{ submitting ? 'Menghapus…' : 'Ya, Hapus' }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>

  </AppLayout>
</template>

<script setup>
import { ref, computed, h, defineAsyncComponent } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusPill from '@/Components/StatusPill.vue'
import Field from '@/Components/Field.vue'
const ProjectsOverviewMap = defineAsyncComponent(() => import('@/Components/Map/ProjectsOverviewMap.vue'))

// ── Inline StatTile sub-component ───────────────────────────
const StatTile = (props) => h('div', { class: 'px-6 py-3 flex flex-col items-center min-w-[80px]' }, [
  h('span', { class: `font-mono text-xl font-bold tabular-nums ${props.color}` }, props.value),
  h('span', { class: 'text-[10px] text-slate-400 mt-0.5 tracking-wider uppercase' }, props.label),
])
StatTile.props = ['label', 'value', 'color']

const props = defineProps({
  projects:  { type: Array, default: () => [] },
  mapPoints: { type: Array, default: () => [] },
})

// ── Filter / search ──────────────────────────────────────────
const search       = ref('')
const filterStatus = ref('')

const statusOpts = [
  { value: '', label: 'Semua' },
  { value: 'draft', label: 'Draft' },
  { value: 'calculated', label: 'Dihitung' },
  { value: 'accepted', label: 'Diterima' },
  { value: 'rejected', label: 'Ditolak' },
]

const filtered = computed(() => {
  let list = props.projects
  if (filterStatus.value) list = list.filter(p => p.status === filterStatus.value)
  if (search.value.trim()) {
    const q = search.value.trim().toLowerCase()
    list = list.filter(p => p.name.toLowerCase().includes(q) || p.location.toLowerCase().includes(q))
  }
  return list
})

// ── Status helpers ───────────────────────────────────────────
function statusAccent(status) {
  return {
    draft:      'bg-amber-300',
    calculated: 'bg-blue-400',
    accepted:   'bg-emerald-400',
    rejected:   'bg-red-400',
  }[status] ?? 'bg-slate-200'
}

// ── Form state ───────────────────────────────────────────────
const showForm    = ref(false)
const editTarget  = ref(null)
const deleteTarget = ref(null)
const submitting  = ref(false)
const formErrors  = ref(null)

const blankForm = () => ({
  name: '', location: '', survey_date: '',
  benchmark_name: '', benchmark_elevation: '',
  description: '', tolerance_class: 'LA', adjustment_method: 'equal',
})
const form = ref(blankForm())

const fieldAttrs = {
  class: 'w-full border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-400 bg-slate-50 placeholder-slate-400 transition-colors',
}

function openCreate() {
  editTarget.value = null
  form.value = blankForm()
  formErrors.value = null
  showForm.value = true
}

function openEdit(project) {
  editTarget.value = project
  form.value = {
    name: project.name, location: project.location,
    survey_date: project.survey_date, benchmark_name: project.benchmark_name,
    benchmark_elevation: project.benchmark_elevation,
    description: project.description ?? '',
    tolerance_class: project.tolerance_class,
    adjustment_method: project.adjustment_method,
  }
  formErrors.value = null
  showForm.value = true
}

function closeForm() {
  showForm.value = false; editTarget.value = null; formErrors.value = null
}

function submitForm() {
  submitting.value = true; formErrors.value = null
  const method = editTarget.value ? 'put' : 'post'
  const url    = editTarget.value ? route('projects.update', editTarget.value.id) : route('projects.store')
  router[method](url, form.value, {
    onSuccess: () => closeForm(),
    onError:   (e) => { formErrors.value = e },
    onFinish:  () => { submitting.value = false },
  })
}

function confirmDelete(project) { deleteTarget.value = project }

function submitDelete() {
  if (!deleteTarget.value) return
  submitting.value = true
  router.delete(route('projects.destroy', deleteTarget.value.id), {
    onSuccess: () => { deleteTarget.value = null },
    onFinish:  () => { submitting.value = false },
  })
}

// ── Helpers ──────────────────────────────────────────────────
function countByStatus(s) { return props.projects.filter(p => p.status === s).length }

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>