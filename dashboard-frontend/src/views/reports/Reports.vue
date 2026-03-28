<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useDashboardStore } from '@/stores/dashboard'
import axios from 'axios'
import { CCard, CCardHeader, CCardBody, CRow, CCol, CSpinner, CButton } from '@coreui/vue'
import CIcon from '@coreui/icons-vue'

const dashboard = useDashboardStore()
const isLoading = ref(true)
const reportData = ref({
  by_clinic: [],
  aging: [],
  list: [],
})

const groupedReportData = computed(() => {
  const groups = {}
  if (!reportData.value.list) return groups

  reportData.value.list.forEach((item) => {
    const clinic = item.clinic_name || 'Unknown Clinic'
    if (!groups[clinic]) groups[clinic] = []
    groups[clinic].push(item)
  })
  return groups
})

const fetchReports = async () => {
  isLoading.value = true
  const { start_date, end_date } = dashboard.calculateDateRange()
  try {
    const res = await dashboard.api.get('/reports/pending', {
      params: { start_date, end_date },
    })
    if (res.data.status === 'success') {
      reportData.value = res.data.data
    }
  } catch (e) {
    console.error('Failed to fetch reports', e)
  } finally {
    isLoading.value = false
  }
}

watch(
  () => [
    dashboard.selectedPeriod,
    dashboard.selectedDay,
    dashboard.selectedWeek,
    dashboard.selectedMonth,
    dashboard.selectedYear,
    dashboard.selectedRange,
  ],
  () => {
    fetchReports()
  },
  { deep: true },
)

onMounted(() => {
  fetchReports()
})

const formatDate = (dateStr) => {
  if (!dateStr) return 'N/A'
  if (typeof dateStr === 'string' && dateStr.includes('T')) {
    return dateStr.split('T')[0]
  }
  return dateStr
}
</script>

