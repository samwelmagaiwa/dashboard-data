<script setup>
import { defineAsyncComponent, computed, ref, onMounted, watch, onUnmounted } from 'vue'
import { useDashboardStore } from '@/stores/dashboard'
import { getAutoScrollState } from '@/composables/useAutoScroll'
import LoadingBanner from '@/components/LoadingBanner.vue'
import { CIcon } from '@coreui/icons-vue'
import {
  cilPeople,
  cilMoney,
  cilCreditCard,
  cilGlobeAlt,
  cilBriefcase,
  cilInstitution,
  cilChevronBottom,
  cilChevronTop,
  cilSearch,
} from '@coreui/icons'
import { ChartLine, ChartBar } from '../charts/index.js'
import { CChart, CChartPie } from '@coreui/vue-chartjs'
import VueDatePicker from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'

import DashboardPieCharts from './DashboardPieCharts.vue'
import WidgetsStatsD from '../widgets/SocialStatsWidgets.vue'
import DashboardRadarChart from './DashboardRadarChart.vue'
import DashboardClinicBarChart from './DashboardClinicBarChart.vue'
import ServiceTrendChart from './ServiceTrendChart.vue'

const MainChart = defineAsyncComponent(() => import('./MainChart.vue'))

const dashboard = useDashboardStore()
const autoScroll = getAutoScrollState()
const isAutoScrollEnabled = computed(() => autoScroll.isEnabled.value)
const hiddenPieCategories = ref([]) // Track hidden categories for pie chart
const showOutageSlideshow = computed(() => dashboard.remoteApiAvailable === false)
const outageSlides = ['/outage-slides/slide-1.jpg', '/outage-slides/slide-2.jpg', '/outage-slides/slide-3.jpg']
const activeOutageSlide = ref(0)

let syncInterval = null
let outageSlideInterval = null

const startOutageSlideshow = () => {
  if (outageSlideInterval) return

  outageSlideInterval = setInterval(() => {
    activeOutageSlide.value = (activeOutageSlide.value + 1) % outageSlides.length
  }, 5000)
}

const stopOutageSlideshow = () => {
  if (outageSlideInterval) {
    clearInterval(outageSlideInterval)
    outageSlideInterval = null
  }

  activeOutageSlide.value = 0
}

onMounted(() => {
  dashboard.fetchStats()

  // Trigger background sync every 5 minutes for admin users
  syncInterval = setInterval(async () => {
    if (dashboard.isAuthenticated) {
      await dashboard.triggerSync()
    }
  }, 300000) // 5 minutes
})

watch(
  showOutageSlideshow,
  (isActive) => {
    if (isActive) {
      startOutageSlideshow()
      return
    }

    stopOutageSlideshow()
  },
  { immediate: true },
)

onUnmounted(() => {
  dashboard.stopPulse() // Cleanup polling
  if (syncInterval) clearInterval(syncInterval)
  stopOutageSlideshow()
})

const getCategoryColor = (title) => {
  const map = {
    PUBLIC: 'info',
    NHIF: 'success',
    'IPPM - PRIVATE': 'primary',
    'IPPM - CREDIT': 'warning',
    'COST SHARING': 'danger',
    NSSF: 'dark',
    FOREIGNER: 'indigo',
  }
  return map[title] || 'primary'
}

const getCategoryIcon = (title) => {
  const map = {
    PUBLIC: cilPeople,
    NHIF: cilCreditCard,
    'IPPM - PRIVATE': cilBriefcase,
    'IPPM - CREDIT': cilMoney,
    'COST SHARING': cilMoney,
    NSSF: cilInstitution,
    FOREIGNER: cilGlobeAlt,
  }
  return map[title] || cilPeople
}

