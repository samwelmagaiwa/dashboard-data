<script setup>
import { defineAsyncComponent, computed, ref, onMounted, watch, onUnmounted } from 'vue'
import { useDashboardStore } from '@/stores/dashboard'
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
  cilCheckCircle,
  cilXCircle,
  cilChevronLeft,
  cilChevronRight,
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
const isClinicsVisible = ref(false)
const hiddenPieCategories = ref([]) // Track hidden categories for pie chart

// Pagination state for clinics table
const currentPage = ref(1)
const pageSize = ref(50)
const pageSizeOptions = [10, 25, 50, 100, 150, 200, 500]
let syncInterval = null

onMounted(() => {
  dashboard.fetchStats()
  dashboard.startPulse() // Start real-time polling

  // Trigger background sync every 5 minutes for admin users
  // (Actual data refresh is handled by store's adaptive polling)
  syncInterval = setInterval(async () => {
    if (dashboard.isAuthenticated) {
      await dashboard.triggerSync()
    }
  }, 300000) // 5 minutes
})

onUnmounted(() => {
  dashboard.stopPulse() // Cleanup polling
  if (syncInterval) clearInterval(syncInterval)
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
  const catTitles = [
    'FOREIGNER',
    'PUBLIC',
    'NHIF',
    'IPPM - PRIVATE',
    'IPPM - CREDIT',
    'COST SHARING',
    'NSSF',
  ]
  const colors = {
    FOREIGNER: '#e83e8c',
    PUBLIC: '#007bff',
    NHIF: '#28a745',
    'IPPM - PRIVATE': '#dc3545',
    'IPPM - CREDIT': '#ffc107',
    'COST SHARING': '#6f42c1',
    NSSF: '#17a2b8',
  }

  const cats = catTitles.map((title) => {
    const metric = dashboard.metrics.find((m) => m.title === title)
    return {
      title,
      value: metric ? metric.value : '0',
      color: colors[title] || '#6c757d',
    }
  })

  // Calculate sum for the ribbon Total
  const totalSum = cats.reduce((acc, cat) => {
    const num = parseInt(cat.value.replace(/,/g, '')) || 0
    return acc + num
  }, 0)

  return [
    ...cats,
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
  // For logarithmic scale, we need a clean upper bound
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
        min: 1, // Ensures bars for small values are visible
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
            // Only show major/clean numbers on log scale
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
    // Only draw labels for bar dataset (index 0)
    const meta = chart.getDatasetMeta(0)
    if (meta.type !== 'bar') return

    meta.data.forEach((bar, index) => {
      const value = chart.data.datasets[0].data[index]
      if (value === 0) return

      const { x, y } = bar.tooltipPosition()
      const displayValue = new Intl.NumberFormat('en-US').format(value)
      // Get the bar color to match the label color
      const barColor = chart.data.datasets[0].backgroundColor[index]

      ctx.save()
      ctx.font = "bold 26px 'Outfit', Arial, sans-serif"
      ctx.fillStyle = barColor
      ctx.textAlign = 'center'
      ctx.textBaseline = 'bottom'
      // Add shadow for better visibility
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

  // Filter out hidden categories or set their value to 0
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

// Plugin to draw labels (Inside for large slices, Outside with lines for small)
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

      const model = element
      const midAngle = model.startAngle + (model.endAngle - model.startAngle) / 2
      const angleSpan = ((model.endAngle - model.startAngle) * 180) / Math.PI

      // Configuration for labels
      const isInternal = angleSpan >= 15
      const sliceColor = chart.data.datasets[0].backgroundColor[index]

      if (isInternal) {
        // --- DRAW INSIDE ---
        const midRadius = model.outerRadius * 0.6 + model.innerRadius * 0.1
        const x = Math.cos(midAngle) * midRadius + model.x
        const y = Math.sin(midAngle) * midRadius + model.y

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
        // --- DRAW OUTSIDE WITH ARROW/LINE ---
        const r = model.outerRadius
        const cx = model.x
        const cy = model.y

        // Point 1: Edge of slice
        const x1 = Math.cos(midAngle) * (r * 0.99) + cx
        const y1 = Math.sin(midAngle) * (r * 0.99) + cy

        // Point 2: Just outside slice
        const x2 = Math.cos(midAngle) * (r * 1.03) + cx
        const y2 = Math.sin(midAngle) * (r * 1.03) + cy

        // Point 3: Horizontal extension
        const isRight = x2 > cx
        const x3 = x2 + (isRight ? 8 : -8)
        const y3 = y2

        // Draw the line
        ctx.shadowBlur = 0
        ctx.strokeStyle = sliceColor
        ctx.lineWidth = 2
        ctx.beginPath()
        ctx.moveTo(x1, y1)
        ctx.lineTo(x2, y2)
        ctx.lineTo(x3, y3)
        ctx.stroke()

        // Draw labeling text (Slice color)
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
// Filter detailed clinic visits based on search term
const filteredDetailedClinics = computed(() => {
  if (!dashboard.detailedClinics) return []
  const query = dashboard.searchTerm.toLowerCase()
  if (!query) return dashboard.detailedClinics

  return dashboard.detailedClinics.filter((item) => {
    return (
      (item.mr_number && item.mr_number.toLowerCase().includes(query)) ||
      (item.clinic_name && item.clinic_name.toLowerCase().includes(query)) ||
      (item.bill_doct_name && item.bill_doct_name.toLowerCase().includes(query)) ||
      (item.cons_doctor_name && item.cons_doctor_name.toLowerCase().includes(query)) ||
      (item.clinic_code && item.clinic_code.toLowerCase().includes(query))
    )
  })
})

// Client-side pagination logic
const paginatedDetailedClinics = computed(() => {
  const start = (currentPage.value - 1) * pageSize.value
  const end = start + pageSize.value
  return filteredDetailedClinics.value.slice(start, end)
})

const totalPages = computed(() => {
  return Math.ceil(filteredDetailedClinics.value.length / pageSize.value)
})

// Watch search term to reset pagination
watch(
  () => dashboard.searchTerm,
  () => {
    currentPage.value = 1
  },
)

// Watch page size to reset pagination
watch(pageSize, () => {
  currentPage.value = 1
})

const clinicStats = computed(() => {
  const snapshotStats = dashboard.realStats

  // Always prefer the fast backend snapshot counts when available.
  // These are accurate for ALL records, even if the table only shows a subset (e.g., 5,000 of 186k).
  if (snapshotStats && snapshotStats.total_detailed > 0) {
    return {
      matches: snapshotStats.matched_count || 0,
      mismatches: snapshotStats.mismatched_count || 0,
      total: snapshotStats.total_detailed || 0,
    }
  }

  // Fallback: calculate from loaded detailed data (for small date ranges where snapshot may not have these fields)
  const detailed = filteredDetailedClinics.value
  let matches = 0
  let mismatches = 0

  detailed.forEach((item) => {
    if (isDoctorMismatch(item.doct_code, item.cons_doctor)) {
      mismatches++
    } else {
      matches++
    }
  })

  return { matches, mismatches, total: detailed.length }
})

const getVisitTypeBadge = (type) => {
  return type === 'N' ? 'primary' : 'info'
}

const getVisitTypeLabel = (type) => {
  return type === 'N' ? 'New' : 'Followup'
}

const isDoctorMismatch = (billed, attended) => {
  if (!billed || !attended) return false
  return billed.trim().toLowerCase() !== attended.trim().toLowerCase()
}

const getClinicColor = (name) => {
  if (!name) return '#636f83'

  let hash = 0
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash)
  }

  // Generate unique HSL colors based on hash
  // Hue: 0-360
  // Saturation: 60-85% (Vibrant)
  // Lightness: 35-50% (Dark enough for white background, light enough to be seen)
  const h = Math.abs(hash) % 360
  const s = 60 + (Math.abs(hash >> 8) % 25)
  const l = 35 + (Math.abs(hash >> 16) % 15)

  return `hsl(${h}, ${s}%, ${l}%)`
}

const formatDate = (dateStr) => {
  if (!dateStr) return 'Date is empty'
  // If it's ISO format "2026-02-24T00:00:00.000000Z", just take the first 10 chars
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
        <!-- Left: Bar + Line Chart Card -->
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

        <!-- Right: Patient Category Pie Chart -->
        <CCol :lg="6">
          <div class="card h-100 border-0 shadow-sm">
            <div class="card-header bg-white border-0 py-3">
              <h5 class="mb-0 fw-bold text-primary" style="font-size: 20px">
                Patient Category Distribution
              </h5>
              <!-- Custom Pill Legend (Clickable) -->
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
      <CRow class="mb-4">
        <CCol :md="12">
          <DashboardClinicBarChart />
        </CCol>
      </CRow>

      <!-- Clinic Breakdown Table (Restored) -->
      <CRow v-if="dashboard.realStats">
        <CCol :md="12">
          <CCard class="border-0 shadow-sm overflow-hidden">
            <CCardHeader
              class="bg-white d-flex justify-content-between align-items-center cursor-pointer py-2"
              @click="isClinicsVisible = !isClinicsVisible"
            >
              <div class="d-flex align-items-center flex-grow-1">
                <div class="fw-bold me-2 text-nowrap" style="font-size: 1.3rem">
                  Click here to view More Clinics Data
                </div>
                <!-- Integrated Search Field -->
                <div class="flex-grow-1 mx-2" style="max-width: 400px" @click.stop>
                  <CInputGroup size="sm">
                    <CInputGroupText class="bg-light border-end-0">
                      <CIcon :icon="cilSearch" class="text-muted" />
                    </CInputGroupText>
                    <CFormInput
                      v-model="dashboard.searchTerm"
                      placeholder="Search MR Number, Clinic, or Doctor..."
                      class="border-start-0 bg-light"
                      style="box-shadow: none; border-color: #dee2e6"
                    />
                  </CInputGroup>
                </div>
                <!-- Totals Section -->
                <div class="d-flex align-items-center text-nowrap ms-2" style="font-size: 1.15rem">
                  <div
                    class="badge bg-success-soft text-success border border-success me-2 py-2 px-3 d-flex align-items-center shadow-sm"
                  >
                    <CIcon :icon="cilCheckCircle" size="lg" class="me-2" />
                    Matched:
                    <strong class="ms-1" style="font-size: 1.4rem">{{
                      clinicStats.matches
                    }}</strong>
                  </div>
                  <div
                    class="badge bg-danger-soft text-danger border border-danger me-2 py-2 px-3 d-flex align-items-center shadow-sm"
                  >
                    <CIcon :icon="cilXCircle" size="lg" class="me-2" />
                    Mismatched:
                    <strong class="ms-1" style="font-size: 1.4rem">{{
                      clinicStats.mismatches
                    }}</strong>
                  </div>
                  <div
                    class="badge bg-info-soft text-info border border-info py-2 px-3 d-flex align-items-center shadow-sm"
                  >
                    <CIcon :icon="cilPeople" size="lg" class="me-2" />
                    Total Records:
                    <strong class="ms-1" style="font-size: 1.4rem">{{ clinicStats.total }}</strong>
                  </div>
                </div>
              </div>
              <div class="d-flex align-items-center">
                <!-- Page Size Selector -->
                <div class="me-3" @click.stop>
                  <CFormSelect
                    size="sm"
                    v-model="pageSize"
                    class="bg-light border-0 shadow-sm"
                    style="width: 100px; cursor: pointer"
                  >
                    <option v-for="option in pageSizeOptions" :key="option" :value="option">
                      {{ option }} / page
                    </option>
                  </CFormSelect>
                </div>
                <CIcon :icon="isClinicsVisible ? cilChevronTop : cilChevronBottom" size="sm" />
              </div>
            </CCardHeader>
            <div v-show="isClinicsVisible" class="collapse-content">
              <CCardBody>
                <!-- Detailed Table Content -->
                <div class="table-responsive" style="max-height: 500px">
                  <table class="table table-hover align-middle table-sm-text">
                    <thead class="table-light sticky-top">
                      <tr>
                        <th class="text-center">S/NO</th>
                        <th>MR Number</th>
                        <th class="text-center">Gender</th>
                        <th class="text-center">Age</th>
                        <th class="text-center">Type</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Dr Code</th>
                        <th>Bill Doctor (Cashier)</th>
                        <th>Attend Doctor</th>
                        <th>Clinic Name</th>
                        <th>Clinic Code</th>
                        <th style="min-width: 250px; width: 350px">Diagnosis</th>
                        <th class="text-center" style="width: 80px">Mismatch</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="(item, index) in paginatedDetailedClinics" :key="index">
                        <td class="text-center text-muted small">
                          {{ (currentPage - 1) * pageSize + index + 1 }}
                        </td>
                        <td class="fw-bold text-primary">{{ item.mr_number }}</td>
                        <td class="text-center">
                          <span
                            class="badge"
                            :class="
                              item.gender === 'M'
                                ? 'bg-info-soft text-info'
                                : 'bg-danger-soft text-danger'
                            "
                          >
                            {{ item.gender }}
                          </span>
                        </td>
                        <td class="text-center">{{ item.pat_age }}</td>
                        <td class="text-center">
                          <span
                            class="badge"
                            :class="`bg-${getVisitTypeBadge(item.visit_type)}-soft text-${getVisitTypeBadge(item.visit_type)}`"
                          >
                            {{ getVisitTypeLabel(item.visit_type) }}
                          </span>
                        </td>
                        <td class="text-nowrap">{{ formatDate(item.visit_date) }}</td>
                        <td>{{ item.cons_time }}</td>
                        <td class="small">{{ item.doct_code }}</td>
                        <td class="small fw-bold">{{ item.bill_doct_name || 'Name is empty' }}</td>
                        <td class="small fw-bold">
                          {{ item.cons_doctor_name || 'Name is empty' }}
                        </td>
                        <td class="fw-bold" :style="{ color: getClinicColor(item.clinic_name) }">
                          {{ item.clinic_name }}
                        </td>
                        <td class="fw-bold" style="font-size: 0.85rem">
                          <code class="text-dark bg-light px-2 py-1 rounded shadow-sm border">{{
                            item.clinic_code
                          }}</code>
                        </td>
                        <td style="max-width: 350px">
                          <div
                            v-if="item.final_diag || item.prov_diag"
                            class="d-flex flex-column gap-1"
                          >
                            <div v-if="item.final_diag" class="small fw-bold text-success">
                              final: {{ item.final_diag }}
                            </div>
                            <div v-if="item.prov_diag" class="small fw-bold text-primary">
                              prov: {{ item.prov_diag }}
                            </div>
                          </div>
                          <div
                            v-else
                            class="badge bg-danger-soft text-danger fw-bold"
                            style="font-size: 10px; padding: 5px 10px"
                          >
                            No Diagnosis Recorded
                          </div>
                        </td>
                        <td class="text-center">
                          <CIcon
                            v-if="!isDoctorMismatch(item.bill_doct_name, item.cons_doctor_name)"
                            :icon="cilCheckCircle"
                            class="text-success"
                            size="lg"
                            title="Doctors Match"
                          />
                          <CIcon
                            v-else
                            :icon="cilXCircle"
                            class="text-danger"
                            size="lg"
                            title="Doctor Mismatch"
                          />
                        </td>
                      </tr>
                      <tr
                        v-if="!dashboard.isDetailedLoading && filteredDetailedClinics.length === 0"
                      >
                        <td colspan="14" class="text-center py-5 text-muted">
                          No records found for the selected criteria.
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>

                <!-- Pagination Footer -->
                <div
                  v-if="totalPages > 1"
                  class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top"
                >
                  <div class="small text-muted">
                    Showing <strong>{{ (currentPage - 1) * pageSize + 1 }}</strong> to
                    <strong>{{
                      Math.min(currentPage * pageSize, filteredDetailedClinics.length)
                    }}</strong>
                    of <strong>{{ filteredDetailedClinics.length }}</strong> records
                  </div>
                  <div class="d-flex align-items-center">
                    <CButton
                      size="sm"
                      color="light"
                      class="me-2 shadow-sm border"
                      :disabled="currentPage === 1"
                      @click="currentPage--"
                    >
                      <CIcon :icon="cilChevronLeft" class="me-1" /> Previous
                    </CButton>

                    <div class="d-flex align-items-center mx-2">
                      <span class="me-2 small">Page</span>
                      <CFormSelect
                        size="sm"
                        v-model="currentPage"
                        class="bg-white border shadow-sm"
                        style="width: 80px"
                      >
                        <option v-for="page in totalPages" :key="page" :value="page">
                          {{ page }}
                        </option>
                      </CFormSelect>
                      <span class="ms-2 small text-muted">of {{ totalPages }}</span>
                    </div>

                    <CButton
                      size="sm"
                      color="light"
                      class="ms-2 shadow-sm border"
                      :disabled="currentPage === totalPages"
                      @click="currentPage++"
                    >
                      Next <CIcon :icon="cilChevronRight" class="ms-1" />
                    </CButton>
                  </div>
                </div>
              </CCardBody>
            </div>
          </CCard>
        </CCol>
      </CRow>
    </div>
  </div>
</template>

<style scoped>
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
  min-width: 140px; /* Ensure items don't squish too much */
  transition: all 0.2s ease;
  cursor: default;
}

.category-ribbon-item:hover {
  transform: translateY(-2px);
}

.category-ribbon-item:last-child {
  border-right: none !important;
}

/* Compact Ribbon Items for side-by-side layout */
.category-ribbon-item-compact {
  min-width: 100px;
  transition: all 0.2s ease;
  cursor: default;
  border-radius: 8px;
  margin: 4px;
}

.category-ribbon-item-compact:hover {
  transform: translateY(-2px);
  background-color: rgba(0, 0, 0, 0.02);
}

.category-ribbon-item-compact.total-section {
  min-width: 120px;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 48, 130, 0.1);
}

.bg-light-gradient {
  background: linear-gradient(135deg, rgba(255, 255, 255, 1) 0%, rgba(240, 242, 245, 0.5) 100%);
}

.text-xs {
  font-size: 0.75rem;
  letter-spacing: 0.5px;
}

.fw-extrabold {
  font-weight: 800;
}

/* Category Pill Badges */
.category-pill {
  display: inline-block;
  padding: 4px 14px;
  border-radius: 20px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  background-color: transparent;
  transition: all 0.2s ease;
}

.category-pill:hover {
  transform: scale(1.05);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
}

/* Clickable Pill Styles */
.clickable-pill {
  cursor: pointer;
  user-select: none;
}

.clickable-pill:hover {
  transform: scale(1.08);
  box-shadow: 0 3px 12px rgba(0, 0, 0, 0.2);
}

.clickable-pill:active {
  transform: scale(0.95);
}

.clickable-pill.pill-hidden {
  opacity: 0.6;
  text-decoration: line-through;
}

.clickable-pill.pill-hidden:hover {
  opacity: 0.8;
}

/* Premium Dashboard Styles */
:deep(.card) {
  border: none;
  border-radius: 16px;
  box-shadow: 0 4px 24px 0 rgba(0, 0, 0, 0.05);
  transition:
    transform 0.3s ease,
    box-shadow 0.3s ease;
  background: white;
  margin-bottom: 24px;
}

:deep(.card:hover) {
  transform: translateY(-5px);
  box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
}

:deep(.card-header) {
  border-bottom: 1px solid rgba(0, 0, 0, 0.05);
  background-color: transparent;
  font-weight: 700;
  padding: 1.5rem;
  font-size: 1.1rem;
  color: #003082; /* MNH Blue */
}

:deep(.card-body) {
  padding: 1.5rem;
}

:deep(.text-body-secondary) {
  color: #6c757d !important;
}

/* Table Font Size Optimization */
.table-sm-text {
  font-size: 14px;
}
.table-sm-text th {
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
.table-sm-text td {
  padding-top: 8px;
  padding-bottom: 8px;
}

/* Fix sticky header padding */
.table-responsive {
  scrollbar-width: thin;
}

.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.5s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
.loading-overlay-subtle {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-color: rgba(255, 255, 255, 0.1);
  z-index: 2;
  pointer-events: none;
}
</style>
