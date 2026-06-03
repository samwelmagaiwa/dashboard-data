<script setup>
import { computed, ref, watch, nextTick, onMounted, onUnmounted } from 'vue'
import { useDashboardStore } from '@/stores/dashboard'
import { CChart } from '@coreui/vue-chartjs'

const dashboard = useDashboardStore()
const chartRef = ref(null)
const activeClinicIndex = ref(-1)

let previewStartTimer = null
let previewStopTimer = null
let previewRestartTimer = null
let previewCycleTimer = null

// ── Sorted clinics ────────────────────────────────────────────────────────
const sortedClinics = computed(() =>
  [...(dashboard.realClinics || [])].sort((a, b) => b.total_visits - a.total_visits),
)

// View Toggle State (Default to Top 10)
const showAll = ref(false)

// Display filtered clinics (Top 10 by default)
const displayClinics = computed(() => {
  const all = sortedClinics.value
  return showAll.value ? all : all.slice(0, 10)
})

const chartContainerStyle = computed(() => ({
  width: showAll.value
    ? displayClinics.value.length > 6
      ? `${Math.max(displayClinics.value.length * 160, 900)}px`
      : '100%'
    : '100%',
  height: '100%',
}))

const getChartInstance = () => {
  const refValue = chartRef.value
  if (!refValue) return null

  return refValue.chart || refValue.instance || refValue.$?.exposed?.chart || refValue
}

const clearAutoPreviewTimers = () => {
  clearTimeout(previewStartTimer)
  clearTimeout(previewStopTimer)
  clearTimeout(previewRestartTimer)
  clearInterval(previewCycleTimer)
}

const clearActivePreview = (shouldUpdate = true) => {
  const chartInstance = getChartInstance()
  activeClinicIndex.value = -1

  if (!chartInstance) return

  if (typeof chartInstance.setActiveElements === 'function') {
    chartInstance.setActiveElements([])
  }
  if (chartInstance.tooltip?.setActiveElements) {
    chartInstance.tooltip.setActiveElements([], { x: 0, y: 0 })
  }

  if (shouldUpdate) {
    chartInstance.update()
  }
}

const showPreviewForIndex = async (index) => {
  const chartInstance = getChartInstance()
  if (!chartInstance || !displayClinics.value.length) return

  await nextTick()

  const safeIndex = index % displayClinics.value.length
  const activeElements = [
    { datasetIndex: 0, index: safeIndex },
    { datasetIndex: 1, index: safeIndex },
  ]

  const currentBar = chartInstance.getDatasetMeta(0)?.data?.[safeIndex]
  const previousBar = chartInstance.getDatasetMeta(1)?.data?.[safeIndex]
  const anchorX = currentBar?.x ?? previousBar?.x ?? 0
  const anchorY = Math.min(currentBar?.y ?? Number.MAX_SAFE_INTEGER, previousBar?.y ?? Number.MAX_SAFE_INTEGER)

  activeClinicIndex.value = safeIndex
  if (typeof chartInstance.setActiveElements !== 'function') return

  chartInstance.setActiveElements(activeElements)
  if (chartInstance.tooltip?.setActiveElements) {
    chartInstance.tooltip.setActiveElements(activeElements, {
      x: anchorX,
      y: Number.isFinite(anchorY) ? anchorY : 0,
    })
  }
  chartInstance.update()
}

const startAutoPreviewCycle = async () => {
  clearAutoPreviewTimers()
  if (!displayClinics.value.length) return

  let currentIndex = 0
  await showPreviewForIndex(currentIndex)

  previewCycleTimer = setInterval(() => {
    if (!displayClinics.value.length) return
    currentIndex = (currentIndex + 1) % displayClinics.value.length
    showPreviewForIndex(currentIndex)
  }, 8000)

  previewStopTimer = setTimeout(() => {
    clearInterval(previewCycleTimer)
    clearActivePreview()
    previewRestartTimer = setTimeout(() => {
      startAutoPreviewCycle()
    }, 300000)
  }, 60000)
}