const patientCategories = computed(() => {
  const colors = {
    PUBLIC: '#007bff',      // Blue
    NHIF: '#28a745',       // Green
    'IPPM - PRIVATE': '#6f42c1', // Purple (distinct from all others)
    'IPPM - CREDIT': '#ffc107',  // Amber
    'COST SHARING': '#E12AFB',   // Magenta (user specified)
    NSSF: '#17a2b8',       // Teal
    FOREIGNER: '#e83e8c',  // Pink
  }

  // Get all categories with their values
  const catTitles = [
    'PUBLIC',
    'NHIF',
    'IPPM - PRIVATE',
    'IPPM - CREDIT',
    'COST SHARING',
    'NSSF',
    'FOREIGNER',
  ]

  const cats = catTitles.map((title) => {
    const metric = dashboard.metrics.find((m) => m.title === title)
    return {
      title,
      value: metric ? metric.value : '0',
      color: colors[title] || '#6c757d',
      numericValue: parseInt((metric?.value || '0').replace(/,/g, '')) || 0,
    }
  })

  // Sort by numeric value descending, but keep NHIF first and FOREIGNER last
  const sortedCats = cats.sort((a, b) => {
    // Always keep NHIF first
    if (a.title === 'NHIF') return -1
    if (b.title === 'NHIF') return 1
    // Always keep FOREIGNER last
    if (a.title === 'FOREIGNER') return 1
    if (b.title === 'FOREIGNER') return -1
    // Sort others by value descending
    return b.numericValue - a.numericValue
  })

  // Calculate sum for the ribbon Total
  const totalSum = sortedCats.reduce((acc, cat) => acc + cat.numericValue, 0)

  return [
    ...sortedCats,
    {
      title: 'Total',
      value: totalSum.toLocaleString(),
      color: 'grey',
    },
  ]
})

// Patient Category Chart Data (Bar + Line combination)
const categoryChartData = computed(() => {
  const categories = patientCategories.value.filter((c) => c.title !== 'Total')
  const values = categories.map((c) => parseInt(c.value.replace(/,/g, '')) || 0)
  const colors = categories.map((c) => c.color)

  return {
    labels: categories.map((c) => c.title),
    datasets: [
      {
        type: 'bar',
        label: 'Patient Count',
        backgroundColor: colors,
        borderColor: colors.map((c) => c),
        borderWidth: 1,
        data: values,
        order: 2,
        barPercentage: 0.7,
        categoryPercentage: 0.8,
        borderRadius: 4,
      },
      {
        type: 'line',
        label: 'Trend Line',
        borderColor: '#003082',
        backgroundColor: 'rgba(0, 48, 130, 0.1)',
        borderWidth: 2,
        fill: false,
        tension: 0.4,
        data: values,
        order: 1,
        pointBackgroundColor: '#003082',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointRadius: 5,
        pointHoverRadius: 7,
      },
    ],
  }
})

const categoryChartOptions = computed(() => {
  const categories = patientCategories.value.filter((c) => c.title !== 'Total')
  const values = categories.map((c) => parseInt(c.value.replace(/,/g, '')) || 0)
  const maxValue = Math.max(...values, 1)
  const yMax =
    maxValue > 1000
      ? Math.ceil(maxValue / 1000) * 1000 * 1.5
      : maxValue > 100
        ? Math.ceil(maxValue / 100) * 100 * 1.5
        : Math.ceil(maxValue / 10) * 10 * 1.5

  return {
    responsive: true,
    maintainAspectRatio: false,
    layout: {
      padding: { top: 5 },
    },
    plugins: {
      legend: {
        display: true,
        position: 'top',
        labels: {
          usePointStyle: true,
          padding: 15,
          font: { size: 11, weight: '600' },
        },
      },
      tooltip: {
        callbacks: {
          label: (context) => {
            const value = context.raw || 0
            return `${context.dataset.label}: ${value.toLocaleString()}`
          },
        },
      },
    },
    scales: {
      x: {
        grid: { display: false },
        ticks: {
          font: { size: 10, weight: 'bold' },
          color: '#333',
          maxRotation: 45,
          minRotation: 45,
        },
      },
      y: {
        type: 'logarithmic',
        min: 1,
        max: yMax,
        title: {
          display: true,
          text: 'Total Patients (Log Scale)',
          font: { size: 12, weight: 'bold' },
          color: '#333',
        },
        grid: {
          borderDash: [2, 2],
          color: 'rgba(0, 0, 0, 0.1)',
        },
        ticks: {
          callback: (value) => {
            const remain = value / Math.pow(10, Math.floor(Math.log10(value)))
            if (remain === 1 || remain === 2 || remain === 5) {
              return value.toLocaleString()
            }
            return ''
          },
        },
      },
    },
  }
})

