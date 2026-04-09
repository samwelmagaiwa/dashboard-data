<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { useDashboardStore } from '@/stores/dashboard'
import {
  CCard,
  CCardHeader,
  CCardBody,
  CRow,
  CCol,
  CSpinner,
  CButton,
  CInputGroup,
  CInputGroupText,
  CFormInput,
  CFormSelect,
} from '@coreui/vue'
import CIcon from '@coreui/icons-vue'
import {
  cilPeople,
  cilCheckCircle,
  cilReload,
  cilArrowLeft,
  cilSearch,
  cilXCircle,
  cilChevronLeft,
  cilChevronRight,
  cilChevronTop,
  cilChevronBottom,
} from '@coreui/icons'

const dashboard = useDashboardStore()

// More Clinics Data State
const isClinicsVisible = ref(true)
const currentPage = ref(1)
const pageSize = ref(500) // Default to 500 as per user request
const pageSizeOptions = [10, 25, 50, 100, 150, 200, 500]

// Filter detailed clinic visits based on search term (from store)
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
  if (filteredDetailedClinics.value.length === 0) return 1
  return Math.ceil(filteredDetailedClinics.value.length / pageSize.value)
})

const clinicStats = computed(() => {
  if (!dashboard.isInitialized) return { matches: '-', mismatches: '-', total: '-' }
  const snapshotStats = dashboard.realStats

  if (snapshotStats && snapshotStats.total_detailed > 0) {
    return {
      matches: snapshotStats.matched_count || 0,
      mismatches: snapshotStats.mismatched_count || 0,
      total: snapshotStats.total_detailed || 0,
    }
  }

  const detailed = filteredDetailedClinics.value
  let matches = 0
  let mismatches = 0

  detailed.forEach((item) => {
    if (isDoctorMismatch(item.bill_doct_name, item.cons_doctor_name)) {
      mismatches++
    } else {
      matches++
    }
  })

  return { matches, mismatches, total: detailed.length }
})

const isDoctorMismatch = (billed, attended) => {
  if (!billed || !attended) return false
  return billed.trim().toLowerCase() !== attended.trim().toLowerCase()
}

const getVisitTypeBadge = (type) => {
  return type === 'N' ? 'primary' : 'info'
}

const getVisitTypeLabel = (type) => {
  return type === 'N' ? 'New' : 'Followup'
}

const formatDate = (dateStr) => {
  if (!dateStr) return 'N/A'
  if (typeof dateStr === 'string' && dateStr.includes('T')) {
    return dateStr.split('T')[0]
  }
  return dateStr
}

const refreshData = async () => {
  await dashboard.fetchStats()
}

// Watch range changes from dashboard store
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
    currentPage.value = 1
  },
  { deep: true },
)

watch(
  () => dashboard.searchTerm,
  () => {
    currentPage.value = 1
  },
)

onMounted(() => {
  if (!dashboard.isInitialized) {
    refreshData()
  }
})
</script>

