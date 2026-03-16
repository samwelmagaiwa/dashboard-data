<script setup>
import { computed } from 'vue'
import { useDashboardStore } from '@/stores/dashboard'
import { CChart } from '@coreui/vue-chartjs'

const dashboard = useDashboardStore()

// ── Sorted clinics ────────────────────────────────────────────────────────
const sortedClinics = computed(() =>
  [...(dashboard.realClinics || [])].sort((a, b) => b.total_visits - a.total_visits),
)

const BLUE = '#003082' // Original Current Visits color
const ORANGE = '#ff6b00' // Original Previous Visits color

function rgba(hex, alpha) {
  const r = parseInt(hex.slice(1, 3), 16)
  const g = parseInt(hex.slice(3, 5), 16)
  const b = parseInt(hex.slice(5, 7), 16)
  return `rgba(${r},${g},${b},${alpha})`
}

// ── Chart data — Grouped Diverging (Current Up, Previous Down) ───────────
const barData = computed(() => {
  const clinics = sortedClinics.value

  return {
    labels: clinics.map((c) => {
      const name = c.clinic_name.replace(/^MLG\s+/i, '')
      // Wrap logic: If name > 10 chars, try to split at spaces
      if (name.length > 10 && name.includes(' ')) {
        const words = name.split(' ')
        // Try to balance lines better: if 3 words, 1 on top, 2 on bot or vice versa.
        // We'll just split near middle space for now.
        const mid = Math.ceil(words.length / 2)
        return [words.slice(0, mid).join(' '), words.slice(mid).join(' ')]
      }
      return name
    }),
    datasets: [
      {
        label: 'Current Visits',
        // Grouped Up
        data: clinics.map((c) => c.total_visits || 0),
        backgroundColor: rgba(BLUE, 0.22),
        borderColor: BLUE,
        borderWidth: 2,
        borderRadius: { topLeft: 5, topRight: 5, bottomLeft: 0, bottomRight: 0 },
        barPercentage: 0.65,
        categoryPercentage: 0.85,
      },
      {
        label: 'Previous Visits',
        // Grouped Down
        data: clinics.map((c) => -(c.previous_visits || 0)),
        backgroundColor: rgba(ORANGE, 0.22),
        borderColor: ORANGE,
        borderWidth: 2,
        borderRadius: { topLeft: 0, topRight: 0, bottomLeft: 5, bottomRight: 5 },
        barPercentage: 0.65,
        categoryPercentage: 0.85,
      },
    ],
  }
})

