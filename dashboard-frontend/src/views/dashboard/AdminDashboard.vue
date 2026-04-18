<script setup>
import { defineAsyncComponent, computed, onMounted } from 'vue'
import { useDashboardStore } from '@/stores/dashboard'
import { useRouter } from 'vue-router'
import LoadingBanner from '@/components/LoadingBanner.vue'
import { ChartLine, ChartBar } from '../charts/index.js'
import { CIcon } from '@coreui/icons-vue'
import { CChart } from '@coreui/vue-chartjs'
import { 
  CDropdown, 
  CDropdownToggle, 
  CDropdownMenu, 
  CDropdownItem,
  CFormSelect
} from '@coreui/vue'

const dashboard = useDashboardStore()
const router = useRouter()

onMounted(() => {
  // Check if user is authenticated and has admin role
  if (!dashboard.isAuthenticated) {
    router.push('/login')
    return
  }

  const adminRoles = ['ED', 'DED', 'DICT']
  if (!adminRoles.includes(dashboard.user?.role)) {
    router.push('/')
    return
  }

  dashboard.fetchStats()
})

const handleClinicChange = (clinic) => {
  dashboard.selectedClinic = clinic
  dashboard.fetchTopDiseases()
}

const topDiseasesChartData = computed(() => {
  if (!dashboard.topDiseases || dashboard.topDiseases.length === 0) {
    return { labels: [], datasets: [] }
  }

  // Use the top 10 diseases
  const sortedDiseases = [...dashboard.topDiseases].sort((a, b) => b.total - a.total).slice(0, 10)
  const labels = sortedDiseases.map((d) => d.name)

  // Identify all unique clinics and their total volume in these top 10 diseases
  const clinicTotals = {}
  sortedDiseases.forEach((d) => {
    Object.entries(d.clinics).forEach(([name, count]) => {
      clinicTotals[name] = (clinicTotals[name] || 0) + count
    })
  })

  // To keep legend readable, keep top 10 clinics and group others
  const sortedAllClinics = Object.entries(clinicTotals)
    .sort((a, b) => b[1] - a[1])
  
  const topClinicNames = sortedAllClinics.slice(0, 12).map(c => c[0])
  const hasOthers = sortedAllClinics.length > 12

  const finalClinicNames = [...topClinicNames]
  if (hasOthers) finalClinicNames.push('Other Clinics')

  const colors = [
    '#e55353', '#4fbc9c', '#321fdb', '#f9b115', '#9da5b1',
    '#39f23d', '#2eb85c', '#3399ff', '#f86c6b', '#ffc107',
    '#63c2de', '#4dbd74', '#6610f2', '#6f42c1', '#e83e8c'
  ]

  const datasets = finalClinicNames.map((clinicName, index) => {
    return {
      label: clinicName,
      data: sortedDiseases.map((d) => {
        if (clinicName === 'Other Clinics') {
          return Object.entries(d.clinics)
            .filter(([name]) => !topClinicNames.includes(name))
            .reduce((sum, [_, count]) => sum + count, 0)
        }
        return d.clinics[clinicName] || 0
      }),
      backgroundColor: colors[index % colors.length],
      barThickness: labels.length > 5 ? 25 : 35,
    }
  })

  return { labels, datasets }
})

const topDiseasesChartOptions = computed(() => ({
  indexAxis: 'y',
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom',
      labels: {
        usePointStyle: true,
        padding: 15,
        font: { size: 11, weight: '500' },
        boxWidth: 8,
      },
    },
    tooltip: {
      padding: 12,
      backgroundColor: 'rgba(30, 41, 59, 0.95)', // Sleek dark tooltip
      titleColor: '#fff',
      bodyColor: '#fff',
      cornerRadius: 8,
      borderColor: 'rgba(255,255,255,0.1)',
      borderWidth: 1,
      callbacks: {
        title: (tooltipItems) => {
          const diseaseLabel = tooltipItems[0].label
          const disease = dashboard.topDiseases.find((d) => d.name === diseaseLabel)
          return `${diseaseLabel} (Total: ${disease?.total || 0})`
        },
        label: (context) => {
          const val = context.raw
          if (val === 0) return null // DYNAMIC: Hide 0 values to keep tooltip short
          return `${context.dataset.label}: ${val.toLocaleString()}`
        },
      },
    },
  },
  scales: {
    x: {
      stacked: true,
      grid: { display: true, drawBorder: false, color: '#f0f0f0' },
      ticks: { 
        font: { size: 10 },
        callback: (value) => value >= 1000 ? (value/1000) + 'k' : value
      },
    },
    y: {
      stacked: true,
      grid: { display: false },
      ticks: { font: { size: 12, weight: '600' } },
    },
  },
}))

const chartOptions = {
  maintainAspectRatio: false,
}

// Helper functions to get values from dashboard metrics
const getValue = (title) => {
  return dashboard.metrics.find((m) => m.title === title)?.value || '0'
}

