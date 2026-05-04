import { computed, ref } from 'vue'

const STORAGE_ENABLED_KEY = 'dashboard:auto-scroll:enabled'
const STORAGE_SPEED_KEY = 'dashboard:auto-scroll:speed'
const STORAGE_SPEED_VALUES_KEY = 'dashboard:auto-scroll:speed-values'
const DEFAULT_SPEED = 'slow'
const TOP_HOLD_MS = 20000
const SPEED_VALUE_OPTIONS = {
  slow: [5, 10, 15, 20],
  medium: [30, 35, 40, 45],
  fast: [50, 55, 60, 65],
}
const DEFAULT_SPEED_MAP = {
  slow: 20,
  medium: 35,
  fast: 50,
}

const isEnabled = ref(false)
const speed = ref(DEFAULT_SPEED)
const speedValues = ref({ ...DEFAULT_SPEED_MAP })

let scrollIntervalId = null
let initialized = false
let trackedPosition = 0
let cachedMaxScroll = 0 // Cache the max scroll limit
let scrollDirection = 'down'
let holdUntil = 0
let pendingScrollPixels = 0

const normalizeSpeedValue = (value, fallback) => {
  const parsed = Number(value)
  if (!Number.isFinite(parsed)) return fallback
  return Math.round(parsed)
}

const normalizePresetSpeedValue = (preset, value, fallback) => {
  const allowedValues = SPEED_VALUE_OPTIONS[preset] || []
  const normalized = normalizeSpeedValue(value, fallback)

  if (allowedValues.includes(normalized)) {
    return normalized
  }

  return fallback
}

const sanitizeSpeedValues = (values = {}) => ({
  slow: normalizePresetSpeedValue('slow', values.slow, DEFAULT_SPEED_MAP.slow),
  medium: normalizePresetSpeedValue('medium', values.medium, DEFAULT_SPEED_MAP.medium),
  fast: normalizePresetSpeedValue('fast', values.fast, DEFAULT_SPEED_MAP.fast),
})

const pixelsPerSecond = computed(
  () => speedValues.value[speed.value] || speedValues.value[DEFAULT_SPEED] || DEFAULT_SPEED_MAP[DEFAULT_SPEED],
)

const canUseDom = () => typeof window !== 'undefined' && typeof document !== 'undefined'

const getScrollRoot = () => {
  if (!canUseDom()) return null
  return document.documentElement.scrollHeight > document.documentElement.clientHeight 
    ? document.documentElement 
    : document.body
}

const getScrollLimit = (forceRefresh = false) => {
  if (!canUseDom()) return 0
  
  // Return cached value unless forced to refresh
  if (!forceRefresh && cachedMaxScroll > 0) {
    return cachedMaxScroll
  }
  
  // Get the physical maximum the browser can scroll
  const root = getScrollRoot()
  if (!root) return 0
  
  const scrollHeight = root.scrollHeight
  const clientHeight = root.clientHeight || window.innerHeight
  const physicalMax = Math.max(scrollHeight - clientHeight, 0)
  
  // Try to find boundary marker (last section)
  const boundary = document.querySelector('[data-auto-scroll-boundary]')
  
  if (boundary) {
    const boundaryRect = boundary.getBoundingClientRect()
    const header = document.querySelector('.header, header, .sticky-top')
    const headerHeight = header ? header.getBoundingClientRect().height : 0
    const boundaryTop = boundaryRect.top + window.scrollY
    const scrollableToBoundary = Math.max(boundaryTop - headerHeight - 20, 0)
    
    if (scrollableToBoundary > 100) {
      // Use MINIMUM of boundary and physical limit
      const finalLimit = Math.min(scrollableToBoundary, physicalMax)
      cachedMaxScroll = finalLimit
      console.log('[AutoScroll] Calculated max:', finalLimit)
      return finalLimit
    }
  }
  
  // Fallback to physical max and cache it
  cachedMaxScroll = physicalMax
  console.log('[AutoScroll] Using physical max:', physicalMax)
  return physicalMax
}

// Force refresh on window resize
if (canUseDom()) {
  window.addEventListener('resize', () => {
    cachedMaxScroll = 0 // Invalidate cache on resize
  })
}

