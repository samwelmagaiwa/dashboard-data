<script setup>
import { onMounted, ref, watch } from 'vue'
import { useColorModes } from '@coreui/vue'
import { CFormSelect, CFormInput, CButton } from '@coreui/vue'
import AppBreadcrumb from '@/components/AppBreadcrumb.vue'
import AppHeaderDropdownAccnt from '@/components/AppHeaderDropdownAccnt.vue'
import { useSidebarStore } from '@/stores/sidebar.js'
import { useDashboardStore } from '@/stores/dashboard'
import VueDatePicker from '@vuepic/vue-datepicker'
import '@vuepic/vue-datepicker/dist/main.css'
import * as XLSX from 'xlsx'

const dashboard = useDashboardStore()
const periodOptions = [
  { value: 'day', label: 'Day' },
  { value: 'week', label: 'Week' },
  { value: 'month', label: 'Month' },
  { value: 'year', label: 'Year' },
  { value: 'range', label: 'Select by Range' },
]

const filterMode = ref(dashboard.selectedPeriod)
// Local refs only for UI state that doesn't belong in store
const modalVisible = ref(false)

const handleSync = async () => {
  if (!confirm('Sync data for the selected date?')) return

  try {
    const res = await dashboard.syncCurrentRange()
    const dateMsg = res?.date ? `\nDate: ${res.date}` : ''
    alert('Sync completed successfully! The dashboard will now refresh.' + dateMsg)
  } catch (error) {
    alert('Sync failed. Please check the console for details.')
  }
}

const handleExport = () => {
  modalVisible.value = false
  const lineData = dashboard.lineChartData
  const barData = dashboard.barChartData
  const wb = XLSX.utils.book_new()

  const patientCategories = dashboard.metrics.filter((m) =>
    [
      'PUBLIC',
      'NHIF',
      'IPPM - PRIVATE',
      'IPPM - CREDIT',
      'COST SHARING',
      'NSSF',
      'FOREIGNER',
    ].includes(m.title),
  )
  const categoriesSheet = XLSX.utils.json_to_sheet(
    patientCategories.map((item) => ({ Category: item.title, Value: item.value })),
  )
  XLSX.utils.book_append_sheet(wb, categoriesSheet, 'Patient Categories')
  const bedsData = dashboard.metrics.filter((m) =>
    ['Occupied Beds', 'Free Beds', 'Total Beds'].includes(m.title),
  )
  const bedsSheet = XLSX.utils.json_to_sheet(
    bedsData.map((item) => ({ Metric: item.title, Value: item.value })),
  )
  XLSX.utils.book_append_sheet(wb, bedsSheet, 'Beds')
  const dischargesData = dashboard.metrics.filter((m) =>
    ['Discharges', 'LIVE', 'DEAD'].includes(m.title),
  )
  const dischargesSheet = XLSX.utils.json_to_sheet(
    dischargesData.map((item) => ({ Metric: item.title, Value: item.value })),
  )
  XLSX.utils.book_append_sheet(wb, dischargesSheet, 'Discharges')
  const lineSheet = XLSX.utils.json_to_sheet(
    lineData.labels.map((label, index) => ({
      Period: label,
      'ON TIME': lineData.datasets[0].data[index],
      LATE: lineData.datasets[1].data[index],
      DEATH: lineData.datasets[2].data[index],
    })),
  )
  XLSX.utils.book_append_sheet(wb, lineSheet, 'staff attendance VS deaths')
  const barSheet = XLSX.utils.json_to_sheet(
    barData.labels.map((label, index) => ({
      Period: label,
      Admission: barData.datasets[0].data[index],
      'Discharges Live': barData.datasets[1].data[index],
      'Discharges Dead': barData.datasets[2].data[index],
    })),
  )
  XLSX.utils.book_append_sheet(wb, barSheet, 'Discharges VS Admissions')
  XLSX.writeFile(wb, `report_${dashboard.selectedPeriod}.xlsx`)
}

// onMounted(() => {
//   dayDate.value = dashboard.selectedDay
//   weekDate.value = dashboard.selectedWeek
//   monthDate.value = dashboard.selectedMonth
//   yearDate.value = dashboard.selectedYear
//   rangeDate.value = dashboard.selectedRange
// })

watch(filterMode, (newVal) => {
  dashboard.selectedPeriod = newVal
})