const scheduleAutoPreview = async () => {
  clearAutoPreviewTimers()
  clearActivePreview(false)
  if (!displayClinics.value.length) return

  await nextTick()
  previewStartTimer = setTimeout(() => {
    startAutoPreviewCycle()
  }, 1200)
}

watch([displayClinics, chartRef], () => {
  scheduleAutoPreview()
}, { flush: 'post' })

onMounted(() => {
  scheduleAutoPreview()
})

onUnmounted(() => {
  clearAutoPreviewTimers()
  clearActivePreview(false)
})

const BLUE = '#003082' 
const ORANGE = '#ff6b00' 

function rgba(hex, alpha) {
  const r = parseInt(hex.slice(1, 3), 16)
  const g = parseInt(hex.slice(3, 5), 16)
  const b = parseInt(hex.slice(5, 7), 16)
  return `rgba(${r},${g},${b},${alpha})`
}

// ── Chart data — Grouped Diverging (Current Up, Previous Down) ───────────
const barData = computed(() => {
  const clinics = displayClinics.value
  const isTop10 = !showAll.value

  return {
    labels: clinics.map((c) => {
      const name = c.clinic_name.replace(/^MLG\s+/i, '')
      if (name.length > 10 && name.includes(' ')) {
        const words = name.split(' ')
        const mid = Math.ceil(words.length / 2)
        return [words.slice(0, mid).join(' '), words.slice(mid).join(' ')]
      }
      return name
    }),
    datasets: [
      {
        label: 'Current Visits',
        data: clinics.map((c) => c.total_visits || 0),
        backgroundColor: clinics.map((_, i) => rgba(BLUE, i === activeClinicIndex.value ? 0.5 : 0.22)),
        borderColor: BLUE,
        borderWidth: clinics.map((_, i) => (i === activeClinicIndex.value ? 3 : 2)),
        borderRadius: { topLeft: 5, topRight: 5, bottomLeft: 0, bottomRight: 0 },
        barPercentage: isTop10 ? 0.75 : 0.65,
        categoryPercentage: 0.85,
        hoverBackgroundColor: rgba(BLUE, 0.5),
        hoverBorderWidth: 3,
      },
      {
        label: 'Previous Visits',
        data: clinics.map((c) => -(c.previous_visits || 0)),
        backgroundColor: clinics.map((_, i) => rgba(ORANGE, i === activeClinicIndex.value ? 0.5 : 0.22)),
        borderColor: ORANGE,
        borderWidth: clinics.map((_, i) => (i === activeClinicIndex.value ? 3 : 2)),
        borderRadius: { topLeft: 0, topRight: 0, bottomLeft: 5, bottomRight: 5 },
        barPercentage: isTop10 ? 0.75 : 0.65,
        categoryPercentage: 0.85,
        hoverBackgroundColor: rgba(ORANGE, 0.5),
        hoverBorderWidth: 3,
      },
    ],
  }
})