<template>
  <div class="reports-container">
    <!-- Top Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4 pt-2">
      <div>
        <h3 class="fw-bold text-gradient mb-1">Clinic Visit Detailed Report</h3>
        <p class="text-muted small mb-0">Analysis of clinic visits, matching, and doctor consultations</p>
      </div>
      <div class="d-flex gap-2">
        <CButton color="white" class="shadow-sm px-3 border" @click="$router.push('/dashboard')">
          <CIcon :icon="cilArrowLeft" class="me-2" />
          Dashboard
        </CButton>
        <CButton
          color="primary"
          class="shadow-sm px-3 fw-semibold"
          @click="refreshData"
          :disabled="dashboard.isLoading"
        >
          <CIcon :icon="cilReload" class="me-2" :class="{ 'fa-spin': dashboard.isLoading }" />
          Refresh
        </CButton>
      </div>
    </div>

    <!-- Redesigned Migrated Section -->
    <CRow class="mb-4">
      <CCol :md="12">
        <CCard class="border-0 shadow-lg overflow-hidden premium-wrapper">
          <CCardHeader
            class="bg-white d-flex justify-content-between align-items-center cursor-pointer py-3 px-4 border-0"
            @click="isClinicsVisible = !isClinicsVisible"
          >
            <div class="d-flex align-items-center flex-grow-1">
              <div class="fw-bold me-3 text-nowrap title-accent" style="font-size: 1.4rem">
                Click here to view More Clinics Data
              </div>
              <!-- Integrated Search Field -->
              <div class="flex-grow-1 mx-3" style="max-width: 400px" @click.stop>
                <CInputGroup size="sm" class="premium-search shadow-sm">
                  <CInputGroupText class="bg-white border-end-0">
                    <CIcon :icon="cilSearch" class="text-primary" />
                  </CInputGroupText>
                  <CFormInput
                    v-model="dashboard.searchTerm"
                    placeholder="Search MR Number, Clinic, or Doctor..."
                    class="border-start-0"
                    style="box-shadow: none"
                  />
                </CInputGroup>
              </div>
              <!-- Totals Section - Redesigned as Premium Chips -->
              <div class="d-flex align-items-center text-nowrap ms-2 gap-3">
                <div class="stat-chip chip-success shadow-sm">
                  <CIcon :icon="cilCheckCircle" size="lg" class="me-2" />
                  <div class="d-flex flex-column">
                    <span class="chip-label">Matched</span>
                    <strong class="chip-value">{{ clinicStats.matches }}</strong>
                  </div>
                </div>
                <div class="stat-chip chip-danger shadow-sm">
                  <CIcon :icon="cilXCircle" size="lg" class="me-2" />
                  <div class="d-flex flex-column">
                    <span class="chip-label">Mismatched</span>
                    <strong class="chip-value">{{ clinicStats.mismatches }}</strong>
                  </div>
                </div>
                <div class="stat-chip chip-info shadow-sm">
                  <CIcon :icon="cilPeople" size="lg" class="me-2" />
                  <div class="d-flex flex-column">
                    <span class="chip-label">Total Records</span>
                    <strong class="chip-value">{{ clinicStats.total }}</strong>
                  </div>
                </div>
              </div>
            </div>
            <div class="d-flex align-items-center ms-4">
              <!-- Page Size Selector -->
              <div class="me-4" @click.stop>
                <CFormSelect
                  size="sm"
                  v-model="pageSize"
                  class="premium-select border-0 shadow-sm"
                  style="width: 120px; cursor: pointer"
                >
                  <option v-for="option in pageSizeOptions" :key="option" :value="option">
                    {{ option }} / page
                  </option>
                </CFormSelect>
              </div>
              <CIcon :icon="isClinicsVisible ? cilChevronTop : cilChevronBottom" size="xl" class="text-primary" />
            </div>
          </CCardHeader>
          
          <div v-show="isClinicsVisible" class="collapse-content">
            <CCardBody class="p-4 pt-0">
              <!-- PREMIUM DATA TABLE - Image Inspired Design -->
              <div class="table-responsive premium-scroll" style="max-height: 700px">
                <table class="table premium-table">
                  <thead class="sticky-top">
                    <tr>
                      <!-- Header Color Groups from Image -->
                      <th class="header-corner">S/NO</th>
                      <th class="header-dark-blue">MR Number</th>
                      <th class="header-dark-blue">Gender</th>
                      <th class="header-dark-blue text-center">Age</th>
                      <th class="header-dark-blue text-center">Type</th>
                      <th class="header-dark-blue">Date</th>
                      <th class="header-dark-blue">Time</th>
                      <th class="header-red">Dr Code</th>
                      <th class="header-red">Bill Doctor (Cashier)</th>
                      <th class="header-red">Attend Doctor</th>
                      <th class="header-dark-blue" style="min-width: 150px">Clinic Name</th>
                      <th class="header-dark-blue text-center">Code</th>
                      <th class="header-dark-blue" style="max-width: 220px">Diagnosis</th>
                      <th class="header-dark-blue text-center pe-3" style="width: 70px">Mismatch</th>
                    </tr>
                  </thead>
                  <tbody :class="{ 'opacity-25': dashboard.isLoading || dashboard.isDetailedLoading }">
                    <tr v-for="(item, index) in paginatedDetailedClinics" :key="index">
                      <!-- Side Header Gradient for S/NO -->
                      <td class="side-header text-center">
                        {{ (currentPage - 1) * pageSize + index + 1 }}
                      </td>
                      <td class="fw-extrabold text-blue-deep">{{ item.mr_number }}</td>
                      <td class="text-center">
                        <span class="gender-pill" :class="item.gender === 'M' ? 'pill-m' : 'pill-f'">
                          {{ item.gender }}
                        </span>
                      </td>
                      <td class="text-center fw-bold">{{ item.pat_age }}</td>
                      <td class="text-center">
                        <span class="type-pill" :class="item.visit_type === 'N' ? 'pill-new' : 'pill-follow'">
                          {{ getVisitTypeLabel(item.visit_type) }}
                        </span>
                      </td>
                      <td class="text-nowrap fw-semibold">{{ formatDate(item.visit_date) }}</td>
                      <td class="text-muted">{{ item.cons_time }}</td>
                      <td><code>{{ item.doct_code }}</code></td>
                      <td class="fw-bold small">{{ item.bill_doct_name || 'Name is empty' }}</td>
                      <td class="fw-bold small">{{ item.cons_doctor_name || 'Name is empty' }}</td>
                      <td class="fw-bold clinic-name">{{ item.clinic_name }}</td>
                      <td class="text-center">
                        <span class="clinic-code">{{ item.clinic_code }}</span>
                      </td>
                      <td class="diagnosis-cell">
                        <div v-if="item.final_diag || item.prov_diag" class="diag-stack">
                          <div v-if="item.final_diag" class="diag-item diag-final">
                            <span class="diag-label">FINAL</span> {{ item.final_diag }}
                          </div>
                          <div v-if="item.prov_diag" class="diag-item diag-prov">
                            <span class="diag-label">PROV</span> {{ item.prov_diag }}
                          </div>
                        </div>
                        <div v-else class="text-muted italic small opacity-50">No Diagnosis</div>
                      </td>
                      <td class="text-center pe-4">
                        <div class="status-indicator shadow-sm" 
                             :class="!isDoctorMismatch(item.bill_doct_name, item.cons_doctor_name) ? 'status-match' : 'status-mismatch'">
                          <CIcon
                            :icon="!isDoctorMismatch(item.bill_doct_name, item.cons_doctor_name) ? cilCheckCircle : cilXCircle"
                            size="lg"
                          />
                        </div>
                      </td>
                    </tr>
                    <tr v-if="!dashboard.isDetailedLoading && filteredDetailedClinics.length === 0">
                      <td colspan="14" class="text-center py-5 no-data">
                        <CIcon :icon="cilSearch" size="3xl" class="text-muted mb-3 d-block mx-auto opacity-25" />
                        No records found for the selected criteria.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <!-- Pagination Footer - Premium Design -->
              <div
                v-if="totalPages > 1"
                class="d-flex justify-content-between align-items-center p-4 mt-3 premium-footer rounded-bottom"
              >
                <div class="small fw-bold text-muted">
                  Showing <span class="text-primary">{{ (currentPage - 1) * pageSize + 1 }}</span> to
                  <span class="text-primary">{{ Math.min(currentPage * pageSize, filteredDetailedClinics.length) }}</span>
                  of <span class="text-primary">{{ filteredDetailedClinics.length }}</span> clinical records
                </div>
                
                <div class="d-flex align-items-center gap-2">
                  <CButton
                    size="sm"
                    color="light"
                    class="pagination-btn"
                    :disabled="currentPage === 1"
                    @click="currentPage--"
                  >
                    <CIcon :icon="cilChevronLeft" />
                  </CButton>

                  <div class="d-flex align-items-center px-3 page-selector-wrap">
                    <span class="small me-2">PAGE</span>
                    <CFormSelect
                      size="sm"
                      v-model="currentPage"
                      class="page-select-dropdown"
                    >
                      <option v-for="page in totalPages" :key="page" :value="page">
                        {{ page }}
                      </option>
                    </CFormSelect>
                    <span class="small ms-2">OF {{ totalPages }}</span>
                  </div>

                  <CButton
                    size="sm"
                    color="light"
                    class="pagination-btn"
                    :disabled="currentPage === totalPages"
                    @click="currentPage++"
                  >
                    <CIcon :icon="cilChevronRight" />
                  </CButton>
                </div>
              </div>
            </CCardBody>
          </div>
        </CCard>
      </CCol>
    </CRow>
  </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;700;800&display=swap');