watch(
  () => dashboard.selectedPeriod,
  (newVal) => {
    filterMode.value = newVal
  },
)

// Watcher removed because we bind directly now

const headerClassNames = ref('mb-0 p-0')
const { colorMode, setColorMode } = useColorModes('coreui-free-vue-admin-template-theme')
const sidebar = useSidebarStore()

const handleSidebarToggle = () => {
  if (window.innerWidth < 992) {
    sidebar.toggleVisible()
    return
  }
  sidebar.toggleNarrow()
}

onMounted(() => {
  document.addEventListener('scroll', () => {
    if (document.documentElement.scrollTop > 0) {
      headerClassNames.value = 'mb-0 p-0 shadow-sm'
    } else {
      headerClassNames.value = 'mb-0 p-0'
    }
  })
})

const formatWeek = (date) => {
  if (!date) return ''

  let start, end

  try {
    if (Array.isArray(date) && date.length === 2) {
      ;[start, end] = date
    } else if (date instanceof Date) {
      // Drive week start/end from single date (Monday-Sunday)
      const d = new Date(date)
      const day = d.getDay() // 0-6 (Sun-Sat)
      const diff = d.getDate() - day + (day === 0 ? -6 : 1) // Adj to Monday
      start = new Date(d.setDate(diff))
      end = new Date(start)
      end.setDate(start.getDate() + 6)
    } else {
      // Fallback for unexpected formats (e.g. ISO strings)
      return ''
    }

    if (!start || !end) return ''

    const options = { day: '2-digit', month: 'short' }
    return `${start.toLocaleDateString('en-US', options)} - ${end.toLocaleDateString('en-US', options)}`
  } catch (e) {
    console.error('Date formatting error:', e)
    return ''
  }
}
</script>

