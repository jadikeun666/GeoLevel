<template>
  <div class="relative">
    <h3 v-if="station" class="text-sm font-semibold text-gray-700 mb-2">
      Profil Melintang — {{ station }}
    </h3>
    <canvas ref="canvasRef" class="max-h-52"></canvas>
    <p v-if="!offsets.length" class="text-center text-sm text-gray-400 py-10">
      Belum ada data profil melintang untuk stasiun ini.
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
} from 'chart.js'

Chart.register(LineController, LineElement, PointElement, LinearScale, Tooltip, Legend)

const props = defineProps({
  station: { type: String, required: true },
  /** Array<{ side: 'L'|'R'|'C', distance: number, elevation: number }> */
  offsets: { type: Array, required: true },
})

const canvasRef = ref(null)
let chart = null

/**
 * Convert offset array to chart points.
 * L offsets are negative X, C is 0, R is positive X.
 */
function toPoints(offsets) {
  return offsets.map(o => {
    const x = o.side === 'L' ? -o.distance : o.side === 'R' ? o.distance : 0
    return { x, y: o.elevation, side: o.side }
  }).sort((a, b) => a.x - b.x)
}

function buildChart() {
  if (chart) { chart.destroy(); chart = null }
  if (!canvasRef.value || !props.offsets.length) return

  const pts = toPoints(props.offsets)

  chart = new Chart(canvasRef.value, {
    type: 'line',
    data: {
      datasets: [{
        label: 'Profil Melintang',
        data: pts,
        borderColor: '#16a34a',
        backgroundColor: 'rgba(22,163,74,0.08)',
        pointBackgroundColor: pts.map(p =>
          p.side === 'C' ? '#dc2626' : '#16a34a'
        ),
        pointRadius: 5,
        fill: true,
        tension: 0,
      }],
    },
    options: {
      responsive: true,
      plugins: {
        tooltip: {
          callbacks: {
            label: (item) => {
              const p = pts[item.dataIndex]
              const side = p.side === 'L' ? 'Kiri' : p.side === 'R' ? 'Kanan' : 'Tengah'
              return `${side} ${Math.abs(p.x).toFixed(3)} m  |  Elev: ${p.y.toFixed(4)} m`
            },
          },
        },
        legend: { display: false },
      },
      scales: {
        x: {
          type: 'linear',
          title: { display: true, text: '← Kiri        Jarak (m)        Kanan →', font: { size: 11 } },
          ticks: {
            font: { size: 10 },
            callback: (v) => Math.abs(v).toFixed(0),
          },
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
watch(() => [props.offsets, props.station], buildChart, { deep: true })
onBeforeUnmount(() => chart?.destroy())
</script>