const getPercentage = (valueTitle, totalTitle) => {
  const valStr = dashboard.metrics.find((m) => m.title === valueTitle)?.value || '0'
  const totalStr = dashboard.metrics.find((m) => m.title === totalTitle)?.value || '1'

  const val = parseInt(valStr.replace(/,/g, ''))
  const total = parseInt(totalStr.replace(/,/g, ''))

  if (!total) return '0%'
  return Math.round((val / total) * 100) + '%'
}
</script>

<template>
  <div class="admin-dashboard p-3" style="position: relative; min-height: 400px">
    <LoadingBanner v-if="dashboard.isLoading" />

    <div :style="{ opacity: dashboard.isLoading ? 0.6 : 1, transition: 'opacity 0.3s' }">
      <!-- Header -->
      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h2 class="mb-1 fw-bold text-dark">Administrative Dashboard</h2>
          <p class="text-muted mb-0">Hospital Management Overview</p>
        </div>
        <div class="d-flex align-items-center gap-3">
          <CDropdown variant="btn-group">
            <CDropdownToggle color="white" class="premium-shadow-sm border-0 px-3">
              <span class="text-muted small me-2">Clinic:</span>
              <span class="fw-bold">{{ dashboard.selectedClinic }}</span>
            </CDropdownToggle>
            <CDropdownMenu style="max-height: 300px; overflow-y: auto">
              <CDropdownItem @click="handleClinicChange('All Clinics')">All Clinics</CDropdownItem>
              <CDropdownItem
                v-for="clinic in dashboard.realClinics"
                :key="clinic.clinic_code"
                @click="handleClinicChange(clinic.clinic_name)"
              >
                {{ clinic.clinic_name }}
              </CDropdownItem>
            </CDropdownMenu>
          </CDropdown>

          <div class="badge bg-primary-gradient px-3 py-2" style="font-size: 0.9rem">
            <span class="fw-bold">{{ dashboard.user?.role }}</span> Access
          </div>
        </div>
      </div>

      <!-- Hospital Metrics Cards (Moved from Public Dashboard) -->
      <CRow :xs="{ gutter: 4 }" class="mb-4">
        <!-- Beds Card -->
        <CCol :md="4">
          <div class="stat-card premium-shadow">
            <div class="stat-card-header">
              <div class="stat-icon-wrapper bg-blue-soft">
                <CIcon icon="cil-bed" class="stat-icon text-blue" />
              </div>
              <div class="stat-main-info">
                <h3 class="stat-value text-blue">{{ getValue('Total Beds') }}</h3>
                <span class="stat-label">Total Beds</span>
              </div>
            </div>
            <div class="stat-card-footer">
              <div class="stat-footer-item">
                <span class="footer-label">Occupied</span>
                <div class="footer-value-group">
                  <span class="footer-value text-success">{{ getValue('Occupied Beds') }}</span>
                  <span class="stat-percentage text-success">{{
                    getPercentage('Occupied Beds', 'Total Beds')
                  }}</span>
                </div>
              </div>
              <div class="vr"></div>
              <div class="stat-footer-item">
                <span class="footer-label">Free</span>
                <div class="footer-value-group">
                  <span class="footer-value text-muted">{{ getValue('Free Beds') }}</span>
                  <span class="stat-percentage text-muted">{{
                    getPercentage('Free Beds', 'Total Beds')
                  }}</span>
                </div>
              </div>
            </div>
          </div>
        </CCol>

        <!-- Discharges Card -->
        <CCol :md="4">
          <div class="stat-card premium-shadow">
            <div class="stat-card-header">
              <div class="stat-icon-wrapper bg-purple-soft">
                <CIcon icon="cil-user" class="stat-icon text-purple" />
              </div>
              <div class="stat-main-info">
                <h3 class="stat-value text-purple">{{ getValue('Discharges') }}</h3>
                <span class="stat-label">Total Discharges</span>
              </div>
            </div>
            <div class="stat-card-footer">
              <div class="stat-footer-item">
                <span class="footer-label">Live</span>
                <div class="footer-value-group">
                  <span class="footer-value text-success">{{ getValue('LIVE') }}</span>
                  <span class="stat-percentage text-success">{{
                    getPercentage('LIVE', 'Discharges')
                  }}</span>
                </div>
              </div>
              <div class="vr"></div>
              <div class="stat-footer-item">
                <span class="footer-label">Dead</span>
                <div class="footer-value-group">
                  <span class="footer-value text-danger">{{ getValue('DEAD') }}</span>
                  <span class="stat-percentage text-danger">{{
                    getPercentage('DEAD', 'Discharges')
                  }}</span>
                </div>
              </div>
            </div>
          </div>
        </CCol>

        <!-- Attendance Card -->
        <CCol :md="4">
          <div class="stat-card premium-shadow">
            <div class="stat-card-header">
              <div class="stat-icon-wrapper bg-orange-soft">
                <CIcon icon="cil-clock" class="stat-icon text-orange" />
              </div>
              <div class="stat-main-info">
                <h3 class="stat-value text-orange">{{ getValue('Attendance') }}</h3>
                <span class="stat-label">Total Attendance</span>
              </div>
            </div>
            <div class="stat-card-footer">
              <div class="stat-footer-item">
                <span class="footer-label">On-Time</span>
                <div class="footer-value-group">
                  <span class="footer-value text-success">{{ getValue('ON-TIME') }}</span>
                  <span class="stat-percentage text-success">{{
                    getPercentage('ON-TIME', 'Attendance')
                  }}</span>
                </div>
              </div>
              <div class="vr"></div>
              <div class="stat-footer-item">
                <span class="footer-label">Late</span>
                <div class="footer-value-group">
                  <span class="footer-value text-warning">{{ getValue('LATE') }}</span>
                  <span class="stat-percentage text-warning">{{
                    getPercentage('LATE', 'Attendance')
                  }}</span>
                </div>
              </div>
            </div>
          </div>
        </CCol>
      </CRow>

      <!-- Charts Section -->
      <CRow>
        <CCol :md="6" class="mb-4">
          <CCard class="h-100 border-0 shadow-sm overflow-hidden">
            <CCardHeader class="bg-white border-0 py-3 d-flex justify-content-between align-items-center">
              <h5 class="mb-0 fw-bold text-dark">Top 10 Diseases</h5>
            </CCardHeader>
            <CCardBody :style="{ height: topDiseasesChartData.datasets.length > 8 ? '450px' : '380px' }" class="px-3 pb-3">
              <div v-if="!dashboard.topDiseases || dashboard.topDiseases.length === 0" class="h-100 d-flex align-items-center justify-content-center flex-column text-muted">
                <CIcon icon="cil-chart" size="xl" class="mb-2 opacity-50" />
                <p class="small">No data available.</p>
              </div>
              <CChart
                v-else
                type="bar"
                :data="topDiseasesChartData"
                :options="topDiseasesChartOptions"
                style="height: 100%; width: 100%"
              />
            </CCardBody>
          </CCard>
        </CCol>
        <CCol :md="6" class="mb-4">
          <CCard class="h-100 border-0 shadow-sm">
            <CCardHeader class="bg-white">Admission VS Discharges</CCardHeader>
            <CCardBody style="height: 350px">
              <ChartBar
                :data="dashboard.barChartData"
                :options="chartOptions"
                style="height: 100%; width: 100%"
              />
            </CCardBody>
          </CCard>
        </CCol>
      </CRow>
    </div>
  </div>