<template>
  <div class="reports-container">
    <!-- Top Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4 pt-2">
      <div>
        <h3 class="fw-bold text-gradient mb-1">Clinical Insight Reports</h3>
        <p class="text-muted small mb-0">Detailed breakdown of pending patient consultations</p>
      </div>
      <div class="d-flex gap-2">
        <CButton
          color="white"
          class="shadow-sm px-3 border"
          @click="$router.push('/')"
        >
          <CIcon icon="cil-arrow-left" class="me-2" />
          Dashboard
        </CButton>
        <CButton
          color="primary"
          class="shadow-sm px-3 fw-semibold"
          @click="fetchReports"
          :disabled="isLoading"
        >
          <CIcon icon="cil-reload" class="me-2" :class="{ 'fa-spin': isLoading }" />
          Refresh
        </CButton>
      </div>
    </div>

    <!-- Stats Summary Row -->
    <CRow class="mb-4">
      <CCol md="3">
        <CCard class="summary-card border-0 shadow-sm bg-primary text-white">
          <CCardBody class="d-flex align-items-center">
            <div class="icon-box bg-white text-primary me-3">
              <CIcon icon="cil-people" size="xl" />
            </div>
            <div>
              <div class="text-white-50 small">Total Pending</div>
              <div class="fs-4 fw-bold">{{ reportData.list.length }}</div>
            </div>
          </CCardBody>
        </CCard>
      </CCol>
      <CCol md="3">
        <CCard class="summary-card border-0 shadow-sm bg-info text-white">
          <CCardBody class="d-flex align-items-center">
            <div class="icon-box bg-white text-info me-3">
              <CIcon icon="cil-hospital" size="xl" />
            </div>
            <div>
              <div class="text-white-50 small">Total Clinics</div>
              <div class="fs-4 fw-bold">{{ reportData.by_clinic.length }}</div>
            </div>
          </CCardBody>
        </CCard>
      </CCol>
    </CRow>

    <div v-if="isLoading" class="d-flex justify-content-center my-5 py-5">
      <div class="text-center">
        <CSpinner color="primary" variant="grow" class="mb-3" />
        <p class="text-muted">Synchronizing clinical data...</p>
      </div>
    </div>

    <div v-else>
      <CRow class="mb-4">
        <!-- Clinic Breakdown -->
        <CCol md="6">
          <CCard class="border-0 shadow-sm h-100 premium-card overflow-hidden">
            <CCardHeader class="bg-white premium-header-mini d-flex justify-content-between">
              <span>Clinic Distribution</span>
              <CIcon icon="cil-chart-pie" class="text-muted" />
            </CCardHeader>
            <CCardBody class="p-0">
              <div class="table-responsive" style="max-height: 400px">
                <table class="table table-hover align-middle mb-0">
                  <thead class="bg-light">
                    <tr>
                      <th class="ps-4">Clinic Name</th>
                      <th class="text-end pe-4">Qty</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="item in reportData.by_clinic"
                      :key="item.clinic_name"
                      class="border-start-4 border-primary"
                    >
                      <td class="ps-4 fw-semibold">{{ item.clinic_name }}</td>
                      <td class="text-end pe-4">
                        <span class="badge bg-danger-soft text-danger fs-6">{{ item.count }}</span>
                      </td>
                    </tr>
                    <tr v-if="reportData.by_clinic.length === 0">
                      <td colspan="2" class="text-center py-4 text-muted small">
                        No pending patients in current period
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </CCardBody>
          </CCard>
        </CCol>

        <!-- Aging Analysis -->
        <CCol md="6">
          <CCard class="border-0 shadow-sm h-100 premium-card overflow-hidden">
            <CCardHeader class="bg-white premium-header-mini d-flex justify-content-between">
              <span>Wait Time Analysis</span>
              <CIcon icon="cil-history" class="text-muted" />
            </CCardHeader>
            <CCardBody class="p-0">
              <div class="table-responsive" style="max-height: 400px">
                <table class="table table-hover align-middle mb-0">
                  <thead class="bg-light">
                    <tr>
                      <th class="ps-4">Encounter Date</th>
                      <th class="text-end">Age</th>
                      <th class="text-end pe-4">Qty</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="item in reportData.aging" :key="item.visit_date">
                      <td class="ps-4">{{ formatDate(item.visit_date) }}</td>
                      <td class="text-end">
                        <span class="text-warning fw-bold">{{ item.days_elapsed }}d</span>
                      </td>
                      <td class="text-end pe-4 fw-bold">{{ item.count }}</td>
                    </tr>
                    <tr v-if="reportData.aging.length === 0">
                      <td colspan="3" class="text-center py-4 text-muted small">
                        All encounter dates are within standard SLAs
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </CCardBody>
          </CCard>
        </CCol>
      </CRow>

      <!-- Detailed List Grouped by Clinic -->
      <CRow>
        <CCol md="12">
          <CCard class="border-0 shadow-sm premium-card overflow-hidden">
            <CCardHeader
              class="bg-white premium-header d-flex justify-content-between align-items-center py-3 px-4"
            >
              <div>
                <h5 class="mb-0 text-primary fw-bold">clinic data</h5>
                <small class="text-muted" style="font-weight: normal; font-size: 11px"
                  >(Will populate when provided)</small
                >
              </div>
              <span class="badge bg-danger-soft text-danger px-3 py-2">LIVE MONITORING</span>
            </CCardHeader>
            <CCardBody class="p-0">
              <div
                v-for="(clinicGroup, clinicName) in groupedReportData"
                :key="clinicName"
                class="clinic-section"
              >
                <div
                  class="clinic-header bg-light-primary px-4 py-2 border-bottom d-flex justify-content-between align-items-center"
                >
                  <div class="d-flex align-items-center">
                    <CIcon icon="cil-hospital" class="me-2 text-primary" />
                    <span class="fw-bold text-dark">{{ clinicName }}</span>
                  </div>
                  <span class="badge rounded-pill bg-primary px-3"
                    >{{ clinicGroup.length }} Pending</span
                  >
                </div>
                <div class="table-responsive">
                  <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th class="ps-4">Visit Date</th>
                        <th>MRN</th>
                        <th>Doctor Consulting</th>
                        <th class="text-center">Age</th>
                        <th>Diagnosis (Prov/Final)</th>
                        <th class="text-end pe-4">Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="visit in clinicGroup" :key="visit.id">
                        <td class="ps-4">
                          <CIcon icon="cil-calendar" size="sm" class="me-1 text-muted" />
                          {{ formatDate(visit.visit_date) }}
                        </td>
                        <td class="fw-semibold">{{ visit.mr_number }}</td>
                        <td>
                          <div class="d-flex flex-column">
                            <span class="fw-bold text-primary">{{ visit.doctor_name || '-' }}</span>
                            <small
                              class="text-muted text-uppercase tracking-wider"
                              style="font-size: 10px"
                              >{{ visit.doctor_code || 'No Code' }}</small
                            >
                          </div>
                        </td>
                        <td class="text-center">
                          <span v-if="visit.pat_age" class="badge bg-light text-dark px-2"
                            >{{ visit.pat_age }} yrs</span
                          >
                          <span v-else class="text-muted">-</span>
                        </td>
                        <td>
                          <div class="d-flex gap-2">
                            <div class="diag-badge prov" title="Provisional Diagnosis">
                              <small>P:</small> {{ visit.prov_diag || '-' }}
                            </div>
                            <div class="diag-badge final" title="Final Diagnosis">
                              <small>F:</small> {{ visit.final_diag || '-' }}
                            </div>
                          </div>
                        </td>
                        <td class="text-end pe-4">
                          <span class="status-indicator"></span>
                          <span class="text-warning fw-bold small">PENDING</span>
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <div v-if="reportData.list.length === 0" class="text-center py-5">
                <CIcon icon="cil-check-circle" size="3xl" class="text-success mb-3 opacity-25" />
                <h5 class="text-muted">Zero Pending Consultations</h5>
                <p class="text-muted small">
                  All patients have been cleared for the selected date range.
                </p>
              </div>
            </CCardBody>
          </CCard>
        </CCol>
      </CRow>
    </div>
  </div>