// Plugin to display values on bars
const categoryBarLabelsPlugin = {
  id: 'categoryBarLabels',
  afterDatasetsDraw(chart) {
    const { ctx } = chart
    const meta = chart.getDatasetMeta(0)
    if (meta.type !== 'bar') return

    meta.data.forEach((bar, index) => {
      const value = chart.data.datasets[0].data[index]
      if (value === 0) return

      const { x, y } = bar.tooltipPosition()
      const displayValue = new Intl.NumberFormat('en-US').format(value)
      const barColor = chart.data.datasets[0].backgroundColor[index]

      ctx.save()
      ctx.font = "bold 26px 'Outfit', Arial, sans-serif"
      ctx.fillStyle = barColor
      ctx.textAlign = 'center'
      ctx.textBaseline = 'bottom'
      ctx.shadowColor = 'rgba(255, 255, 255, 0.8)'
      ctx.shadowBlur = 4
      ctx.fillText(displayValue, x, y - 6)
      ctx.restore()
    })
  },
}

// Patient Category Pie Chart Data
const categoryPieChartData = computed(() => {
  const categories = patientCategories.value.filter((c) => c.title !== 'Total')
  const values = categories.map((c) => {
    if (hiddenPieCategories.value.includes(c.title)) return 0
    return parseInt(c.value.replace(/,/g, '')) || 0
  })
  const colors = categories.map((c) => c.color)

  return {
    labels: categories.map((c) => c.title),
    datasets: [
      {
        backgroundColor: colors,
        data: values,
        borderWidth: 2,
        borderColor: '#fff',
        radius: '95%',
        cutout: 0,
      },
    ],
  }
})

// Toggle category visibility in pie chart
const togglePieCategory = (categoryTitle) => {
  if (hiddenPieCategories.value.includes(categoryTitle)) {
    hiddenPieCategories.value = hiddenPieCategories.value.filter((c) => c !== categoryTitle)
  } else {
    hiddenPieCategories.value.push(categoryTitle)
  }
}

// Check if category is hidden
const isCategoryHidden = (categoryTitle) => {
  return hiddenPieCategories.value.includes(categoryTitle)
}

const categoryPieChartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  layout: {
    padding: 10,
  },
  plugins: {
    legend: {
      display: false,
    },
    tooltip: {
      callbacks: {
        label: (context) => {
          const label = context.label || ''
          const value = context.raw || 0
          const total = context.dataset.data.reduce((a, b) => a + b, 0)
          const percentage = total > 0 ? Math.round((value / total) * 100) : 0
          return `${label}: ${value.toLocaleString()} (${percentage}%)`
        },
      },
    },
  },
}

// Plugin to draw labels
const categoryPieLabelsPlugin = {
  id: 'categoryPieLabels',
  afterDatasetsDraw(chart) {
    const { ctx } = chart
    const meta = chart.getDatasetMeta(0)

    ctx.save()
    meta.data.forEach((element, index) => {
      if (element.hidden) return
      const value = chart.data.datasets[0].data[index]
      if (!value || value === 0) return

      const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0)
      const percentageVal = total > 0 ? (value / total) * 100 : 0
      const percentage = percentageVal.toFixed(1) + '%'
      const midAngle = element.startAngle + (element.endAngle - element.startAngle) / 2
      const angleSpan = ((element.endAngle - element.startAngle) * 180) / Math.PI
      const sliceColor = chart.data.datasets[0].backgroundColor[index]

      if (angleSpan >= 15) {
        const midRadius = element.outerRadius * 0.6 + element.innerRadius * 0.1
        const x = Math.cos(midAngle) * midRadius + element.x
        const y = Math.sin(midAngle) * midRadius + element.y
        ctx.fillStyle = '#fff'
        ctx.textAlign = 'center'
        ctx.textBaseline = 'middle'
        ctx.shadowColor = 'rgba(0,0,0,0.9)'
        ctx.shadowBlur = 5
        ctx.font = "bold 22px 'Outfit', sans-serif"
        ctx.fillText(value.toLocaleString(), x, y - 10)
        ctx.font = "normal 14px 'Outfit', sans-serif"
        ctx.fillText(percentage, x, y + 14)
      } else {
        const r = element.outerRadius
        const cx = element.x
        const cy = element.y
        const x1 = Math.cos(midAngle) * (r * 0.99) + cx
        const y1 = Math.sin(midAngle) * (r * 0.99) + cy
        const x2 = Math.cos(midAngle) * (r * 1.03) + cx
        const y2 = Math.sin(midAngle) * (r * 1.03) + cy
        const isRight = x2 > cx
        const x3 = x2 + (isRight ? 8 : -8)
        const y3 = y2
        ctx.shadowBlur = 0
        ctx.strokeStyle = sliceColor
        ctx.lineWidth = 2
        ctx.beginPath()
        ctx.moveTo(x1, y1)
        ctx.lineTo(x2, y2)
        ctx.lineTo(x3, y3)
        ctx.stroke()
        ctx.fillStyle = sliceColor
        ctx.textAlign = isRight ? 'left' : 'right'
        ctx.textBaseline = 'middle'
        ctx.font = "bold 13px 'Outfit', sans-serif"
        const labelX = x3 + (isRight ? 3 : -3)
        ctx.fillText(`${value.toLocaleString()} (${percentage})`, labelX, y3)
      }
    })
    ctx.restore()
  },
}

