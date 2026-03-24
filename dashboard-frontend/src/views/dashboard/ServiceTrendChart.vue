<script setup>
import { computed, ref, watch } from 'vue'
import { CChartBar } from '@coreui/vue-chartjs'
import { useDashboardStore } from '@/stores/dashboard'
import { Chart, registerables } from 'chart.js'
import { CIcon } from '@coreui/icons-vue'
import { cilHospital, cilChart, cilBarChart } from '@coreui/icons'

Chart.register(...registerables)

const dashboard = useDashboardStore()

// Breakdown toggle state
const breakdownEnabled = ref(false)

// Watch for toggle changes to refetch data
watch(breakdownEnabled, (newVal) => {
  dashboard.setBreakdownMode(newVal)
})

// Dynamic width calculations based on breakdown mode and data points
const MIN_CHART_WIDTH = 600 // minimum chart width

// Calculate dynamic chart width based on number of labels and breakdown mode
const chartWidth = computed(() => {
  const labels = dashboard.serviceTrendData?.labels || []
  const numLabels = labels.length

  // More width per point when breakdown is enabled for clearer grouped bars
  // Increased width per point for chunkier, wider bars
  const widthPerPoint = breakdownEnabled.value ? 250 : 220
  const calculatedWidth = numLabels * widthPerPoint

  // For monthly breakdown (12 months), ensure minimum width fits all bars clearly
  if (breakdownEnabled.value && numLabels === 12) {
    return Math.max(calculatedWidth, 1600) // Increased from 1400
  }

  return Math.max(calculatedWidth, MIN_CHART_WIDTH)
})

// Determine if scrolling is needed - lower threshold for better visibility
const needsScroll = computed(() => {
  const labels = dashboard.serviceTrendData?.labels || []
  // In breakdown mode, allow checking for better visibility even with fewer items
  const threshold = 4 // lowered from 7 to ensure week view (7 items) scrolls
  return labels.length > threshold
})

const metricDetails = [
  { id: 'trend', label: 'Overall Trend', color: '#1e293b' }, // Dark Slate (Matches line color)
  { id: 'opd', label: 'Total OPD', color: '#3b82f6' }, // Blue
  { id: 'emergency', label: 'Emergency', color: '#dc3545' }, // Red
  { id: 'consulted', label: 'Consulted', color: '#f97316' }, // Orange
  { id: 'not_consulted', label: 'Not Consulted', color: '#fbbf24' }, // Amber/Yellow
  { id: 'new', label: 'New Visits', color: '#06b6d4' }, // Cyan/Teal - distinct from blue
  { id: 'followup', label: 'Follow-ups', color: '#6610f2' }, // Purple
]

// Referral map for known codes that might be missing names
const keyReferralMap = {
  '000037': 'SELF REFERRAL',
}

// Referral stats computed - sorted by count descending
const referralData = computed(() => {
  const stats = dashboard.referralStats || []
  return stats.map((item) => {
    // If name is missing or generic, try to map it
    let name = item.name
    if ((!name || name.toLowerCase().includes('facility')) && keyReferralMap[item.code]) {
      name = keyReferralMap[item.code]
    }
    return { ...item, name }
  })
})

const totalReferrals = computed(() => {
  return referralData.value.reduce((sum, item) => sum + (item.count || 0), 0)
})

