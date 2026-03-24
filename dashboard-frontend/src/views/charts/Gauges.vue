<template>
  <div class="dashboard-grid">
    <div class="card large">
      <h2>Requests</h2>
      <p class="number">52435</p>
      <p>Total requests received</p>
    </div>
    <div v-for="(card, index) in cards" :key="index" class="card">
      <h2>{{ card.title }}</h2>
      <div class="progress-circle" :style="card.style">
        <span>{{ card.percent.toFixed(2) }}%</span>
        <div class="inner-circle"></div>
      </div>
      <p v-if="card.number" class="number">{{ card.number.toFixed(2) }}</p>
      <p>{{ card.text }}</p>
    </div>
    <!-- Gauge cards -->
    <div v-for="(value, i) in gauges" :key="i" class="gauge-card">
      <!-- <div class="circle" :class="getColor(value)">{{ value.toFixed(2) }}%</div> -->
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
const gauges = [15.0, 35.0, 52.0, 64.0, 10.0, 25.0, 72.0, 84.0]

function getColor(value) {
  if (value < 40) return 'green'
  if (value < 70) return 'orange'
  return 'red'
}
const cards = [
  {
    title: 'Apdex Score',
    percent: 80,
    text: 'Overall Apdex Score',
  },
  {
    title: 'Current Req Rate',
    percent: 8,
    number: 10,
    text: 'Requests per second',
  },
  {
    title: 'Current Err Rate',
    percent: 20,
    number: 4,
    text: 'Errors per second',
  },
  {
    title: 'data here',
    percent: 5,
    number: 9,
    text: 'show data again here',
  },
]
cards.forEach((card) => {
  const progressDeg = card.percent * 3.6
  card.style = `background: conic-gradient(blue 0deg, red ${progressDeg}deg, #eee ${progressDeg}deg 360deg);`
})
</script>

<style scoped>
.dashboard-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
  padding: 1rem;
  background: #f5f5f5;
}

.card {
  background: white;
  border-radius: 20px;
  padding: 1rem;
  font-size: 1.2rem;
  text-align: center;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
  transition: transform 0.3s ease;
}

.card:hover {
  transform: translateY(-5px);
}

h2 {
  font-size: 1.4rem;
  margin-bottom: 0.5rem;
  color: #333;
}

.number {
  font-size: 1.5rem;
  color: #555;
  margin: 0.5rem 0;
}

p {
  font-size: 1rem;
  color: #777;
  margin: 0;
}

.progress-circle {
  position: relative;
  width: 100px;
  height: 100px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: bold;
  font-size: 1.4rem;
  margin: 1rem auto;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.progress-circle span {
  z-index: 2;
  color: #333;
}

.inner-circle {
  position: absolute;
  width: 85px;
  height: 85px;
  border-radius: 50%;
  background: white;
  z-index: 1;
}
</style>