const doScroll = () => {
  if (!canUseDom() || !isEnabled.value) {
    stopScroll()
    return
  }

  if (holdUntil > Date.now()) {
    return
  }

  const scrollRoot = getScrollRoot()
  if (!scrollRoot) {
    stopScroll()
    return
  }

  // Use cached maxScroll (refreshed less frequently)
  const maxScroll = getScrollLimit()
  trackedPosition = Math.min(Math.max(window.scrollY || scrollRoot.scrollTop || 0, 0), maxScroll)

  pendingScrollPixels += pixelsPerSecond.value / 60
  const scrollStep = Math.floor(pendingScrollPixels)

  if (scrollStep <= 0) {
    return
  }

  pendingScrollPixels -= scrollStep

  const delta = scrollDirection === 'down' ? scrollStep : -scrollStep
  const nextPosition = trackedPosition + delta

  if (scrollDirection === 'down' && nextPosition >= maxScroll) {
    trackedPosition = maxScroll
    scrollDirection = 'up'
    cachedMaxScroll = 0
    window.scrollTo(0, trackedPosition)
    document.documentElement.scrollTop = trackedPosition
    document.body.scrollTop = trackedPosition
    return
  }

  if (scrollDirection === 'up' && nextPosition <= 0) {
    trackedPosition = 0
    scrollDirection = 'down'
    holdUntil = Date.now() + TOP_HOLD_MS
    window.scrollTo({ top: 0, left: 0, behavior: 'auto' })
    document.documentElement.scrollTop = 0
    document.body.scrollTop = 0
    cachedMaxScroll = 0
    return
  }

  trackedPosition = nextPosition
  window.scrollTo(0, trackedPosition)
  document.documentElement.scrollTop = trackedPosition
  document.body.scrollTop = trackedPosition
}

const stopScroll = () => {
  if (scrollIntervalId !== null && canUseDom()) {
    clearInterval(scrollIntervalId)
  }
  scrollIntervalId = null
}

const start = () => {
  if (!canUseDom()) return
  stopScroll()
  trackedPosition = 0
  cachedMaxScroll = 0 // Reset cache
  scrollDirection = 'down'
  holdUntil = 0
  pendingScrollPixels = 0
  
  // Force to top immediately
  window.scrollTo({ top: 0, left: 0, behavior: 'auto' })
  document.documentElement.scrollTop = 0
  document.body.scrollTop = 0
  
  // Pre-calculate the max scroll limit
  getScrollLimit(true)
  
  scrollIntervalId = setInterval(doScroll, 1000 / 60)
}

const stop = () => {
  pendingScrollPixels = 0
  stopScroll()
}

const applyState = () => {
  if (!canUseDom()) return

  window.localStorage.setItem(STORAGE_ENABLED_KEY, String(isEnabled.value))
  window.localStorage.setItem(STORAGE_SPEED_KEY, speed.value)
  window.localStorage.setItem(STORAGE_SPEED_VALUES_KEY, JSON.stringify(speedValues.value))

  if (isEnabled.value) {
    // Reset to top before starting
    const scrollRoot = getScrollRoot()
    if (scrollRoot) {
      scrollRoot.scrollTop = 0
      window.scrollTo(0, 0)
    }
    start()
  } else {
    stop()
  }
}

export function initAutoScroll() {
  if (initialized || !canUseDom()) {
    return
  }

  initialized = true

  isEnabled.value = window.localStorage.getItem(STORAGE_ENABLED_KEY) === 'true'

  const savedSpeed = window.localStorage.getItem(STORAGE_SPEED_KEY)
  if (savedSpeed && DEFAULT_SPEED_MAP[savedSpeed]) {
    speed.value = savedSpeed
  }

  const savedSpeedValues = window.localStorage.getItem(STORAGE_SPEED_VALUES_KEY)
  if (savedSpeedValues) {
    try {
      speedValues.value = sanitizeSpeedValues(JSON.parse(savedSpeedValues))
    } catch {
      speedValues.value = { ...DEFAULT_SPEED_MAP }
    }
  }

  if (isEnabled.value) {
    start()
  }
}

export function useAutoScroll() {
  initAutoScroll()

  const toggle = () => {
    isEnabled.value = !isEnabled.value
    applyState()
  }

  const setSpeed = (value) => {
    speed.value = DEFAULT_SPEED_MAP[value] ? value : DEFAULT_SPEED
    applyState()
  }

  const setSpeedValue = (preset, value) => {
    if (!DEFAULT_SPEED_MAP[preset]) return

    speedValues.value = {
      ...speedValues.value,
      [preset]: normalizePresetSpeedValue(preset, value, DEFAULT_SPEED_MAP[preset]),
    }

    applyState()
  }

  return {
    isEnabled,
    speed,
    speedValues,
    speedValueOptions: SPEED_VALUE_OPTIONS,
    toggle,
    setSpeed,
    setSpeedValue,
    speedOptions: [
      { value: 'slow', label: 'Slow' },
      { value: 'medium', label: 'Medium' },
      { value: 'fast', label: 'Fast' },
    ],
  }
}

export function getAutoScrollState() {
  initAutoScroll()

  return {
    isEnabled,
    speed,
    speedValues,
    speedValueOptions: SPEED_VALUE_OPTIONS,
  }
}