// Custom plugin to draw labels on top of bars
const barLabelsPlugin = {
  id: 'barLabels',
  afterDatasetsDraw(chart) {
    const { ctx, scales } = chart
    const numLabels = chart.data.labels?.length || 1
    const isBreakdownMode = breakdownEnabled.value

    chart.data.datasets.forEach((dataset, datasetIndex) => {
      const meta = chart.getDatasetMeta(datasetIndex)
      const color = metricDetails[datasetIndex]?.color || '#333'

      meta.data.forEach((bar, index) => {
        const value = dataset.data[index]
        const { x, y } = bar.tooltipPosition()

        // Format value - show 0 if null/undefined/0
        const displayValue =
          value === null || value === undefined || value === 0
            ? '0'
            : new Intl.NumberFormat('en-US').format(value)

        ctx.save()

        // SPECIAL HANDLING FOR TREND LINE (Index 0)
        if (datasetIndex === 0) {
          // Draw "Flag" style label: Vertical line up to the TOP of the chart area
          // This places values "out of the last top horizontal line" as requested

          // Calculate top position (outside the main grid area)
          const topSpace = 15 // Distance from very top of canvas
          const labelY = topSpace

          // Draw vertical "dotted/broken dots" line
          ctx.beginPath()
          ctx.moveTo(x, y) // From data point
          // Extend up to just below the label (adjusted for 20px font)
          ctx.lineTo(x, labelY + 28)
          ctx.strokeStyle = '#94a3b8' // Slate-400 for better visibility
          ctx.lineWidth = 1.5
          ctx.setLineDash([2, 4]) // "Broken dots" style (Short dash, longer gap)
          ctx.stroke()
          ctx.setLineDash([]) // Reset dash

          // Draw Text at the very top (Larger Font: 20px)
          ctx.font = `bold 20px 'Outfit', sans-serif`
          ctx.fillStyle = color // Keep text dark/slate
          ctx.textAlign = 'center'
          ctx.textBaseline = 'top' // Draw from top down

          // EXTRA GLOW for top labels
          ctx.shadowColor = 'white'
          ctx.shadowBlur = 4
          ctx.fillText(displayValue, x, labelY)
        } else if (datasetIndex === 1) {
          // SKIP label for "Total OPD" (Index 1)
          // It is redundant with the Trend Line (Index 0) and causes overlap
          return
        } else {
          // STANDARD BAR LABEL DRAWING
          // For zero values, position label at the bottom (x-axis)
          const yPos =
            value === null || value === undefined || value === 0 ? scales.y.bottom - 8 : y - 5

          // Adjust font size - INCREASED sizes
          // Default: 16px. Breakdown mode dense: 13px minimum
          const fontSize = isBreakdownMode ? (numLabels > 8 ? 13 : numLabels > 4 ? 14 : 16) : 16
          ctx.font = `bold ${fontSize}px 'Outfit', sans-serif`
          ctx.fillStyle = color
          ctx.textAlign = 'center'
          ctx.textBaseline = 'bottom'

          // Enhanced white shadow/glow for maximum legibility on colored bars
          ctx.shadowColor = 'white'
          ctx.shadowBlur = 5
          ctx.fillText(displayValue, x, yPos + 2) // Shifted down 2px to be closer to bar
        }
        ctx.restore()
      })
    })
  },
}

const chartData = computed(() => {
  const rawData = dashboard.serviceTrendData || { labels: [], datasets: [] }

  if (!rawData.datasets) return rawData

  // Adjust bar sizing based on breakdown mode and number of data points
  const numLabels = rawData.labels?.length || 1

  // Dynamic bar sizing for monthly breakdown
  let barPercentage, categoryPercentage
  if (breakdownEnabled.value) {
    // In breakdown mode: Wider bars, smaller gaps
    barPercentage = 0.95 // Bars take 95% of their allocated space (almost touching)
    categoryPercentage = 0.8 // Month group takes 80% of slot width (smaller gaps)
  } else {
    // Default mode: Significantly chunkier bars
    barPercentage = 0.95 // Bars take 95% of their group (thick)
    categoryPercentage = 0.8 // Day group takes 80% of slot (less white space)
  }

  return {
    ...rawData,
    datasets: rawData.datasets.map((ds, index) => {
      const color = metricDetails[index]?.color || ds.backgroundColor
      return {
        ...ds,
        backgroundColor: color,
        borderRadius: breakdownEnabled.value ? 3 : 4,
        borderSkipped: false,
        barPercentage,
        categoryPercentage,
      }
    }),
  }
})