<template>
  <CHeader position="sticky" :class="headerClassNames" style="z-index: 1040">
    <CContainer class="border-bottom px-4" fluid style="flex-wrap: wrap; gap: 4px">
      <CHeaderToggler @click="handleSidebarToggle" style="margin-inline-start: -14px">
        <CIcon icon="cil-menu" size="lg" />
      </CHeaderToggler>
      <CHeaderNav class="d-none d-md-flex">
        <CNavItem>
          <CNavLink href="/dashboard"> Dashboard </CNavLink>
        </CNavItem>
        <!-- <CNavItem>
          <CNavLink href="#">Users</CNavLink>
        </CNavItem>
        <CNavItem>
          <CNavLink href="#">Settings</CNavLink>
        </CNavItem> -->
      </CHeaderNav>
      <CHeaderNav
        class="d-flex align-items-center flex-wrap gap-1"
        style="flex: 1 1 auto; justify-content: flex-end; padding: 4px 0"
      >
        <CFormSelect v-model="filterMode" :options="periodOptions" style="width: 120px" size="sm" />

        <!-- Navigation Buttons -->
        <div v-if="filterMode !== 'range'" class="d-flex align-items-center gap-1">
          <CButton color="light" variant="outline" size="sm" @click="dashboard.jumpDate('prev')">
            <CIcon icon="cil-chevron-left" />
          </CButton>
          <CButton color="light" variant="outline" size="sm" @click="dashboard.resetToToday">
            Today
          </CButton>
          <CButton color="light" variant="outline" size="sm" @click="dashboard.jumpDate('next')">
            <CIcon icon="cil-chevron-right" />
          </CButton>
        </div>

        <VueDatePicker
          v-if="filterMode === 'day'"
          v-model="dashboard.selectedDay"
          format="dd/MM/yyyy"
          auto-apply
          :enable-time-picker="false"
          placeholder="Select day"
          style="width: 150px"
        />
        <VueDatePicker
          v-if="filterMode === 'week'"
          v-model="dashboard.selectedWeek"
          week-picker
          :enable-time-picker="false"
          :week-start="1"
          teleport="body"
          placeholder="Select week"
          :format="formatWeek"
          style="width: 180px"
        />
        <VueDatePicker
          v-if="filterMode === 'month'"
          v-model="dashboard.selectedMonth"
          month-picker
          auto-apply
          format="MMMM yyyy"
          placeholder="Select month"
          style="width: 150px"
        />
        <VueDatePicker
          v-if="filterMode === 'year'"
          v-model="dashboard.selectedYear"
          year-picker
          auto-apply
          placeholder="Select year"
          style="width: 120px"
        />
        <VueDatePicker
          v-if="filterMode === 'range'"
          v-model="dashboard.selectedRange"
          range
          auto-apply
          placeholder="Select date range"
          style="width: 200px"
        />
        <CButton color="primary" size="sm" @click="handleSync" :disabled="dashboard.isLoading">
          <CIcon icon="cil-sync" class="me-1" :class="{ 'fa-spin': dashboard.isLoading }" />
          {{ dashboard.isLoading ? 'Syncing...' : 'Sync' }}
        </CButton>
        <CButton color="warning" size="sm" variant="outline" @click="modalVisible = true"
          >Export</CButton
        >
      </CHeaderNav>
      <CModal
        :visible="modalVisible"
        @close="() => (modalVisible = false)"
        backdrop="static"
        keyboard="false"
      >
        <CModalHeader>
          <CIcon icon="cil-spreadsheet" class="text-primary me-2" />
          Confirm Export
        </CModalHeader>
        <CModalBody class="text-center">
          <CIcon icon="cil-spreadsheet" size="xxl" class="mb-3 text-primary" />
          <p class="fs-5">
            Are you sure you want to export this data to Excel for the current period ({{
              dashboard.selectedPeriod
            }})?
          </p>
          <small class="text-muted"
            >This action will generate an Excel file with the current dashboard data.</small
          >
        </CModalBody>
        <CModalFooter>
          <CButton color="danger" variant="outline" @click="modalVisible = false">
            <CIcon icon="cil-x" class="me-1" />
            Cancel
          </CButton>
          <CButton color="primary" @click="handleExport">
            <CIcon icon="cil-check" class="me-1" />
            Yes, Export
          </CButton>
        </CModalFooter>
      </CModal>
      <!-- <CHeaderNav class="ms-auto">
        <CNavItem>
          <CNavLink href="#">
            <CIcon icon="cil-bell" size="lg" />
          </CNavLink>
        </CNavItem>
        <CNavItem>
          <CNavLink href="#">
            <CIcon icon="cil-list" size="lg" />
          </CNavLink>
        </CNavItem>
        <CNavItem>
          <CNavLink href="#">
            <CIcon icon="cil-envelope-open" size="lg" />
          </CNavLink>
        </CNavItem>
      </CHeaderNav> -->
      <CHeaderNav>
        <li class="nav-item py-1">
          <div class="vr h-100 mx-2 text-body text-opacity-75"></div>
        </li>
        <CDropdown variant="nav-item" placement="bottom-end">
          <CDropdownToggle :caret="false">
            <CIcon v-if="colorMode === 'dark'" icon="cil-moon" size="lg" />
            <CIcon v-else-if="colorMode === 'light'" icon="cil-sun" size="lg" />
            <CIcon v-else icon="cil-contrast" size="lg" />
          </CDropdownToggle>
          <CDropdownMenu>
            <CDropdownItem
              :active="colorMode === 'light'"
              class="d-flex align-items-center"
              component="button"
              type="button"
              @click="setColorMode('light')"
            >
              <CIcon class="me-2" icon="cil-sun" size="lg" /> Light
            </CDropdownItem>
            <CDropdownItem
              :active="colorMode === 'dark'"
              class="d-flex align-items-center"
              component="button"
              type="button"
              @click="setColorMode('dark')"
            >
              <CIcon class="me-2" icon="cil-moon" size="lg" /> Dark
            </CDropdownItem>
            <CDropdownItem
              :active="colorMode === 'auto'"
              class="d-flex align-items-center"
              component="button"
              type="button"
              @click="setColorMode('auto')"
            >
              <CIcon class="me-2" icon="cil-contrast" size="lg" /> Auto
            </CDropdownItem>
          </CDropdownMenu>
        </CDropdown>
        <li class="nav-item py-1">
          <div class="vr h-100 mx-2 text-body text-opacity-75"></div>
        </li>
        <AppHeaderDropdownAccnt />
      </CHeaderNav>
    </CContainer>
    <!-- Removed Breadcrumb to minimize top gap -->
    <!-- <CContainer class="px-4" fluid>
      <AppBreadcrumb />
    </CContainer> -->
  </CHeader>
</template>