// ── Chart options ─────────────────────────────────────────────────────────
const chartOptions = computed(() => {
  const clinics = sortedClinics.value
  const maxCurrent = Math.max(...clinics.map((c) => c.total_visits || 0), 10)
  const maxPrevious = Math.max(...clinics.map((c) => c.previous_visits || 0), 10)

  // Slightly more headroom (20% instead of 10%) to prevent label overlap
  const yMax = Math.ceil(maxCurrent * 1.2)
  const yMin = -Math.ceil(maxPrevious * 1.2)

  return {
    responsive: true,
    maintainAspectRatio: false,
    layout: {
      padding: { top: 60, bottom: 0, left: 10, right: 10 },
    },
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: 'rgba(255,255,255,0.95)',
        titleColor: '#1e293b',
        bodyColor: '#334155',
        borderColor: '#e2e8f0',
        borderWidth: 1,
        padding: 12,
        cornerRadius: 10,
        callbacks: {
          label: (ctx) => {
            const clinic = clinics[ctx.dataIndex]
            if (!clinic) return ''
            const val = Math.abs(ctx.raw)
            return `  ${ctx.dataset.label}: ${val.toLocaleString()}`
          },
          afterLabel: (ctx) => {
            const clinic = clinics[ctx.dataIndex]
            if (ctx.datasetIndex === 0 && clinic) {
              const sign = (clinic.trend || 0) > 0 ? '+' : ''
              return `  Change: ${sign}${clinic.trend}% (${clinic.interpretation})`
            }
            return ''
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
    const xAxis = chart.scales.x
    const yAxis = chart.scales.y
    const xAxisY = yAxis.getPixelForValue(0)

    // ── 0. Draw Vertical Dividers between clinics ──
    ctx.save()
    ctx.strokeStyle = '#94a3b8' // Darker Slate for better definition
    ctx.lineWidth = 2.5 // Increased thickness for premium feel
    // Removed setLineDash for a solid, premium look

    // Grid lines usually draw at ticks. For separators, we draw between ticks.
    const meta0 = chart.getDatasetMeta(0)
    const dataLen = chart.data.labels.length
    for (let i = 0; i < dataLen - 1; i++) {
      const bar1 = meta0.data[i]
      const bar2 = meta0.data[i + 1]
      if (bar1 && bar2) {
        const dividerX = (bar1.x + bar2.x) / 2
        ctx.beginPath()
        ctx.moveTo(dividerX, chart.chartArea.bottom + 5)
        ctx.lineTo(dividerX, chart.chartArea.bottom + 80) // Longer to perfectly frame labels
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

        const clinic = sortedClinics.value[index]
        if (!clinic) return

        const { x, y } = bar.tooltipPosition()
        const absoluteVal = Math.abs(rawVal)
        const isPositive = rawVal >= 0

        ctx.save()

        // 1. Draw absolute visits at the tip
        ctx.font = "bold 18px 'Outfit', sans-serif"
        ctx.fillStyle = isPositive ? BLUE : ORANGE
        ctx.textAlign = 'center'
        ctx.textBaseline = isPositive ? 'bottom' : 'top'
        ctx.fillText(absoluteVal.toLocaleString(), x, isPositive ? y - 8 : y + 8)

        // 2. Trend indicators (Aligned at the top for both Growth and Decline)
        if (isCurrent && clinic.trend !== undefined) {
          const trend = clinic.trend
          const trendStr = `${trend > 0 ? '+' : ''}${trend}%`

          // Position trend labels clearly at the top
          const topSpace = 5
          const trendY = topSpace

          // Dotted line logic
          // If growth, start from bar tip (Blue).
          // If decline, user wants it over the "down bar" (Orange).
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

          // 3. Draw Trend % at the top (Centered for both Growth and Decline)
          ctx.font = "bold 20px 'Outfit', sans-serif"
          const isGrowth = trend >= 0
          ctx.fillStyle = isGrowth ? '#059669' : '#dc2626'
          ctx.textBaseline = 'top'
          ctx.textAlign = 'center'
          ctx.fillText(trendStr, trendX, trendY)

          // 4. Draw Arrows
          if (isGrowth) {
            // Green upward arrow centered on dotted line (trendX) and pointing AT the percentage
            const arrowX = trendX
            const arrowY = trendY + 28

            ctx.beginPath()
            ctx.moveTo(arrowX, arrowY + 8) // Shaft start
            ctx.lineTo(arrowX, arrowY - 8) // Shaft end / Arrow tip
            // Arrow head pointing UP
            ctx.moveTo(arrowX, arrowY - 8)
            ctx.lineTo(arrowX - 5, arrowY - 3)
            ctx.moveTo(arrowX, arrowY - 8)
            ctx.lineTo(arrowX + 5, arrowY - 3)

            ctx.strokeStyle = '#059669'
            ctx.lineWidth = 3
            ctx.stroke()
          } else {
            // Red downward arrow at 0-line for declines
            const arrowX = trendX
            const arrowY = xAxisY

            // Calculate dynamic shaft length based on the actual bar height for Previous Visits
            // This prevents striking through the visit count labels on small bars.
            const prevMeta = chart.getDatasetMeta(1)
            const prevBar = prevMeta.data[index]
            let shaftLength = 18
            if (prevBar) {
              const barHeight = Math.abs(prevBar.y - xAxisY)
              // Arrow should tip into the bar but stay above the text
              // We'll cap the shaft so it doesn't exceed a safe portion of the bar height
              shaftLength = Math.min(18, Math.max(6, barHeight * 0.4))
            }

            ctx.beginPath()
            ctx.moveTo(arrowX, arrowY)
            ctx.lineTo(arrowX, arrowY + shaftLength) // Dynamic shaft
            // Arrow head
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

    // ── 4. Draw the Continuous "Zig-Zag" Number Line ──
    ctx.save()
    ctx.strokeStyle = '#003082' // Bold Primary
    ctx.lineWidth = 3
    ctx.lineJoin = 'round'
    ctx.lineCap = 'round'
    // White glow to separate from bar colors
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
        // Find tips
        const tipC = barC.y
        const tipP = barP.y
        // Connect Peak (Current) then Trough (Previous)
        points.push({ x: barC.x, y: tipC })
        points.push({ x: barP.x, y: tipP })
      }
    })

    if (points.length > 1) {
      ctx.beginPath()
      ctx.moveTo(points[0].x, points[0].y)

      // Use Bezier curves to simulate the "Trend Line" (tension) from the other chart
      for (let i = 0; i < points.length - 1; i++) {
        const p0 = points[i]
        const p1 = points[i + 1]

        // Control points for a smooth flow
        const cp1x = p0.x + (p1.x - p0.x) * 0.5
        const cp2x = p0.x + (p1.x - p0.x) * 0.5

        ctx.bezierCurveTo(cp1x, p0.y, cp2x, p1.y, p1.x, p1.y)
      }
      ctx.stroke()

      // ── Draw Markers (Match Patient Category style) ──
      points.forEach((p) => {
        ctx.beginPath()
        ctx.arc(p.x, p.y, 5, 0, Math.PI * 2)
        ctx.fillStyle = '#003082' // pointBackgroundColor
        ctx.fill()
        ctx.strokeStyle = '#fff' // pointBorderColor
        ctx.lineWidth = 2 // pointBorderWidth
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
      style="height: 48px"
    >
      <h5 class="mb-0 fw-bold text-primary" style="font-size: 18px">
        Clinics Distribution Total visits comparison
      </h5>

      <!-- Legend (premium pill-style) -->
      <div class="chart-legend-overlay d-flex gap-3 align-items-center">
        <div class="legend-badge current-visits d-flex align-items-center gap-2">
          <span class="badge-icon">▲</span>
          <span class="badge-text text-nowrap">Current Visits (Up)</span>
        </div>
        <div class="legend-divider"></div>
        <div class="legend-badge previous-visits d-flex align-items-center gap-2">
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
      </div>

      <div style="width: 120px"></div>
    </div>

    <div class="card-body p-0 position-relative">
      <div class="chart-wrapper p-0" style="height: 650px; overflow-x: auto">
        <div
          :style="{
            width:
              sortedClinics.length > 6 ? Math.max(sortedClinics.length * 160, 900) + 'px' : '100%',
            height: '100%',
          }"
        >
          <CChart
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
}

/* ── Legend pill overlay (centred in header) ───── */
.chart-legend-overlay {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  z-index: 5;
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(8px);
  -webkit-backdrop-filter: blur(8px);
  padding: 6px 18px;
  border-radius: 30px;
  border: 1px solid rgba(226, 232, 240, 0.8);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
  white-space: nowrap;
  display: flex;
  gap: 12px;
  align-items: center;
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
.chart-wrapper::-webkit-scrollbar-track {
  background: #f8fafc;
}
.chart-wrapper::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 3px;
}

:deep(.card) {
  transition: transform 0.3s ease;
}
:deep(.card:hover) {
  transform: translateY(-2px);
}
</style>