const maxDataValue = computed(() => {
  let max = 0

  // From chart data (current bars)
  if (dashboard.serviceTrendData && dashboard.serviceTrendData.datasets) {
    dashboard.serviceTrendData.datasets.forEach((ds) => {
      const dMax = Math.max(...(Array.isArray(ds.data) ? ds.data : [0]))
      if (dMax > max) max = dMax
    })
  }

  // ONLY use card data when NOT in breakdown mode AND we have only 1 label
  // If we have multiple labels (e.g. 7 days or 12 months), we are showing a distribution,
  // so we should scale to the distribution peaks, not the total sum.
  const numLabels = dashboard.serviceTrendData?.labels?.length || 0

  if (!breakdownEnabled.value && dashboard.realStats && numLabels <= 1) {
    const rs = dashboard.realStats
    const cardValues = [
      rs.total_patients || 0,
      rs.emergency_patients || 0,
      rs.consulted || 0,
      rs.pending || 0,
      rs.new_visits || 0,
      rs.followups || 0,
    ]
    const cardMax = Math.max(...cardValues)
    if (cardMax > max) max = cardMax
  }

  // Add headroom for labels above the bars
  return max > 0 ? Math.ceil(max * 1.15) : 10
})

const chartOptions = computed(() => {
  const numLabels = dashboard.serviceTrendData?.labels?.length || 1
  const isBreakdownMode = breakdownEnabled.value

  // Dynamic X-axis font size based on breakdown mode and number of labels
  // Dynamic X-axis font size and rotation
  let xFontSize = 20
  let xRotation = 0

  if (numLabels > 1) {
    // Enforce diagonal format for multi-data views (Week, Breakdown, etc)
    xRotation = 45
    if (numLabels > 10) {
      xFontSize = 12
    } else if (numLabels > 6) {
      xFontSize = 14
    } else {
      xFontSize = 15
    }
  }

  return {
    layout: {
      padding: { top: 60, bottom: isBreakdownMode ? 20 : 0, left: 10, right: 10 },
    },
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: 'rgba(255, 255, 255, 0.95)',
        titleColor: '#1e293b',
        bodyColor: '#475569',
        borderColor: '#e2e8f0',
        borderWidth: 1,
        padding: 14,
        boxPadding: 6,
        usePointStyle: true,
        callbacks: {
          // Enhanced tooltip for breakdown mode
          label: (context) => {
            const label = context.dataset.label || ''
            const value = context.raw || 0
            return `${label}: ${value.toLocaleString()}`
          },
        },
      },
    },
    responsive: true,
    maintainAspectRatio: false,
    scales: {
      x: {
        grid: { display: false },
        ticks: {
          color: '#1e293b',
          font: { size: xFontSize, weight: '700', family: "'Outfit', sans-serif" },
          maxRotation: xRotation,
          minRotation: xRotation,
          padding: 12,
        },
      },
      y: {
        beginAtZero: true,
        max: maxDataValue.value,
        title: isBreakdownMode
          ? {
              display: true,
              text: 'Patients',
              font: { size: 12, weight: '600' },
              color: '#64748b',
            }
          : { display: false },
        grid: {
          color: 'rgba(203, 213, 225, 0.4)',
          drawBorder: false,
        },
        ticks: {
          color: '#94a3b8',
          font: { size: 12, weight: '600' },
          maxTicksLimit: 10,
          callback: (value) => value.toLocaleString(),
        },
      },
    },
  }
})
</script>