const formatDate = (dateStr) => {
  if (!dateStr) return 'Date is empty'
  if (typeof dateStr === 'string' && dateStr.includes('T')) {
    return dateStr.split('T')[0]
  }
  return dateStr
}
</script>

<template>
  <div
    class="dashboard-grid px-0 pt-0 pb-3"
    style="position: relative; min-height: 400px; overflow-x: hidden"
  >
    <div v-if="showOutageSlideshow" class="outage-slideshow-shell">
      <div
        class="outage-slideshow-track"
        :style="{ transform: `translateX(-${activeOutageSlide * 100}%)` }"
      >
        <div v-for="slide in outageSlides" :key="slide" class="outage-slide">
          <img :src="slide" alt="Hospital view" class="outage-slide-image" />
        </div>
      </div>
    </div>

    <template v-else>
      <LoadingBanner v-if="dashboard.isLoading" />

      <!-- Future Date Warning Alert -->
      <div
        v-if="dashboard.futureDateWarning"
        class="alert alert-warning alert-dismissible fade show mb-4 shadow-sm"
        role="alert"
      >
        <div class="d-flex align-items-center">
          <div class="me-3">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="32"
              height="32"
              fill="currentColor"
              class="text-warning"
              viewBox="0 0 16 16"
            >
              <path
                d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"
              />
            </svg>
          </div>
          <div>
            <h5 class="alert-heading mb-1 fw-bold">{{ dashboard.futureDateWarning.title }}</h5>
            <p class="mb-0">{{ dashboard.futureDateWarning.message }}</p>
          </div>
        </div>
      </div>

      <div :style="{ opacity: dashboard.isLoading ? 0.6 : 1, transition: 'opacity 0.3s' }">
        <WidgetsStatsD class="mb-2" />

      <ServiceTrendChart />

      <DashboardPieCharts />

      <!-- Patient Categories Ribbon (Original) -->
      <div class="card premium-shadow mb-4 overflow-hidden border-0">
        <div class="card-header bg-white border-0 py-3">
          <h4 class="mb-0 fw-bold text-primary" style="font-size: 22px">
            Patient Category Breakdown
          </h4>
        </div>
        <div class="card-body p-0">
          <div
            class="d-flex align-items-center justify-content-between p-3 overflow-auto custom-scrollbar"
          >
            <div
              v-for="(item, index) in patientCategories.filter((c) => c.title !== 'Total')"
              :key="index"
              class="d-flex flex-column align-items-center px-4 border-end category-ribbon-item"
            >
              <span
                class="text-uppercase fw-bold text-muted mb-1"
                :style="{ color: item.color, fontSize: '0.85rem' }"
                >{{ item.title }}</span
              >
              <div class="d-flex align-items-center">
                <h3 class="mb-0 fw-bold me-2">{{ item.value }}</h3>
              </div>
              <div
                class="progress-line mt-2"
                :style="{
                  backgroundColor: item.color,
                  width: '40px',
                  height: '3px',
                  borderRadius: '2px',
                }"
              ></div>
            </div>

            <!-- Total Section -->
            <div
              class="d-flex flex-column align-items-center px-4 bg-light-gradient category-ribbon-item"
            >
              <span class="text-uppercase fw-bold text-dark mb-1" style="font-size: 0.85rem"
                >Total</span
              >
              <h2 class="mb-0 fw-extrabold text-primary">
                {{ patientCategories.find((c) => c.title === 'Total')?.value || '0' }}
              </h2>
              <div
                class="progress-line mt-2 bg-primary"
                style="width: 50px; height: 4px; border-radius: 2px"
              ></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Patient Category Analytics - Two Cards Side by Side -->
      <CRow class="mb-4">
        <CCol :lg="6">
          <div class="card h-100 border-0 shadow-sm">
            <div class="card-header bg-white border-0 p-3">
              <h5 class="mb-0 fw-bold text-primary" style="font-size: 20px">
                Patient Category Chart
              </h5>
            </div>
            <div class="card-body p-0">
              <div class="chart-container" style="height: 420px">
                <CChart
                  type="bar"
                  :data="categoryChartData"
                  :options="categoryChartOptions"
                  :plugins="[categoryBarLabelsPlugin]"
                  style="height: 100%"
                />
              </div>
            </div>
          </div>
        </CCol>

        <CCol :lg="6">
          <div class="card h-100 border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
              <h5 class="mb-0 fw-bold text-primary" style="font-size: 20px">
                Patient Category Distribution
              </h5>
              <div class="d-flex flex-wrap justify-content-start mt-2 gap-2">
                <span
                  v-for="(item, index) in patientCategories.filter((c) => c.title !== 'Total')"
                  :key="index"
                  class="category-pill clickable-pill"
                  :class="{ 'pill-hidden': isCategoryHidden(item.title) }"
                  :style="{
                    border: `2px solid ${isCategoryHidden(item.title) ? '#ccc' : item.color}`,
                    color: isCategoryHidden(item.title) ? '#999' : item.color,
                    backgroundColor: isCategoryHidden(item.title)
                      ? 'transparent'
                      : `${item.color}15`,
                  }"
                  @click="togglePieCategory(item.title)"
                  :title="isCategoryHidden(item.title) ? 'Click to show' : 'Click to hide'"
                >
                  {{ item.title }}
                </span>
              </div>
            </div>
            <div class="card-body p-0">
              <div class="chart-container" style="height: 420px; margin-top: -10px">
                <CChartPie
                  :data="categoryPieChartData"
                  :options="categoryPieChartOptions"
                  :plugins="[categoryPieLabelsPlugin]"
                  style="height: 100%"
                />
              </div>
            </div>
          </div>
        </CCol>
      </CRow>

      <!-- All Clinics Histogram (Enhanced) -->
      <CRow class="mb-4 pb-4">
        <CCol :md="12">
          <DashboardClinicBarChart />
        </CCol>
      </CRow>
      
        <!-- Auto-scroll boundary marker - last section -->
        <div data-auto-scroll-boundary style="height: 1px;"></div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.outage-slideshow-shell {
  position: relative;
  width: 100%;
  min-height: calc(100vh - 120px);
  overflow: hidden;
  border-radius: 18px;
  background: #f8fafc;
}

