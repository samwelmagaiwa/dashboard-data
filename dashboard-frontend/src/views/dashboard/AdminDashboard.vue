<script setup>
import { defineAsyncComponent, computed, onMounted } from 'vue'
import { useDashboardStore } from '@/stores/dashboard'
import { useRouter } from 'vue-router'
import LoadingBanner from '@/components/LoadingBanner.vue'
import { ChartLine, ChartBar } from '../charts/index.js'
import { CIcon } from '@coreui/icons-vue'

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
        <div class="badge bg-primary-gradient px-3 py-2" style="font-size: 0.9rem">
          <span class="fw-bold">{{ dashboard.user?.role }}</span> Access
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
          <CCard class="h-100 border-0 shadow-sm">
            <CCardHeader class="bg-white">Attendance VS Deaths</CCardHeader>
            <CCardBody style="height: 350px">
              <ChartLine
                :data="dashboard.lineChartData"
                :options="chartOptions"
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
</style>