<template>
  <CCard class="mt-0 mb-4 service-trends-card border-0 shadow-lg overflow-hidden">
    <div class="glass-header px-4 py-1">
      <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
          <h5 class="header-title mb-0 fw-bold text-primary" style="font-size: 16px">
            Service Trends Analysis
          </h5>
        </div>
        <div class="d-flex align-items-center gap-3">
          <!-- Referral Summary in Header -->
          <div
            class="premium-stat-pill d-flex align-items-center shadow-sm border-primary-subtle bg-white"
            style="scale: 0.9; margin: -5px 0"
          >
            <span
              class="pill-label bg-primary text-white border-0"
              style="padding: 4px 8px; font-size: 11px"
              >Total Referrals</span
            >
            <span class="pill-value text-primary fs-6 px-3">{{
              totalReferrals.toLocaleString()
            }}</span>
          </div>
          <CBadge
            color="primary"
            shape="rounded-pill"
            class="facilities-badge shadow-sm"
            style="font-size: 11px; padding: 4px 12px"
          >
            {{ referralData.length }} Facilities
          </CBadge>
        </div>
      </div>
    </div>

    <CCardBody class="p-0 border-0">
      <CRow class="m-0 mx-0">
        <!-- Left Side: Bar Chart + Legend (Stacked on LG, Side-by-Side on XL) -->
        <CCol :xl="7" :lg="12" class="p-0 border-end bg-white">
          <!-- Chart Legend Moved to Left Column (Tightened Padding) -->
          <div class="px-4 py-2 bg-light-subtle border-bottom">
            <div
              class="custom-legend d-flex flex-wrap align-items-center gap-3 mb-1 p-2 bg-white rounded-4 shadow-sm border"
            >
              <div
                v-for="metric in metricDetails"
                :key="metric.id"
                class="legend-item d-flex align-items-center gap-2"
              >
                <span
                  class="legend-dot"
                  :style="{ backgroundColor: metric.color, width: '12px', height: '12px' }"
                ></span>
                <span class="legend-label fw-bold" style="font-size: 14px; color: #334155">{{
                  metric.label
                }}</span>
              </div>

              <!-- Breakdown Toggle Button -->
              <div class="ms-auto">
                <button
                  type="button"
                  class="breakdown-pill-btn d-flex align-items-center"
                  :class="{ active: breakdownEnabled }"
                  @click="breakdownEnabled = !breakdownEnabled"
                  :title="breakdownEnabled ? 'Switch to default view' : 'Show monthly breakdown'"
                >
                  <span class="pill-left">
                    <CIcon :icon="cilBarChart" size="sm" class="me-1" />
                    Monthly Breakdown
                  </span>
                  <span class="pill-right" :class="{ on: breakdownEnabled }">
                    {{ breakdownEnabled ? 'ON' : 'OFF' }}
                  </span>
                </button>
              </div>
            </div>
          </div>

          <!-- Subtle top-right sync indicator for the card -->
          <div v-if="dashboard.isSyncing" class="sync-indicator-mini" title="Background syncing in progress...">
            <div class="spinner-border spinner-border-sm text-primary" style="width: 0.8rem; height: 0.8rem;"></div>
          </div>

          <div
            v-if="dashboard.isTrendsLoading && (!chartData.labels || chartData.labels.length === 0)"
            class="empty-state d-flex flex-column align-items-center justify-content-center py-5"
            :style="{ height: breakdownEnabled ? '560px' : '460px' }"
          >
            <div class="spinner-border text-primary opacity-25" role="status"></div>
            <p class="mt-3 text-muted small text-uppercase fw-bold letter-spacing-1">
              Loading Trends...
            </p>
          </div>

          <div
            v-else-if="(!chartData.labels || chartData.labels.length === 0) && !dashboard.isTrendsLoading"
            class="empty-state d-flex flex-column align-items-center justify-content-center py-5 text-center px-4"
            :style="{ height: breakdownEnabled ? '560px' : '460px' }"
          >
            <p class="text-muted fw-bold mb-1 small">No trend data available.</p>
          </div>

          <div
            v-else
            class="chart-container p-3 pt-3"
            :style="{ height: breakdownEnabled ? '600px' : '500px' }"
          >
            <div
              class="chart-scroll-wrapper"
              :class="{ 'has-scroll': needsScroll, 'breakdown-mode': breakdownEnabled }"
            >
              <div
                class="chart-inner"
                :style="{
                  width: needsScroll ? chartWidth + 'px' : '100%',
                  height: '100%',
                }"
              >
                <CChartBar
                  :key="breakdownEnabled ? 'breakdown' : 'default'"
                  :data="chartData"
                  :options="{
                    ...chartOptions,
                    maintainAspectRatio: false,
                    plugins: {
                      ...chartOptions.plugins,
                      legend: { display: false },
                    },
                  }"
                  :plugins="[barLabelsPlugin]"
                  style="height: 100%; width: 100%"
                />
              </div>
            </div>
            <div v-if="needsScroll" class="scroll-hint text-muted small mt-2 text-center">
              <i class="cil-swap-horizontal me-1"></i>
              Scroll horizontally to see all {{ chartData.labels?.length }}
              {{ breakdownEnabled ? 'months' : 'data points' }}
            </div>
          </div>
        </CCol>

        <!-- Right Side: Referral Distribution (Stacked on LG, Side-by-Side on XL) -->
        <CCol :xl="5" :lg="12" class="p-0 bg-light-subtle">
          <div
            class="referral-container"
            :style="{ height: breakdownEnabled ? '660px' : '580px', overflowY: 'auto' }"
          >
            <div class="px-1 py-4">
              <div class="mb-4">
                <h6 class="fw-bold text-dark-emphasis mb-0 d-flex align-items-center">
                  <CIcon :icon="cilHospital" class="me-2 text-primary" />
                  Referral Distribution
                </h6>
              </div>

              <div
                v-if="referralData.length > 0"
                class="referral-table-wrapper rounded-3 border shadow-sm bg-white table-responsive"
              >
                <table class="table table-hover align-middle mb-0 referral-table">
                  <thead class="table-light">
                    <tr>
                      <th class="ps-3 py-3 text-uppercase" style="font-size: 11px; width: 70px">
                        Code
                      </th>
                      <th class="py-3 text-uppercase" style="font-size: 11px">Hospital Name</th>
                      <th
                        class="py-3 text-uppercase"
                        style="font-size: 11px; width: 60px; text-align: center"
                      >
                        %
                      </th>
                      <th
                        class="text-end pe-3 py-3 text-uppercase"
                        style="font-size: 11px; width: 70px"
                      >
                        QTY
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white">
                    <tr v-for="(hosp, index) in referralData" :key="hosp.code" class="referral-row">
                      <td class="ps-3 py-3">
                        <code
                          class="bg-primary-subtle px-2 py-1 rounded text-primary fw-bold"
                          style="font-size: 11px"
                        >
                          {{ hosp.code || '—' }}
                        </code>
                      </td>
                      <td class="py-3">
                        <span
                          v-if="hosp.name && hosp.name.trim()"
                          class="fw-bold text-dark text-truncate d-inline-block"
                          style="font-size: 13px; max-width: 155px"
                          :title="hosp.name"
                        >
                          {{ hosp.name }}
                        </span>
                        <span
                          v-else
                          class="facility-code-name fw-semibold text-truncate d-inline-block"
                          style="font-size: 12px; max-width: 180px"
                          :title="'Facility ' + hosp.code"
                        >
                          Facility {{ hosp.code }}
                        </span>
                      </td>
                      <td
                        style="
                          text-align: center;
                          vertical-align: middle;
                          padding-top: 10px;
                          padding-bottom: 10px;
                        "
                      >
                        <span class="fw-bold" style="font-size: 16px; color: #4f46e5">
                          {{
                            totalReferrals > 0
                              ? (((hosp.count || 0) / totalReferrals) * 100).toFixed(1) + '%'
                              : '0%'
                          }}
                        </span>
                      </td>
                      <td class="text-end pe-3 py-3">
                        <div class="d-flex flex-column align-items-end gap-1">
                          <span class="fw-bold text-primary" style="font-size: 15px">{{
                            (hosp.count || 0).toLocaleString()
                          }}</span>
                          <div
                            class="progress shadow-sm"
                            style="width: 60px; height: 5px; border-radius: 4px"
                          >
                            <div
                              class="progress-bar bg-primary"
                              role="progressbar"
                              :style="{
                                width: (hosp.count / (referralData[0]?.count || 1)) * 100 + '%',
                              }"
                            ></div>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div
                v-else
                class="d-flex flex-column align-items-center justify-content-center py-5 opacity-50"
              >
                <CIcon icon="cil-hospital" size="xl" class="mb-2" />
                <p class="small">No referral data found</p>
              </div>
            </div>
          </div>
        </CCol>
      </CRow>
    </CCardBody>
  </CCard>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap');

