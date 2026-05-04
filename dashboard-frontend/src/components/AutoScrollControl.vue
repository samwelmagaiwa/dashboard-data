<script setup>
import { computed } from 'vue'
import { useAutoScroll } from '@/composables/useAutoScroll'
import { CFormSelect } from '@coreui/vue'

const autoScroll = useAutoScroll()
const isAutoScrollEnabled = computed(() => autoScroll.isEnabled.value)
const speedPresets = [
  { key: 'slow', label: 'Slow' },
  { key: 'medium', label: 'Medium' },
  { key: 'fast', label: 'Fast' },
]

const handleSpeedChange = (event) => {
  autoScroll.setSpeed(event.target.value)
}

const handleSpeedValueChange = (preset, event) => {
  autoScroll.setSpeedValue(preset, event.target.value)
}

const presetOptions = (preset) =>
  (autoScroll.speedValueOptions[preset] || []).map((value) => ({
    value,
    label: String(value),
  }))
</script>

<template>
  <div class="auto-scroll-control d-inline-flex flex-column align-items-start gap-2">
    <div class="d-inline-flex align-items-center gap-2">
    <button
      type="button"
      class="auto-scroll-toggle"
      :class="isAutoScrollEnabled ? 'is-on' : 'is-off'"
      @click="autoScroll.toggle"
      :aria-pressed="isAutoScrollEnabled"
      title="Toggle automatic page scrolling"
    >
      <span class="auto-scroll-toggle__label">
        {{ isAutoScrollEnabled ? 'ON' : 'OFF' }}
      </span>
      <span class="auto-scroll-toggle__thumb"></span>
    </button>

    <CFormSelect
      :model-value="autoScroll.speed.value"
      :options="autoScroll.speedOptions"
      size="sm"
      class="auto-scroll-speed"
      aria-label="Auto scroll speed"
      @change="handleSpeedChange"
    />
    <span class="active-speed-label">
      {{ autoScroll.speedValues.value[autoScroll.speed.value] }} px/sec
    </span>
    </div>

    <div class="speed-config-grid">
      <label
        v-for="preset in speedPresets"
        :key="preset.key"
        class="speed-config-card"
        :class="{ 'is-active': autoScroll.speed.value === preset.key }"
      >
        <span class="speed-config-card__label">{{ preset.label }}</span>
        <CFormSelect
          :model-value="autoScroll.speedValues.value[preset.key]"
          :options="presetOptions(preset.key)"
          size="sm"
          class="speed-config-card__select"
          :aria-label="`${preset.label} auto scroll speed`"
          @change="(event) => handleSpeedValueChange(preset.key, event)"
        />
        <span class="speed-config-card__hint">px/sec</span>
      </label>
    </div>
  </div>
</template>

<style scoped>
.auto-scroll-control {
  min-width: max-content;
}

.speed-config-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(84px, 1fr));
  gap: 8px;
  width: 100%;
}

.speed-config-card {
  display: flex;
  flex-direction: column;
  gap: 6px;
  padding: 8px;
  border: 1px solid rgba(91, 87, 214, 0.2);
  border-radius: 12px;
  background: rgba(91, 87, 214, 0.05);
}

.speed-config-card.is-active {
  border-color: rgba(91, 87, 214, 0.45);
  background: rgba(91, 87, 214, 0.1);
  box-shadow: 0 6px 16px rgba(91, 87, 214, 0.12);
}

.speed-config-card__label {
  font-size: 0.7rem;
  font-weight: 800;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  color: #4c478f;
}

.speed-config-card__select {
  width: 100%;
}

:deep(.speed-config-card__select .form-select) {
  border: 1px solid rgba(91, 87, 214, 0.3);
  border-radius: 10px;
  padding: 6px 30px 6px 8px;
  font-size: 0.85rem;
  font-weight: 700;
  color: #302c92;
  background-color: #fff;
}

:deep(.speed-config-card__select .form-select:focus) {
  border-color: #3f3ab7;
  box-shadow: 0 0 0 0.18rem rgba(91, 87, 214, 0.14);
}

.speed-config-card__hint {
  font-size: 0.66rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  color: #7c7aa8;
  text-transform: uppercase;
}

.auto-scroll-speed {
  width: 132px;
  min-width: 132px;
}

.active-speed-label {
  font-size: 0.78rem;
  font-weight: 800;
  color: #4c478f;
  letter-spacing: 0.02em;
  white-space: nowrap;
}

:deep(.auto-scroll-speed .form-select) {
  border: 2px solid #5b57d6;
  border-radius: 12px;
  background-color: rgba(91, 87, 214, 0.08);
  color: #302c92;
  font-weight: 700;
  box-shadow: 0 4px 12px rgba(91, 87, 214, 0.12);
}

:deep(.auto-scroll-speed .form-select:focus) {
  border-color: #3f3ab7;
  box-shadow: 0 0 0 0.2rem rgba(91, 87, 214, 0.18);
}

.auto-scroll-toggle {
  position: relative;
  display: inline-flex;
  align-items: center;
  justify-content: flex-end;
  width: 102px;
  min-width: 102px;
  height: 44px;
  padding: 0 14px;
  border: 0;
  border-radius: 999px;
  background: linear-gradient(135deg, #8f1010, #d71920);
  box-shadow: inset 0 2px 4px rgba(255, 255, 255, 0.22), 0 10px 20px rgba(0, 0, 0, 0.2);
  color: #fff;
  cursor: pointer;
  transition: background 0.25s ease, transform 0.2s ease;
}

.auto-scroll-toggle:hover {
  transform: translateY(-1px);
}

.auto-scroll-toggle.is-off {
  background: linear-gradient(135deg, #8f1010, #d71920) !important;
  justify-content: flex-end;
}

.auto-scroll-toggle.is-on {
  background: linear-gradient(135deg, #4b9f12, #7dd320);
  justify-content: flex-start;
}

.auto-scroll-toggle__label {
  font-size: 1rem;
  font-weight: 800;
  letter-spacing: 0.08em;
  transition: transform 0.25s ease;
}

.auto-scroll-toggle:not(.is-on) .auto-scroll-toggle__label {
  transform: translateX(-2px);
}

.auto-scroll-toggle__thumb {
  position: absolute;
  top: 4px;
  left: 4px;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: radial-gradient(circle at 30% 30%, #f7f8ff, #ced4f2);
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.28);
  transition: transform 0.25s ease;
}

.auto-scroll-toggle.is-on .auto-scroll-toggle__thumb {
  transform: translateX(58px);
}
</style>
