<template>
  <AppLayout title="Proyek Survei">
    <div class="min-h-screen bg-stone-50 font-mono">

      <!-- ── Top bar ─────────────────────────────────────────── -->
      <header class="border-b border-stone-200 bg-white px-6 py-4 flex items-center justify-between">
        <div>
          <p class="text-xs text-stone-400 tracking-widest uppercase mb-0.5">GeoLevel</p>
          <h1 class="text-xl font-bold text-stone-800 tracking-tight">Proyek Survei</h1>
        </div>
        <button
          @click="showCreate = true"
          class="inline-flex items-center gap-2 bg-stone-800 hover:bg-stone-700 text-white text-sm font-semibold px-4 py-2 rounded transition-colors"
        >
          <span class="text-base leading-none">+</span> Proyek Baru
        </button>
      </header>

      <!-- ── Stats bar ──────────────────────────────────────── -->
      <div class="border-b border-stone-200 bg-white px-6 py-3 flex gap-6 text-xs text-stone-500">
        <span>Total: <strong class="text-stone-800">{{ projects.length }}</strong></span>
        <span>Diterima: <strong class="text-green-700">{{ countByStatus('accepted') }}</strong></span>
        <span>Ditolak: <strong class="text-red-700">{{ countByStatus('rejected') }}</strong></span>
        <span>Draft: <strong class="text-stone-600">{{ countByStatus('draft') }}</strong></span>
      </div>

      <!-- ── Content ────────────────────────────────────────── -->
      <div class="px-6 py-6">

        <!-- Empty state -->
        <div
          v-if="projects.length === 0"
          class="border-2 border-dashed border-stone-200 rounded-xl text-center py-20 text-stone-400"
        >
          <div class="text-4xl mb-3">📐</div>
          <p class="text-sm font-medium">Belum ada proyek.</p>
          <p class="text-xs mt-1">Klik <em>Proyek Baru</em> untuk memulai survei pertama.</p>
        </div>

        <!-- Project grid -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
          <Link
            v-for="p in projects"
            :key="p.id"
            :href="route('projects.show', p.id)"
            class="group block border border-stone-200 bg-white rounded-xl p-5 hover:border-stone-400 hover:shadow-md transition-all"
          >
            <!-- Header row -->
            <div class="flex items-start justify-between gap-2 mb-3">
              <h2 class="text-sm font-bold text-stone-800 group-hover:text-stone-900 leading-snug">
                {{ p.name }}
              </h2>
              <StatusPill :status="p.status" />
            </div>

            <!-- Meta -->
            <dl class="text-xs text-stone-500 space-y-1">
              <div class="flex gap-2">
                <dt class="w-20 shrink-0">Lokasi</dt>
                <dd class="text-stone-700 truncate">{{ p.location }}</dd>
              </div>
              <div class="flex gap-2">
                <dt class="w-20 shrink-0">Tanggal</dt>
                <dd class="text-stone-700">{{ formatDate(p.survey_date) }}</dd>
              </div>
              <div class="flex gap-2">
                <dt class="w-20 shrink-0">Benchmark</dt>
                <dd class="text-stone-700">{{ p.benchmark_name }} ({{ p.benchmark_elevation }} m)</dd>
              </div>
              <div class="flex gap-2" v-if="p.closure_error != null">
                <dt class="w-20 shrink-0">fh</dt>
                <dd :class="p.status === 'accepted' ? 'text-green-700' : 'text-red-600'" class="font-semibold tabular-nums">
                  {{ Number(p.closure_error).toFixed(6) }} m
                </dd>
              </div>
            </dl>

            <!-- Footer -->
            <div class="mt-4 pt-3 border-t border-stone-100 flex items-center justify-between">
              <span class="text-[10px] text-stone-400 uppercase tracking-wider">
                {{ p.tolerance_class }} · {{ p.adjustment_method }}
              </span>
              <span class="text-xs text-stone-400 group-hover:text-stone-600 transition-colors">
                Buka →
              </span>
            </div>
          </Link>
        </div>
      </div>
    </div>

    <!-- ── Create Modal ───────────────────────────────────────── -->
    <Teleport to="body">
      <div
        v-if="showCreate"
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm"
        @click.self="showCreate = false"
      >
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 overflow-hidden">
          <div class="border-b border-stone-100 px-6 py-4 flex items-center justify-between">
            <h2 class="font-bold text-stone-800">Proyek Baru</h2>
            <button @click="showCreate = false" class="text-stone-400 hover:text-stone-600 text-xl leading-none">×</button>
          </div>

          <form @submit.prevent="submitCreate" class="px-6 py-5 space-y-4">
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
                  <option value="LAA">LAA (c=2mm)</option>
                  <option value="LA">LA (c=4mm) — default</option>
                  <option value="LB">LB (c=8mm)</option>
                  <option value="LC">LC (c=12mm)</option>
                </select>
              </Field>
              <Field label="Metode Perataan">
                <select v-model="form.adjustment_method" v-bind="fieldAttrs">
                  <option value="equal">Equal Distribution</option>
                  <option value="bowditch">Bowditch</option>
                </select>
              </Field>
            </div>
            <Field label="Deskripsi">
              <textarea v-model="form.description" v-bind="fieldAttrs" rows="2" placeholder="Opsional…" />
            </Field>

            <div v-if="errors" class="text-xs text-red-600 space-y-1">
              <p v-for="(msgs, field) in errors" :key="field">
                <strong class="capitalize">{{ field }}</strong>: {{ msgs[0] }}
              </p>
            </div>

            <div class="flex justify-end gap-3 pt-2">
              <button type="button" @click="showCreate = false" class="text-sm text-stone-500 hover:text-stone-700 px-4 py-2">
                Batal
              </button>
              <button
                type="submit"
                :disabled="submitting"
                class="bg-stone-800 hover:bg-stone-700 disabled:opacity-50 text-white text-sm font-semibold px-5 py-2 rounded-lg transition-colors"
              >
                {{ submitting ? 'Menyimpan…' : 'Simpan Proyek' }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import StatusPill from '@/Components/StatusPill.vue'
import Field from '@/Components/Field.vue'

const props = defineProps({
  projects: { type: Array, default: () => [] },
})

// ── Modal state ──────────────────────────────────────────────
const showCreate = ref(false)
const submitting = ref(false)
const errors     = ref(null)

const blankForm = () => ({
  name:                '',
  location:            '',
  survey_date:         '',
  benchmark_name:      '',
  benchmark_elevation: '',
  description:         '',
  tolerance_class:     'LA',
  adjustment_method:   'equal',
})

const form = ref(blankForm())

const fieldAttrs = {
  class: 'w-full border border-stone-200 rounded-lg px-3 py-2 text-sm text-stone-800 focus:outline-none focus:ring-2 focus:ring-stone-400 bg-stone-50',
}

function submitCreate() {
  submitting.value = true
  errors.value     = null

  router.post(route('projects.store'), form.value, {
    onSuccess: () => {
      showCreate.value = false
      form.value       = blankForm()
    },
    onError: (e) => { errors.value = e },
    onFinish: () => { submitting.value = false },
  })
}

// ── Helpers ──────────────────────────────────────────────────
function countByStatus(s) {
  return props.projects.filter(p => p.status === s).length
}

function formatDate(d) {
  if (!d) return '—'
  return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}
</script>