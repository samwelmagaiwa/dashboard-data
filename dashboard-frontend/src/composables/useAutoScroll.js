import { computed, ref } from 'vue'

const STORAGE_ENABLED_KEY = 'dashboard:auto-scroll:enabled'
const STORAGE_SPEED_KEY = 'dashboard:auto-scroll:speed'
const DEFAULT_SPEED = 'slow'
const SPEED_MAP = {
  slow: 20,
  medium: 35,
  fast: 50,
}

const isEnabled = ref(false)
const speed = ref(DEFAULT_SPEED)

let scrollIntervalId = null
let initialized = false
let trackedPosition = 0
let cachedMaxScroll = 0 // Cache the max scroll limit
let isResetting = false // Cooldown flag after reset

const pixelsPerSecond = computed(() => SPEED_MAP[speed.value] || SPEED_MAP[DEFAULT_SPEED])

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

  // During cooldown after reset, skip this tick
  if (isResetting) {
    isResetting = false
    console.log('[AutoScroll] Cooldown tick - skipping')
    return
  }

  const scrollRoot = getScrollRoot()
  if (!scrollRoot) {
    stopScroll()
    return
  }

  // Use cached maxScroll (refreshed less frequently)
  const maxScroll = getScrollLimit()
  
  // Check if our tracked position has reached or exceeded the bottom
  if (trackedPosition >= maxScroll) {
    console.log('[AutoScroll] >>> AT BOTTOM - RESETTING TO TOP <<<')
    trackedPosition = 0
    
    // Force reset to absolute top - ALL methods
    window.scrollTo({ top: 0, left: 0, behavior: 'auto' })
    document.documentElement.scrollTop = 0
    document.documentElement.scrollLeft = 0
    document.body.scrollTop = 0
    document.body.scrollLeft = 0
    
    // Set cooldown to skip next tick
    isResetting = true
    
    // Force refresh maxScroll on next loop
    cachedMaxScroll = 0
    
    return
  }
  
  // Calculate next position
  const scrollAmount = pixelsPerSecond.value / 60
  const nextPosition = trackedPosition + scrollAmount
  
  // If next position would exceed max, reset immediately instead
  if (nextPosition >= maxScroll) {
    console.log('[AutoScroll] >>> WOULD EXCEED - RESETTING TO TOP <<<')
    trackedPosition = 0
    window.scrollTo({ top: 0, left: 0, behavior: 'auto' })
    document.documentElement.scrollTop = 0
    document.documentElement.scrollLeft = 0
    document.body.scrollTop = 0
    document.body.scrollLeft = 0
    
    // Set cooldown to skip next tick
    isResetting = true
    
    // Force refresh maxScroll on next loop
    cachedMaxScroll = 0
    
    return
  }

  // Normal scroll - update BOTH elements for consistency
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
  isResetting = true // Start with cooldown
  
  // Force to top immediately
  window.scrollTo({ top: 0, left: 0, behavior: 'auto' })
  document.documentElement.scrollTop = 0
  document.body.scrollTop = 0
  
  // Pre-calculate the max scroll limit
  getScrollLimit(true)
  
  scrollIntervalId = setInterval(doScroll, 1000 / 60)
}

const stop = () => {
  stopScroll()
}

const applyState = () => {
  if (!canUseDom()) return

  window.localStorage.setItem(STORAGE_ENABLED_KEY, String(isEnabled.value))
  window.localStorage.setItem(STORAGE_SPEED_KEY, speed.value)

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
  if (savedSpeed && SPEED_MAP[savedSpeed]) {
    speed.value = savedSpeed
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
    speed.value = SPEED_MAP[value] ? value : DEFAULT_SPEED
    applyState()
  }

  return {
    isEnabled,
    speed,
    toggle,
    setSpeed,
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
  }
}