// ── Chart options ─────────────────────────────────────────────────────────
const chartOptions = computed(() => {
  const clinics = displayClinics.value
  const isTop10 = !showAll.value
  const maxCurrent = Math.max(...clinics.map((c) => c.total_visits || 0), 10)
  const maxPrevious = Math.max(...clinics.map((c) => c.previous_visits || 0), 10)

  const yMax = Math.ceil(maxCurrent * 1.2)
  const yMin = -Math.ceil(maxPrevious * 1.2)

  return {
    responsive: true,
    maintainAspectRatio: false,
    layout: {
      padding: { top: 60, bottom: 0, left: isTop10 ? 5 : 10, right: isTop10 ? 5 : 10 },
    },
    // Ensure tooltip stays open and is easy to see
    interaction: {
      mode: 'index',
      intersect: false,
    },
    plugins: {
      legend: { display: false },
      tooltip: {
        enabled: true,
        backgroundColor: 'rgba(255,255,255,0.98)',
        titleColor: '#0f172a',
        bodyColor: '#334155',
        borderColor: '#003082',
        borderWidth: 1.5,
        padding: 10,
        cornerRadius: 10,
        boxPadding: 4,
        usePointStyle: true,
        titleFont: { size: 13, weight: '800', family: "'Outfit', sans-serif" },
        bodyFont: { size: 12, weight: '600', family: "'Outfit', sans-serif" },
        displayColors: true,
        callbacks: {
          label: (ctx) => {
            const clinic = clinics[ctx.dataIndex]
            if (!clinic) return ''
            const isCurrent = ctx.datasetIndex === 0
            const val = Math.abs(ctx.raw)
            return `  ${ctx.dataset.label}: ${val.toLocaleString()}`
          },
          afterLabel: (ctx) => {
            const clinic = clinics[ctx.dataIndex]
            if (!clinic) return ''
            const lines = []
            
            if (ctx.datasetIndex === 0) {
              const pendingLabel = dashboard.isTodaySelected ? 'Await Consultation' : 'Not Consulted'
              lines.push(`  Consulted: ${clinic.consulted || 0}`)
              lines.push(`  ${pendingLabel}: ${clinic.pending || 0}`)
              
              const sign = (clinic.trend || 0) > 0 ? '+' : ''
              lines.push(`  Change: ${sign}${clinic.trend}% (${clinic.interpretation})`)
            } else {
              lines.push(`  Not Consulted: ${clinic.previous_pending || 0}`)
            }
            return lines
          },
        },
      },
    },
    scales: {
      x: {
        grid: { display: false },
        ticks: {
          font: { size: 14, weight: '800', family: "'Outfit', sans-serif" },
          color: '#0f172a',
          maxRotation: 0,
          minRotation: 0,
          autoSkip: false,
          padding: 25,
        },
      },
      y: {
        min: yMin,
        max: yMax,
        title: {
          display: true,
          text: 'Visits (Current Up / Previous Down)',
          color: '#1e293b',
          font: { size: 14, weight: '900', family: "'Outfit', sans-serif" },
        },
        grid: {
          color: (ctx) => (ctx.tick.value === 0 ? 'rgba(0,0,0,0.5)' : 'rgba(148,163,184,0.2)'),
          lineWidth: (ctx) => (ctx.tick.value === 0 ? 2.5 : 1),
        },
        ticks: {
          padding: 8,
          color: '#334155',
          font: { size: 15, weight: '700' },
          callback: (v) => Math.abs(v).toLocaleString(),
          maxTicksLimit: 14,
        },
      },
    },
  }
})