</template>

<style scoped>
.admin-dashboard {
  background: #f8f9fa;
  min-height: 100vh;
}

.footer-value-group {
  display: flex;
  align-items: baseline;
  gap: 0.35rem;
}

.stat-percentage {
  font-size: 0.85rem;
  font-weight: 600;
  padding: 0.1rem 0.4rem;
  border-radius: 999px;
  background-color: rgba(0, 0, 0, 0.05);
}

.text-muted.stat-percentage {
  background-color: rgba(108, 117, 125, 0.15);
}

.bg-primary-gradient {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.premium-shadow {
  box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04);
  border-radius: 16px;
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}

.premium-shadow:hover {
  transform: translateY(-5px);
  box-shadow: 0 15px 50px rgba(0, 0, 0, 0.07);
}

.premium-shadow-sm {
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
  border-radius: 10px;
}

.stat-card {
  background: white;
  border-radius: 16px;
  padding: 1.5rem;
  height: 100%;
  display: flex;
  flex-direction: column;
}

.stat-card-header {
  display: flex;
  align-items: flex-start;
  gap: 1.25rem;
  margin-bottom: 1.5rem;
}

.stat-icon-wrapper {
  width: 54px;
  height: 54px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-icon {
  width: 28px;
  height: 28px;
}

.stat-main-info {
  display: flex;
  flex-direction: column;
}

.stat-value {
  font-size: 1.75rem;
  line-height: 1.2;
  font-weight: 800;
  margin-bottom: 0.25rem;
}

.stat-label {
  color: #64748b;
  font-size: 0.95rem;
  font-weight: 500;
}

.stat-card-footer {
  margin-top: auto;
  padding-top: 1.25rem;
  border-top: 1px dashed #e2e8f0;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.stat-footer-item {
  flex: 1;
}

.footer-label {
  display: block;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  color: #94a3b8;
  margin-bottom: 0.5rem;
  font-weight: 700;
}

.footer-value {
  font-size: 1.15rem;
  font-weight: 700;
}

/* Colors */
.bg-blue-soft { background-color: rgba(51, 153, 255, 0.1); }
.text-blue { color: #3399ff; }
.bg-purple-soft { background-color: rgba(158, 119, 237, 0.1); }
.text-purple { color: #9e77ed; }
.bg-orange-soft { background-color: rgba(247, 144, 9, 0.1); }
.text-orange { color: #f79009; }

.bg-primary-gradient {
  background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
}
</style>
