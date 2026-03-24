<script setup>
import { ref } from 'vue'
const patientCategories = ref([
  {
    title: 'Foreigner',
    value: '100',
    titleClass: 'fw-semibold text-body-secondary',
    valueClass: 'text-nowrap',
    color: '#e83e8c',
  },
  {
    title: 'Public',
    value: '1000',
    titleClass: 'fw-semibold text-body-secondary',
    valueClass: 'text-nowrap',
    color: '#007bff',
  },
  {
    title: 'Nhif',
    value: '300',
    titleClass: 'fw-semibold text-body-secondary',
    valueClass: 'text-nowrap',
    color: '#28a745',
  },
  {
    title: 'Ippm - Private',
    value: '780',
    titleClass: 'fw-semibold text-body-secondary',
    valueClass: 'text-nowrap',
    color: '#dc3545',
  },
  {
    title: 'Ippm - Credit',
    value: '220',
    titleClass: 'fw-semibold text-body-secondary',
    valueClass: 'text-nowrap',
    color: '#ffc107',
  },
  {
    title: 'Cost Sharing',
    value: '50000',
    titleClass: 'fw-semibold text-body-secondary',
    valueClass: 'text-nowrap',
    color: '#6f42c1',
  },
  {
    title: 'Waivers',
    value: '900',
    titleClass: 'fw-semibold text-body-secondary',
    valueClass: 'text-nowrap',
    color: '#fd7e14',
  },
  {
    title: 'Nssf',
    value: '30',
    titleClass: 'fw-semibold text-body-secondary',
    valueClass: 'text-nowrap',
    color: '#17a2b8',
  },
  {
    title: 'Total',
    value: '9000',
    titleClass: 'fw-semibold text-body-secondary',
    valueClass: 'fw-semibold text-nowrap',
    color: 'grey',
  },
])
</script>

<template>
  <div class="patient-categories-container mb-4">
    <div v-for="i in 4" :key="i" class="patient-category-div">
      <div v-for="j in 2" :key="j">
        <div class="category-item">
          <template v-if="patientCategories[(i - 1) * 2 + (j - 1)]">
            <div :class="patientCategories[(i - 1) * 2 + (j - 1)].titleClass">
              {{ patientCategories[(i - 1) * 2 + (j - 1)].title }}
            </div>
            <div :class="patientCategories[(i - 1) * 2 + (j - 1)].valueClass">
              {{ patientCategories[(i - 1) * 2 + (j - 1)].value }}
            </div>
          </template>
        </div>
        <div
          class="divider-line"
          :style="{ backgroundColor: patientCategories[(i - 1) * 2 + (j - 1)].color, width: `${patientCategories[(i - 1) * 2 + (j - 1)].value.length * 10}px` }"
        ></div>
      </div>
    </div>
    <div class="patient-category-div total-div">
      <div class="category-item">
        <div :class="patientCategories[8].titleClass">
          {{ patientCategories[8].title }}
        </div>
        <div :class="patientCategories[8].valueClass">
          {{ patientCategories[8].value }}
        </div>
      </div>
      <div
        class="divider-line"
        :style="{ backgroundColor: patientCategories[8].color, width: `${patientCategories[8].value.length * 10}px` }"
      ></div>
    </div>
  </div>
</template>

<style scoped>
.patient-categories-container {
  display: flex;
  flex-direction: row;
  justify-content: space-between;
  gap: 1rem;
}

.patient-category-div {
  display: flex;
  flex-direction: column;
  background-color: var(--cui-card-bg);
  border: 1px solid var(--cui-border-color);
  border-radius: var(--cui-border-radius);
  padding: 1rem;
  flex: 1;
  max-width: 200px;
  overflow-x: auto;
}

.total-div {
  background-color: var(--cui-text-body-secondary text);
  color: black;
  overflow-x: auto;
}

.category-item {
  display: flex;
  justify-content: space-between;
  padding: 0.5rem 0;
}

.divider-line {
  height: 2px;
  margin: 0.1rem 0;
}
</style>
