<script setup>
import {
  ChartLine,
  ChartBar,
  ChartDoughnut,
  ChartRadar,
  ChartPie,
  ChartPolarArea,
  Gauges,
} from './index.js'
</script>

<template>
  <CRow>
    <!-- <CCol :md="6" class="mb-4">
      <CCard>
        <CCardHeader> Line Chart </CCardHeader>
        <CCardBody style="height: 400px">
          <ChartLine style="height: 100%; width: 100%" />
        </CCardBody>
      </CCard>
    </CCol>
    <CCol :md="6" class="mb-4">
      <CCard>
        <CCardHeader>Bar Chart</CCardHeader>
        <CCardBody style="height: 400px; display: flex; align-items: flex-end;">
          <ChartBar style="height: auto; width: 100%" /></CCardBody>
      </CCard>
    </CCol> -->
    <CCol :md="6" class="mb-4">
      <CCard>
        <CCardHeader>Doughnut Chart</CCardHeader>
        <CCardBody style="height: 400px"><ChartDoughnut style="height: 100%; width: 100%" /></CCardBody>
      </CCard>
    </CCol>
    <CCol :md="6" class="mb-4">
      <CCard>
        <CCardHeader>Radar Chart</CCardHeader>
        <CCardBody style="height: 400px"><ChartRadar style="height: 100%; width: 100%" /></CCardBody>
      </CCard>
    </CCol>
    <CCol :md="6" class="mb-4">
      <CCard>
        <CCardHeader>Pie Chart</CCardHeader>
        <CCardBody style="height: 400px"><ChartPie style="height: 100%; width: 100%" /></CCardBody>
      </CCard>
    </CCol>
    <CCol :md="6" class="mb-4">
      <CCard>
        <CCardHeader>Polar Area Chart</CCardHeader>
        <CCardBody style="height: 400px"><ChartPolarArea style="height: 100%; width: 100%" /></CCardBody>
      </CCard>
    </CCol>
    <CCol :md="12" class="mb-4">
      <CCard>
        <CCardHeader>Gauges Dashboard</CCardHeader>
        <CCardBody>
          <Gauges />
        </CCardBody>
      </CCard>
    </CCol>
  </CRow>
</template>
