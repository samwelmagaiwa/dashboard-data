<script setup>
import { computed, ref } from 'vue'
import { CChartPie } from '@coreui/vue-chartjs'
import { useDashboardStore } from '@/stores/dashboard'

const dashboard = useDashboardStore()

// State to track which categories are "hidden" (greyed out)
const hiddenCategories = ref([])

const pieData = computed(() => {
  const stats = dashboard.compStats || { labels: [], current: [], previous: [] }

  // Append values to labels for tooltips
  const labelsWithValues = stats.labels.map((label, index) => {
    const value = stats.current[index] || 0
    return `${label} (${value.toLocaleString()})`
  })

  // Calculate percentages
  const total = stats.current.reduce((a, b) => a + b, 0) || 1
  const percentages = stats.current.map((val) => ((val / total) * 100).toFixed(1) + '%')

  const categoryColorsMap = {
    FOREIGN: '#00E5FF', // Cyan
    PUBLIC: '#007bff',   // Blue
    NHIF: '#28a745',     // Green
    'IPPM-PRV': '#6f42c1', // Purple (matching Dashboard.vue)
    'IPPM-CRD': '#ffc107', // Amber
    'COST-SH': '#E12AFB',  // Magenta (user specified)
    NSSF: '#17a2b8',     // Teal
  }

  // Determine background colors (Pie slices)
  const backgroundColors = stats.labels.map((label) => {
    return categoryColorsMap[label] || '#333'
  })

  // Filter data: if hidden, value is 0 (so slice disappears).
  // Otherwise use actual value.
  const displayData = stats.current.map((val, index) => {
    if (hiddenCategories.value.includes(stats.labels[index])) return 0
    return val
  })

  return {
    labels: stats.labels,
    datasets: [
      {
        data: displayData,
        backgroundColor: backgroundColors,
        borderWidth: 2,
        borderColor: '#fff',
        hoverOffset: 10,
      },
    ],
    // Store percentages for the plugin to access (based on ORIGINAL total)
    _percentages: percentages,
  }
})

const chartOptions = computed(() => {
  return {
    plugins: {
      legend: {
        display: false, // Hide default legend, using custom one
      },
      tooltip: {
        callbacks: {
          label: (context) => {
            const label = context.chart.data.labels[context.dataIndex] || ''
            const value = context.raw || 0
            const total = context.chart._metasets[context.datasetIndex].total
            const percentage = ((value / total) * 100).toFixed(1) + '%'
            return `${label}: ${value} (${percentage})`
          },
        },
      },
    },
    maintainAspectRatio: false,
    layout: {
      padding: 20,
    },
  }
})

// Custom Plugin to draw text inside slices
const plugins = [
  {
    id: 'percentageLabels',
    afterDatasetsDraw(chart, args, options) {
      const { ctx } = chart
      const meta = chart.getDatasetMeta(0)

      ctx.save()
      ctx.font = "bold 22px 'Outfit', sans-serif"
      ctx.fillStyle = '#fff'
      ctx.textAlign = 'center'
      ctx.textBaseline = 'middle'

      meta.data.forEach((element, index) => {
        if (element.hidden) return

        // Check if slice is hidden by legend logic
        const label = chart.data.labels[index]
        if (hiddenCategories.value.includes(label)) return

        const value = chart.data.datasets[0].data[index]
        if (!value || value === 0) return

        const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0)
        const percentage = total > 0 ? ((value / total) * 100).toFixed(1) + '%' : '0.0%'

        // Calculate position
        const model = element
        // Center of the slice arc
        const midRadius = model.outerRadius * 0.7 + model.innerRadius * 0.3
        const midAngle = model.startAngle + (model.endAngle - model.startAngle) / 2
        const x = Math.cos(midAngle) * midRadius + model.x
        const y = Math.sin(midAngle) * midRadius + model.y

        // Add shadow for readability on light colors
        ctx.shadowColor = 'rgba(0,0,0,0.6)'
        ctx.shadowBlur = 4

        // Draw Value and Percentage
        ctx.fillText(value.toLocaleString(), x, y - 10)
        ctx.font = "normal 16px 'Outfit', sans-serif"
        ctx.fillText(percentage, x, y + 12)
        ctx.font = "bold 22px 'Outfit', sans-serif" // Reset
      })
      ctx.restore()
    },
  },
]

const handleLegendClick = (cat) => {
  if (hiddenCategories.value.includes(cat)) {
    hiddenCategories.value = hiddenCategories.value.filter((c) => c !== cat)
  } else {
    hiddenCategories.value.push(cat)
  }
}

// Custom Legend Items
const legendItems = computed(() => {
  const map = {
    FOREIGN: '#00E5FF', // Cyan
    PUBLIC: '#007bff',   // Blue
    NHIF: '#28a745',     // Green
    'IPPM-PRV': '#6f42c1', // Purple (matching Dashboard.vue)
    'IPPM-CRD': '#ffc107', // Amber
    'COST-SH': '#E12AFB',  // Magenta (user specified)
    NSSF: '#17a2b8',     // Teal
  }

  // Hex to RGBA helper
  const hexToRgba = (hex, alpha) => {
    const r = parseInt(hex.slice(1, 3), 16)
    const g = parseInt(hex.slice(3, 5), 16)
    const b = parseInt(hex.slice(5, 7), 16)
    return `rgba(${r}, ${g}, ${b}, ${alpha})`
  }

  return Object.keys(map).map((key) => {
    const isHidden = hiddenCategories.value.includes(key)
    const color = map[key]
    return {
      text: key,
      style: {
        backgroundColor: isHidden ? 'transparent' : hexToRgba(color, 0.2),
        borderColor: isHidden ? '#ccc' : color,
        borderWidth: '3px',
        color: isHidden ? '#999' : '#333',
        cursor: 'pointer',
        padding: '5px 10px',
        borderRadius: '20px',
        margin: '0 5px 5px 0',
        display: 'inline-block',
        fontSize: '11px',
        fontWeight: 'bold',
        borderStyle: 'solid',
        userSelect: 'none',
      },
    }
  })
})
</script>

<template>
  <div class="card h-100 border-0 shadow-sm">
    <div class="card-header bg-transparent border-0 font-weight-bold">
      <!-- Custom Legend Row -->
      <div class="d-flex flex-wrap justify-content-center mb-2 mt-3">
        <div
          v-for="item in legendItems"
          :key="item.text"
          :style="item.style"
          @click="handleLegendClick(item.text)"
        >
          {{ item.text }}
        </div>
      </div>
      <h6 class="mb-2 text-start fw-bold">Patient Category analytics</h6>
    </div>
    <div
      class="card-body position-relative"
      style="min-height: 520px; height: clamp(420px, 50vh, 640px)"
    >
      <CChartPie :data="pieData" :options="chartOptions" :plugins="plugins" style="height: 100%" />
    </div>
  </div>
</template>