.service-trends-card {
  border-radius: 24px;
  background: white;
  position: relative;
  transition:
    transform 0.3s ease,
    box-shadow 0.3s ease;
}

.service-trends-card:hover {
  box-shadow: 0 20px 40px rgba(0, 50, 150, 0.08) !important;
}

.sync-indicator-mini {
  position: absolute;
  top: 15px;
  right: 20px;
  z-index: 5;
  background: white;
  padding: 6px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 10px rgba(0, 48, 130, 0.1);
  border: 1px solid rgba(0, 48, 130, 0.05);
}

.glass-header {
  background: rgba(255, 255, 255, 0.4);
  backdrop-filter: blur(8px);
  border-bottom: 1px solid rgba(203, 213, 225, 0.3);
  z-index: 2;
}

.referral-container::-webkit-scrollbar {
  width: 6px;
}

.referral-container::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.05);
  border-radius: 10px;
}

.referral-container:hover::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.1);
}

.referral-table thead th {
  font-size: 11px;
  text-transform: uppercase;
  color: #64748b;
  font-weight: 700;
  letter-spacing: 0.5px;
  padding-top: 15px;
  padding-bottom: 15px;
  border-bottom: 2px solid #f1f5f9;
}

.referral-table tbody tr {
  transition: background 0.2s ease;
  border-bottom: 1px solid #f8fafc;
}

