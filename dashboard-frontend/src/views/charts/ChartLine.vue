<script setup>
import { CChart } from '@coreui/vue-chartjs'

const props = defineProps({
  data: {
    type: Object,
    required: true,
  },
  options: {
    type: Object,
    default: () => ({
      maintainAspectRatio: false,
    }),
  },
})
</script>

<template>
  <CChart type="line" :data="props.data" :options="props.options" />
</template>
