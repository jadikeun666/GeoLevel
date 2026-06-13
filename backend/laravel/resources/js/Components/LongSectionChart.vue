<template>
  <div class="relative">
    <h3 v-if="title" class="text-sm font-semibold text-gray-700 mb-2">{{ title }}</h3>
    <canvas ref="canvasRef" class="max-h-64"></canvas>
    <p v-if="!dataset.length" class="text-center text-sm text-gray-400 py-10">
      Belum ada data profil memanjang.
    </p>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import {
  Chart,
  LineController,
  LineElement,
  PointElement,
  LinearScale,
  Tooltip,
  Legend,
  Filler,
} from 'chart.js'

Chart.register(LineController, LineElement, PointElement, LinearScale, Tooltip, Legend, Filler)

const props = defineProps({
  /** Array<{ x: number, y: number, label: string }> */
  dataset: { type: Array, required: true },
  title:   { type: String, default: '' },
})

const canvasRef = ref(null)
let chart = null

function buildChart() {
  if (chart) { chart.destroy(); chart = null }
  if (!canvasRef.value || !props.dataset.length) return

  chart = new Chart(canvasRef.value, {
    type: 'line',
    data: {
      datasets: [{
        label: 'Elevasi Tetap (m)',
        data: props.dataset.map(d => ({ x: d.x, y: d.y })),
        borderColor: '#2563eb',
        backgroundColor: 'rgba(37,99,235,0.08)',
        pointBackgroundColor: '#2563eb',
        pointRadius: 4,
        pointHoverRadius: 6,
        fill: true,
        tension: 0.3,
      }],
    },
    options: {
      responsive: true,
      interaction: { mode: 'index', intersect: false },
      plugins: {
        tooltip: {
          callbacks: {
            title: (items) => {
              const idx = items[0].dataIndex
              return props.dataset[idx]?.label ?? ''
            },
            label: (item) =>
              `Elevasi: ${Number(item.parsed.y).toFixed(4)} m  |  Jarak: ${Number(item.parsed.x).toFixed(3)} m`,
          },
        },
        legend: { display: false },
      },
      scales: {
        x: {
          type: 'linear',
          title: { display: true, text: 'Jarak Kumulatif (m)', font: { size: 11 } },
          ticks: { font: { size: 10 } },
        },
        y: {
          title: { display: true, text: 'Elevasi (m)', font: { size: 11 } },
          ticks: { font: { size: 10 } },
        },
      },
    },
  })
}

onMounted(buildChart)
watch(() => props.dataset, buildChart, { deep: true })
onBeforeUnmount(() => chart?.destroy())
</script>