// ── Custom plugin: Absolute values at tips -> dotted line -> Trend % on Current ──
const barLabelsPlugin = {
  id: 'clinicGroupedDivergingLabels',
  afterDatasetsDraw(chart) {
    const { ctx } = chart
    const yAxis = chart.scales.y
    const xAxisY = yAxis.getPixelForValue(0)

    ctx.save()
    ctx.strokeStyle = '#94a3b8' 
    ctx.lineWidth = 2.5 

    const meta0 = chart.getDatasetMeta(0)
    const dataLen = chart.data.labels.length
    for (let i = 0; i < dataLen - 1; i++) {
        // Vertical dividers (Same as before)
      const bar1 = meta0.data[i]
      const bar2 = meta0.data[i + 1]
      if (bar1 && bar2) {
        const dividerX = (bar1.x + bar2.x) / 2
        ctx.beginPath()
        ctx.moveTo(dividerX, chart.chartArea.bottom + 5)
        ctx.lineTo(dividerX, chart.chartArea.bottom + 80) 
        ctx.stroke()
      }
    }
    ctx.restore()

    chart.data.datasets.forEach((dataset, dsIdx) => {
      const meta = chart.getDatasetMeta(dsIdx)
      const isCurrent = dsIdx === 0

      meta.data.forEach((bar, index) => {
        const rawVal = dataset.data[index]
        if (rawVal === undefined || rawVal === null) return

        const clinic = displayClinics.value[index]
        if (!clinic) return

        const { x, y } = bar.tooltipPosition()
        const absoluteVal = Math.abs(rawVal)
        const isPositive = rawVal >= 0

        ctx.save()
        ctx.font = "bold 18px 'Outfit', sans-serif"
        ctx.fillStyle = isPositive ? BLUE : ORANGE
        ctx.textAlign = 'center'
        ctx.textBaseline = isPositive ? 'bottom' : 'top'
        ctx.fillText(absoluteVal.toLocaleString(), x, isPositive ? y - 8 : y + 8)

        // ── Sub-metrics (Consulted / Await) ────────────────
        ctx.save()
        const isToday = dashboard.isTodaySelected
        const consulted = clinic.consulted
        const pending = clinic.pending
        const prevPending = clinic.previous_pending

        ctx.font = "bold 14px 'Outfit', sans-serif"
        
        if (isCurrent) {
          // Current Bar (Up)
          const consultedVal = consulted !== undefined ? consulted : 0
          const pendingVal = pending !== undefined ? pending : 0
          
          if (consultedVal > 0 || pendingVal > 0) {
            const subY = y - 32
            
            // Draw Consulted (Green with Up Arrow)
            ctx.fillStyle = '#16a34a'
            ctx.textAlign = 'right'
            ctx.fillText(`${consultedVal.toLocaleString()} ↑`, x - 5, subY)
            
            // Draw Await Consultation (Pink with Down Arrow)
            ctx.fillStyle = '#ec4899'
            ctx.textAlign = 'left'
            ctx.fillText(`↓ ${pendingVal.toLocaleString()}`, x + 5, subY)
          }
        } else {
          // Previous Bar (Down)
          const prevConsulted = clinic.previous_consulted !== undefined ? clinic.previous_consulted : 0
          const prevPending = clinic.previous_pending !== undefined ? clinic.previous_pending : 0
          
          if (prevConsulted > 0 || prevPending > 0) {
            const subY = y + 32
            ctx.textBaseline = 'top'
            
            // Draw Previous Consulted (Green with Up Arrow)
            ctx.fillStyle = '#16a34a'
            ctx.textAlign = 'right'
            ctx.fillText(`${prevConsulted.toLocaleString()} ↑`, x - 5, subY)
            
            // Draw Previous Not Consulted (Red with Down Arrow)
            ctx.fillStyle = '#dc2626'
            ctx.textAlign = 'left'
            ctx.fillText(`↓ ${prevPending.toLocaleString()}`, x + 5, subY)
          }
        }
        ctx.restore()

        if (isCurrent && clinic.trend !== undefined) {
          const trend = clinic.trend
          const trendStr = `${trend > 0 ? '+' : ''}${trend}%`
          const topSpace = 5
          const trendY = topSpace

          let trendX = x
          if (trend < 0) {
            const prevMeta = chart.getDatasetMeta(1)
            const prevBar = prevMeta.data[index]
            if (prevBar) {
              trendX = prevBar.x
            }
          }

          const xAxisY = chart.scales.y.getPixelForValue(0)
          const lineStart = trend > 0 ? y - 28 : xAxisY - 6
          const lineEnd = trendY + 20

          if (lineStart > lineEnd) {
            ctx.beginPath()
            ctx.setLineDash([3, 4])
            ctx.moveTo(trendX, lineStart)
            ctx.lineTo(trendX, lineEnd)
            ctx.strokeStyle = 'rgba(100,116,139,0.5)'
            ctx.lineWidth = 1.2
            ctx.stroke()
            ctx.setLineDash([])
          }

          ctx.font = "bold 20px 'Outfit', sans-serif"
          const isGrowth = trend >= 0
          ctx.fillStyle = isGrowth ? '#059669' : '#dc2626'
          ctx.textBaseline = 'top'
          ctx.textAlign = 'center'
          ctx.fillText(trendStr, trendX, trendY)

          if (isGrowth) {
            const arrowX = trendX
            const arrowY = trendY + 28
            ctx.beginPath()
            ctx.moveTo(arrowX, arrowY + 8) 
            ctx.lineTo(arrowX, arrowY - 8) 
            ctx.moveTo(arrowX, arrowY - 8)
            ctx.lineTo(arrowX - 5, arrowY - 3)
            ctx.moveTo(arrowX, arrowY - 8)
            ctx.lineTo(arrowX + 5, arrowY - 3)
            ctx.strokeStyle = '#059669'
            ctx.lineWidth = 3
            ctx.stroke()
          } else {
            const arrowX = trendX
            const arrowY = xAxisY
            const prevMeta = chart.getDatasetMeta(1)
            const prevBar = prevMeta.data[index]
            let shaftLength = 18
            if (prevBar) {
              const barHeight = Math.abs(prevBar.y - xAxisY)
              shaftLength = Math.min(18, Math.max(6, barHeight * 0.4))
            }
            ctx.beginPath()
            ctx.moveTo(arrowX, arrowY)
            ctx.lineTo(arrowX, arrowY + shaftLength) 
            ctx.moveTo(arrowX, arrowY + shaftLength)
            ctx.lineTo(arrowX - 5, arrowY + shaftLength - 6)
            ctx.moveTo(arrowX, arrowY + shaftLength)
            ctx.lineTo(arrowX + 5, arrowY + shaftLength - 6)
            ctx.strokeStyle = '#dc2626'
            ctx.lineWidth = 3
            ctx.stroke()
          }
        }
        ctx.restore()
      })
    })

    // ── 4. Zig-Zag Number Line ──
    ctx.save()
    ctx.strokeStyle = '#003082' 
    ctx.lineWidth = 3
    ctx.lineJoin = 'round'
    ctx.lineCap = 'round'
    ctx.shadowColor = 'rgba(255,255,255,0.8)'
    ctx.shadowBlur = 4

    ctx.beginPath()
    let points = []
    const metaCurrent = chart.getDatasetMeta(0)
    const metaPrevious = chart.getDatasetMeta(1)

    chart.data.labels.forEach((_, i) => {
      const barC = metaCurrent.data[i]
      const barP = metaPrevious.data[i]
      if (barC && barP) {
        points.push({ x: barC.x, y: barC.y })
        points.push({ x: barP.x, y: barP.y })
      }
    })

    if (points.length > 1) {
      ctx.beginPath()
      ctx.moveTo(points[0].x, points[0].y)
      for (let i = 0; i < points.length - 1; i++) {
        const p0 = points[i]
        const p1 = points[i + 1]
        const cp1x = p0.x + (p1.x - p0.x) * 0.5
        const cp2x = p0.x + (p1.x - p0.x) * 0.5
        ctx.bezierCurveTo(cp1x, p0.y, cp2x, p1.y, p1.x, p1.y)
      }
      ctx.stroke()

      points.forEach((p) => {
        ctx.beginPath()
        ctx.arc(p.x, p.y, 5, 0, Math.PI * 2)
        ctx.fillStyle = '#003082' 
        ctx.fill()
        ctx.strokeStyle = '#fff' 
        ctx.lineWidth = 2 
        ctx.stroke()
      })
    }
    ctx.restore()
  },
}
</script>