.referral-table tbody tr:last-child {
  border-bottom: none;
}

.referral-table td {
  padding-top: 10px;
  padding-bottom: 10px;
  font-size: 12px;
}

.referral-row {
  transition: all 0.2s ease;
}

.referral-row:hover {
  background-color: rgba(59, 130, 246, 0.05) !important;
}

.top-referral {
  background-color: rgba(59, 130, 246, 0.02);
}

.rank-badge {
  width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 10px;
  font-weight: 700;
  color: white;
  flex-shrink: 0;
}

.bg-orange {
  background-color: #cd7f32 !important;
}

.fw-600 {
  font-weight: 600;
}

.progress {
  background-color: #f1f5f9;
  border-radius: 10px;
  overflow: hidden;
}

.header-title {
  font-family: 'Outfit', sans-serif;
  letter-spacing: -0.5px;
  color: #0f172a !important;
}

.header-subtitle {
  font-family: 'Outfit', sans-serif;
  font-weight: 500;
}

/* Legend Styles */
.legend-item {
  cursor: default;
  transition: opacity 0.2s;
}

.legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  display: inline-block;
}

.legend-label {
  font-size: 11px;
  font-weight: 700;
  color: #64748b;
  font-family: 'Outfit', sans-serif;
  white-space: nowrap;
}

/* Scrolling Styles */
.scroll-wrapper {
  overflow-x: auto;
  overflow-y: visible;
  padding-bottom: 20px;
  padding-top: 40px;
}

.scroll-wrapper::-webkit-scrollbar {
  height: 6px;
}

.scroll-wrapper::-webkit-scrollbar-track {
  background: #e2e8f0;
  border-radius: 10px;
}

.scroll-wrapper::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 10px;
}

.scroll-wrapper::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

.empty-state {
  min-height: 300px;
  background: rgba(248, 250, 252, 0.5);
  border-radius: 20px;
  border: 1px dashed rgba(203, 213, 225, 0.8);
}

.chart-viewport {
  transition: width 0.4s ease;
}

