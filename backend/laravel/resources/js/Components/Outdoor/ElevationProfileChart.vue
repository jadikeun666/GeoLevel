<template>
  <div class="space-y-2" data-testid="profile">
    <h2 class="text-sm font-bold text-slate-700" data-testid="profile-title">
      Profil Elevasi <span class="font-normal text-slate-400">· Elevasi GPS, bukan hasil leveling</span>
    </h2>

    <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
    <p v-else-if="loading" class="text-xs text-slate-500">Memuat profil…</p>
    <p v-else-if="!profile.has_elevation" class="text-xs text-slate-400" data-testid="profile-empty">
      Track ini tidak punya data elevasi.
    </p>
    <div v-else class="bg-white rounded-xl border border-slate-200 p-3" style="height: 260px" data-testid="profile-chart">
      <Line :data="chartData" :options="options" />
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import { Line } from 'vue-chartjs'
import { Chart, LineElement, PointElement, LinearScale, Tooltip } from 'chart.js'
import { TRACK_COLOR } from './outdoorCategories'

// Contract: docs/outdoor.md, 19.4. Data: GET outdoor.tracks.profile -> points [{ d (m), ele (m) }].
Chart.register(LineElement, PointElement, LinearScale, Tooltip)

const props = defineProps({ trackId: { type: Number, required: true } })

const profile = ref({ points: [], has_elevation: false })
const loading = ref(true)
const error   = ref('')

const chartData = computed(() => ({
  datasets: [{
    data: profile.value.points.map((p) => ({ x: p.d, y: p.ele })),
    borderColor: TRACK_COLOR,
    backgroundColor: TRACK_COLOR,
    borderWidth: 2,
    pointRadius: 0,
    pointHitRadius: 8,
  }],
}))

const options = {
  responsive: true,
  maintainAspectRatio: false,
  animation: false,
  plugins: { legend: { display: false } },
  scales: {
    x: { type: 'linear', title: { display: true, text: 'Jarak (m)' } },
    y: { type: 'linear', title: { display: true, text: 'Elevasi GPS (m)' } },
  },
}

onMounted(async () => {
  try {
    const { data } = await axios.get(route('outdoor.tracks.profile', props.trackId))
    profile.value = data.data
  } catch (e) {
    error.value = 'Gagal memuat profil elevasi.'
  } finally {
    loading.value = false
  }
})
</script>
