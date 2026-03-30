import { computed, ref } from 'vue'

const STORAGE_ENABLED_KEY = 'dashboard:auto-scroll:enabled'
const STORAGE_SPEED_KEY = 'dashboard:auto-scroll:speed'
const DEFAULT_SPEED = 'slow'
const SPEED_MAP = {
  slow: 24,
  medium: 38,
  fast: 54,
}

const isEnabled = ref(false)
const speed = ref(DEFAULT_SPEED)

let frameId = null
let lastTimestamp = 0
let initialized = false

const pixelsPerSecond = computed(() => SPEED_MAP[speed.value] || SPEED_MAP[DEFAULT_SPEED])

const canUseDom = () => typeof window !== 'undefined' && typeof document !== 'undefined'

const getScrollRoot = () => {
  if (!canUseDom()) return null

  const candidates = [
    document.scrollingElement,
    document.documentElement,
    document.body,
  ].filter(Boolean)

  return (
    candidates.find((element) => element.scrollHeight > element.clientHeight) ||
    document.scrollingElement ||
    document.documentElement ||
    document.body
  )
}

const getAbsoluteTop = (element) => {
  let top = 0
  let current = element

  while (current) {
    top += current.offsetTop || 0
    current = current.offsetParent
  }

  return top
}

const getScrollLimit = (scrollRoot) => {
  if (!canUseDom() || !scrollRoot) return 0

  const stopElement = document.querySelector('[data-auto-scroll-boundary]')
  const naturalMax = Math.max(scrollRoot.scrollHeight - window.innerHeight, 0)

  if (!stopElement) {
    return naturalMax
  }

  const header = document.querySelector(
    '.header.position-sticky, .header.sticky-top, header.position-sticky, header.sticky-top',
  )
  const headerHeight = header ? Math.ceil(header.getBoundingClientRect().height) : 0
  const stopTop = getAbsoluteTop(stopElement)
  const safetyBuffer = 12
  const limitedMax = Math.max(stopTop - headerHeight - safetyBuffer, 0)

  return Math.min(limitedMax, naturalMax)
}

const scrollToTop = () => {
  const scrollRoot = getScrollRoot()
  if (!scrollRoot || !canUseDom()) return

  scrollRoot.scrollTop = 0
  window.scrollTo(0, 0)
  lastTimestamp = 0
}

const stop = () => {
  if (frameId !== null && canUseDom()) {
    window.cancelAnimationFrame(frameId)
  }

  frameId = null
  lastTimestamp = 0
}

const step = (timestamp) => {
  if (!isEnabled.value || !canUseDom()) {
    stop()
    return
  }

  const scrollRoot = getScrollRoot()
  if (!scrollRoot) {
    stop()
    return
  }

  const maxScrollTop = getScrollLimit(scrollRoot)
  if (maxScrollTop <= 0) {
    frameId = window.requestAnimationFrame(step)
    return
  }

  const currentScrollTop = scrollRoot.scrollTop || window.scrollY || 0
  if (currentScrollTop >= maxScrollTop) {
    scrollToTop()
    frameId = window.requestAnimationFrame(step)
    return
  }

  if (!lastTimestamp) {
    lastTimestamp = timestamp
  }

  const deltaSeconds = (timestamp - lastTimestamp) / 1000
  lastTimestamp = timestamp

  const nextScrollTop = Math.min(currentScrollTop + pixelsPerSecond.value * deltaSeconds, maxScrollTop)

  if (nextScrollTop >= maxScrollTop) {
    // Immediate reset if we reach the limit
    scrollToTop()
  } else {
    scrollRoot.scrollTop = nextScrollTop
    window.scrollTo(0, nextScrollTop)
  }

  frameId = window.requestAnimationFrame(step)
}

const start = () => {
  if (!canUseDom()) return
  stop()
  frameId = window.requestAnimationFrame(step)
}

const applyState = () => {
  if (!canUseDom()) return

  window.localStorage.setItem(STORAGE_ENABLED_KEY, String(isEnabled.value))
  window.localStorage.setItem(STORAGE_SPEED_KEY, speed.value)

  if (isEnabled.value) {
    scrollToTop()
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