.reports-container {
  background-color: #f0f3f6;
  min-height: 100vh;
  margin: -1.5rem;
  padding: 1.5rem 2rem;
  font-family: 'Outfit', sans-serif;
}

.text-gradient {
  background: linear-gradient(135deg, #003082 0%, #0072bc 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
}

.premium-wrapper {
  border-radius: 20px !important;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08) !important;
}

.title-accent {
  color: #1a202c;
  letter-spacing: -0.5px;
}

/* Premium Search Field */
.premium-search {
  border: 1px solid #e2e8f0;
  border-radius: 12px !important;
  overflow: hidden;
  background: #f8fafc;
}
.premium-search .form-control {
  background: transparent;
  padding: 0.6rem 0.5rem;
  font-weight: 500;
}

/* Stat Chips */
.stat-chip {
  display: flex;
  align-items: center;
  padding: 0.6rem 1.25rem;
  border-radius: 14px;
  background: white;
  min-width: 140px;
  border: 1px solid #edf2f7;
}
.chip-label {
  font-size: 10px;
  text-transform: uppercase;
  font-weight: 700;
  color: #718096;
  line-height: 1;
  margin-bottom: 2px;
}
.chip-value {
  font-size: 1.4rem;
  line-height: 1;
  color: #2d3748;
}
.chip-success { border-left: 4px solid #48bb78; }
.chip-success .text-primary { color: #48bb78 !important; }
.chip-danger { border-left: 4px solid #f56565; }
.chip-info { border-left: 4px solid #4299e1; }

.premium-select {
  background-color: #f8fafc;
  border-radius: 10px !important;
  font-weight: 600;
  color: #4a5568;
}

/* IMAGE INSPIRED TABLE STYLING */
.premium-table {
  border-collapse: separate;
  border-spacing: 0 8px; /* Vertical gap between rows like in image */
  margin-top: -8px;
}

/* Header Gradients from Image */
.premium-table thead th {
  padding: 1.25rem 1rem;
  font-size: 11px;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border: none;
  vertical-align: middle;
}

.header-corner { background: #f8fafc; color: #4a5568; border-radius: 12px 0 0 0; text-align: center; }
.header-blue { background: linear-gradient(to bottom, #42a5f5, #1e88e5); color: white; }
.header-orange { background: linear-gradient(to bottom, #ffa726, #fb8c00); color: white; }
.header-red { background: linear-gradient(to bottom, #ef5350, #e53935); color: white; }
.header-dark-blue { background: linear-gradient(to bottom, #1e3a8a, #1e3a8a); color: white; }
.header-dark-blue:last-child { border-radius: 0 12px 0 0; }

/* Table Body Spacing and Look */
.premium-table tbody tr {
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.02);
  transition: all 0.2s ease;
}

.premium-table tbody tr:hover {
  transform: scale(1.002);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
}

.premium-table tbody td {
  background: #ffffff;
  padding: 0.65rem 0.5rem;
  border-top: 1px solid #f1f5f9;
  border-bottom: 1px solid #f1f5f9;
  font-weight: 500;
  color: #4a5568;
  line-height: 1.3;
}

.premium-table tbody tr:nth-child(even) td {
  background: #f8fafc; /* Attractive subtle secondary row color */
}

/* Side Header Gradient for S/NO */
.side-header {
  background: linear-gradient(to right, #2563eb, #1d4ed8) !important;
  color: white !important;
  font-weight: 800 !important;
  border-radius: 12px 0 0 12px !important;
  box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
  min-width: 50px;
  width: 55px;
}

.premium-table tbody tr:nth-child(even) .side-header {
  background: linear-gradient(to right, #1d4ed8, #1e40af) !important;
}

/* Cell Specific Styles */
.fw-extrabold { font-weight: 800; }
.text-blue-deep { color: #1e3a8a; }

.gender-pill {
  padding: 4px 10px;
  border-radius: 8px;
  font-weight: 800;
  font-size: 11px;
}
.pill-m { background: #ebf8ff; color: #3182ce; }
.pill-f { background: #fff5f5; color: #e53e3e; }

.type-pill {
  padding: 4px 10px;
  border-radius: 8px;
  font-weight: 700;
  font-size: 10px;
  text-transform: uppercase;
}
.pill-new { background: #e6fffa; color: #319795; }
.pill-follow { background: #f0f5ff; color: #5a67d8; }

.clinic-name { color: #1a202c; letter-spacing: -0.2px; }
.clinic-code {
  background: #edf2f7;
  padding: 2px 8px;
  border-radius: 6px;
  font-family: monospace;
  font-weight: 700;
  color: #2d3748;
}

.diagnosis-cell {
  max-width: 200px;
  white-space: normal;
}

.diag-stack { 
  display: flex; 
  flex-direction: column; 
  gap: 4px; 
  align-items: flex-start; /* Ensure tags only fit text width */
}
.diag-item {
  padding: 4px 8px;
  border-radius: 6px;
  font-size: 13px; /* Increased for better visibility */
  border: 1px solid #e2e8f0;
  line-height: 1.2;
  margin-bottom: 3px;
  font-weight: 600;
  white-space: nowrap;
}
.diag-final { background: #f0fdf4; border-color: #bbf7d0; color: #166534; }
.diag-prov { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
.diag-label {
  font-size: 8px;
  font-weight: 900;
  padding: 1px 4px;
  border-radius: 3px;
  background: rgba(0,0,0,0.1);
  margin-right: 4px;
}

/* Status Indicator */
.status-indicator {
  width: 32px;
  height: 32px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  margin: 0 auto;
}
.status-match { background: #f0fdf4; color: #48bb78; }
.status-mismatch { background: #fff5f5; color: #f56565; }

/* Pagination Styling */
.premium-footer { background: #f8fafc; border-top: 1px solid #edf2f7; }
.pagination-btn {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 8px;
  padding: 0.5rem;
  color: #4a5568;
}
.page-selector-wrap {
  background: white;
  border: 1px solid #e2e8f0;
  border-radius: 10px;
  padding: 4px 12px;
  display: flex;
  align-items: center;
  white-space: nowrap; /* Prevent "OF XXX" from wrapping */
  min-width: 140px;
}
.page-select-dropdown {
  border: none;
  background: transparent;
  width: 65px; /* Explicit width to give space for numbers */
  font-weight: 800;
  color: #1a202c;
  padding-right: 15px;
  text-align: center;
}

/* Custom Scrollbar */
.premium-scroll::-webkit-scrollbar { width: 6px; height: 6px; }
.premium-scroll::-webkit-scrollbar-track { background: #f8fafc; }
.premium-scroll::-webkit-scrollbar-thumb { background: #cbd5e0; border-radius: 10px; }
</style>