.outage-slideshow-track {
  display: flex;
  width: 100%;
  min-height: calc(100vh - 120px);
  transition: transform 0.9s ease-in-out;
}

.outage-slide {
  flex: 0 0 100%;
  min-height: calc(100vh - 120px);
  background: #fff;
}

.outage-slide-image {
  width: 100%;
  height: 100%;
  min-height: calc(100vh - 120px);
  object-fit: cover;
  display: block;
}

/* Scrollbar Styling */
.custom-scrollbar::-webkit-scrollbar {
  height: 6px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: #f1f1f1;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #c1c1c1;
  border-radius: 3px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #a8a8a8;
}

/* Ribbon Item Styles */
.category-ribbon-item {
  min-width: 140px;
  transition: all 0.2s ease;
  cursor: default;
}

.category-ribbon-item:hover {
  transform: translateY(-2px);
}

.bg-danger-soft {
  background-color: #fff1f2;
}

.bg-info-soft {
  background-color: #f0f9ff;
}

.category-pill {
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s ease;
  user-select: none;
}

.category-pill:hover {
  transform: scale(1.05);
}

.pill-hidden {
  opacity: 0.5;
  text-decoration: line-through;
}

.chart-container {
  padding: 15px;
}

@media (max-width: 991.98px) {
  .outage-slideshow-shell,
  .outage-slideshow-track,
  .outage-slide,
  .outage-slide-image {
    min-height: 60vh;
  }
}
</style>