<template>
  <div class="card border-0 shadow-sm overflow-hidden histogram-container">
    <!-- Card Header -->
    <div
      class="card-header bg-white border-0 py-0 d-flex justify-content-between align-items-center position-relative"
      style="height: 54px"
    >
      <h5 class="mb-0 fw-bold text-primary" style="font-size: 18px">
        All Clinics Total visits comparison
      </h5>

      <!-- Legend Pill -->
      <div class="chart-legend-overlay d-flex gap-2 align-items-center">
        <div class="legend-badge current-visits d-flex align-items-center gap-2">
          <div class="d-flex align-items-center gap-2">
            <span class="badge-icon">▲</span>
            <span class="badge-text text-nowrap">Current Visits (Up)</span>
          </div>
          <div class="d-flex align-items-center gap-2 ps-2 border-start border-slate-200">
             <span class="fw-black" style="color: #16a34a; font-size: 16px">↑</span>
             <small class="fw-bold" style="color: #16a34a; font-size: 10px; letter-spacing: 0.5px">CONSULTED</small>
             <span class="fw-black ms-1" style="color: #ec4899; font-size: 16px">↓</span>
             <small class="fw-bold" style="color: #ec4899; font-size: 10px; letter-spacing: 0.5px">AWAIT</small>
          </div>
        </div>
        <div class="legend-divider"></div>
        <div class="legend-badge previous-visits d-flex align-items-center gap-2">
          <div class="d-flex align-items-center gap-2">
            <span class="badge-icon">▼</span>
            <span class="badge-text text-nowrap">
              Previous Visits (Down)
              <small
                v-if="sortedClinics[0]?.comparison_dates"
                class="comparison-tag ms-1 text-secondary opacity-75"
              >
                {{
                  sortedClinics[0].comparison_dates.toLowerCase().includes('vs')
                    ? sortedClinics[0].comparison_dates
                    : '(vs ' + sortedClinics[0].comparison_dates + ')'
                }}
              </small>
            </span>
          </div>
          <div class="d-flex align-items-center gap-2 ps-2 border-start border-slate-200">
             <span class="fw-black" style="color: #16a34a; font-size: 16px">↑</span>
             <small class="fw-bold" style="color: #16a34a; font-size: 10px; letter-spacing: 0.5px">CONSULTED</small>
             <span class="fw-black ms-1" style="color: #dc2626; font-size: 16px">↓</span>
             <small class="fw-bold" style="color: #dc2626; font-size: 10px; letter-spacing: 0.5px">NOT CONS.</small>
          </div>
        </div>

        <div v-if="sortedClinics.length > 10" class="legend-divider"></div>

        <button
          v-if="sortedClinics.length > 10"
          type="button"
          class="btn btn-sm toggle-view-btn d-flex align-items-center"
          @click="showAll = !showAll"
          :class="showAll ? 'btn-outline-primary' : 'btn-primary'"
        >
          <span class="btn-text fw-bold">{{ showAll ? 'Top 10' : 'Full' }}</span>
        </button>
      </div>

      <div style="width: 140px"></div>
    </div>

    <div class="card-body p-0 position-relative">
      <div class="chart-wrapper p-0" :class="{ 'chart-wrapper--fit': !showAll }" style="height: 650px">
        <div :style="chartContainerStyle">
          <CChart
            ref="chartRef"
            type="bar"
            :data="barData"
            :options="chartOptions"
            :plugins="[barLabelsPlugin]"
            style="height: 100%"
          />
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.histogram-container {
  border-radius: 16px;
  background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
  border: 1px solid rgba(191, 219, 254, 0.6);
  box-shadow: 0 14px 36px rgba(15, 23, 42, 0.08);
  transform: translateY(-2px);
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.chart-legend-overlay {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 10;
  background: rgba(255, 255, 255, 0.9);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  padding: 6px 16px;
  border-radius: 30px;
  border: 1px solid rgba(226, 232, 240, 0.8);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
  white-space: nowrap;
  display: flex;
  gap: 12px;
  align-items: center;
}

.toggle-view-btn {
  border-radius: 20px;
  padding: 4px 14px;
  font-size: 13px;
  transition: all 0.3s ease;
  border-width: 2px;
}

.toggle-view-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.legend-badge {
  padding: 2px 4px;
  border-radius: 6px;
}

.badge-icon {
  font-size: 16px;
  width: 26px;
  height: 26px;
  background: rgba(0, 0, 0, 0.05);
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
}

.current-visits .badge-icon {
  background: rgba(0, 48, 130, 0.1);
  color: #003082;
}
.previous-visits .badge-icon {
  background: rgba(255, 107, 0, 0.1);
  color: #ff6b00;
}

.badge-text {
  font-size: 16px;
  font-weight: 700;
  color: #1e293b;
}

.legend-divider {
  width: 1px;
  height: 24px;
  background: #e2e8f0;
  margin: 0 8px;
}

.comparison-tag {
  font-size: 13px;
  font-weight: 500;
  font-style: italic;
}

.chart-wrapper::-webkit-scrollbar {
  height: 6px;
}

.chart-wrapper {
  overflow-x: auto;
}

.chart-wrapper--fit {
  overflow-x: hidden;
}
.chart-wrapper::-webkit-scrollbar-track {
  background: #f8fafc;
}
.chart-wrapper::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 3px;
}

:deep(.histogram-container:hover) {
  transform: translateY(-4px);
  box-shadow: 0 18px 42px rgba(15, 23, 42, 0.12);
}
</style>