/* Horizontal Scroll for Chart */
.chart-scroll-wrapper {
  width: 100%;
  height: 100%;
}

.chart-scroll-wrapper.has-scroll {
  overflow-x: auto;
  overflow-y: hidden;
  padding-bottom: 12px;
}

.chart-scroll-wrapper.has-scroll::-webkit-scrollbar {
  height: 8px;
}

.chart-scroll-wrapper.has-scroll::-webkit-scrollbar-track {
  background: #f1f5f9;
  border-radius: 10px;
}

.chart-scroll-wrapper.has-scroll::-webkit-scrollbar-thumb {
  background: linear-gradient(135deg, #3b82f6, #6366f1);
  border-radius: 10px;
}

.chart-scroll-wrapper.has-scroll::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(135deg, #2563eb, #4f46e5);
}

/* Breakdown mode specific styles */
.chart-scroll-wrapper.breakdown-mode {
  transition: all 0.3s ease;
}

.chart-scroll-wrapper.breakdown-mode.has-scroll {
  padding-bottom: 16px;
}

.chart-scroll-wrapper.breakdown-mode::-webkit-scrollbar {
  height: 10px;
}

.chart-scroll-wrapper.breakdown-mode::-webkit-scrollbar-thumb {
  background: linear-gradient(135deg, #10b981, #059669);
}

.chart-inner {
  min-width: 100%;
  transition: all 0.3s ease;
}

.scroll-hint {
  color: #94a3b8;
  font-size: 11px;
  font-weight: 600;
}

/* Chart container transition */
.chart-container {
  transition: height 0.3s ease;
}

.facility-code-name {
  color: #64748b;
  font-style: italic;
}

/* Breakdown Toggle Button - Pill Style */
.breakdown-pill-btn {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 25px;
  padding: 0;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  font-family: 'Outfit', sans-serif;
  overflow: hidden;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
  height: 34px;
}

.breakdown-pill-btn:hover {
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
  border-color: #93c5fd;
}

.breakdown-pill-btn .pill-left {
  background: linear-gradient(135deg, #4f46e5, #3b82f6);
  color: white;
  padding: 0 14px;
  height: 100%;
  display: flex;
  align-items: center;
  font-weight: 700;
  font-size: 11px;
  text-transform: uppercase;
  letter-spacing: 0.3px;
  white-space: nowrap;
}

.breakdown-pill-btn .pill-right {
  background: white;
  color: #64748b;
  padding: 0 14px;
  height: 100%;
  display: flex;
  align-items: center;
  font-weight: 800;
  font-size: 13px;
  transition: all 0.3s ease;
}

.breakdown-pill-btn .pill-right.on {
  color: #10b981;
  background: linear-gradient(135deg, #ecfdf5, #d1fae5);
}

.breakdown-pill-btn.active {
  border-color: #10b981;
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
}

.breakdown-pill-btn.active .pill-left {
  background: linear-gradient(135deg, #059669, #10b981);
}

/* Premium Stat Pill */
.premium-stat-pill {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 20px;
  overflow: hidden;
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
  font-family: 'Outfit', sans-serif;
  height: 32px; /* Increased from 24px */
}

.pill-label {
  background: #f8fafc;
  color: #64748b;
  font-size: 11px; /* Increased from 9px */
  font-weight: 700;
  padding: 0 12px;
  text-transform: uppercase;
  height: 100%;
  display: flex;
  align-items: center;
  border-right: 1px solid #e2e8f0;
}

.pill-value {
  color: #2563eb;
  font-size: 16px; /* Increased from 11px */
  font-weight: 800;
  padding: 0 14px;
  height: 100%;
  display: flex;
  align-items: center;
}

.facilities-badge {
  font-size: 13px !important; /* Increased from 10px */
  padding: 6px 12px !important;
  font-weight: 700 !important;
}

.rank-badge {
  width: 18px;
  height: 18px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 50%;
  font-size: 10px;
  font-weight: 800;
  color: white;
}
</style>