</template>

<style scoped>
.reports-container {
  background-color: #f8fafc;
  min-height: 100vh;
  margin: -1.5rem;
  padding: 1.5rem 2rem;
}

.text-gradient {
  background: linear-gradient(90deg, #003082 0%, #0072bc 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.premium-card {
  border-radius: 12px;
  background: white;
  transition: box-shadow 0.3s ease;
}

.premium-header {
  border-bottom: 2px solid #f1f5f9;
  color: #003082;
}

.premium-header-mini {
  font-weight: 700;
  font-size: 0.9rem;
  color: #475569;
  padding: 1.2rem 1.5rem;
}

.summary-card {
  border-radius: 12px;
  overflow: hidden;
}

.icon-box {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.bg-danger-soft {
  background-color: #fff1f2;
}

.bg-light-primary {
  background-color: #f0f7ff;
}

.diag-badge {
  padding: 2px 8px;
  border-radius: 4px;
  font-size: 11px;
  font-family: inherit;
  font-weight: 600;
  border: 1px solid #e2e8f0;
}

.diag-badge.prov {
  background: #f8fafc;
  color: #64748b;
}

.diag-badge.final {
  background: #eff6ff;
  color: #1d4ed8;
  border-color: #bfdbfe;
}

.status-indicator {
  display: inline-block;
  width: 8px;
  height: 8px;
  background-color: #f59e0b;
  border-radius: 50%;
  margin-right: 6px;
  box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2);
}

.border-start-4 {
  border-left-width: 4px !important;
}

.tracking-wider {
  letter-spacing: 0.05em;
}

.clinic-section:not(:last-child) {
  border-bottom: 1px solid #f1f5f9;
}
</style>
