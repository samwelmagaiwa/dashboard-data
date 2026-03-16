import { ref } from 'vue'
import { defineStore } from 'pinia'

export const useSidebarStore = defineStore('sidebar', () => {
  const visible = ref(true)
  const unfoldable = ref(false)
  const narrow = ref(false)

  const toggleVisible = (value) => {
    visible.value = value !== undefined ? value : !visible.value
  }

  const toggleUnfoldable = () => {
    unfoldable.value = !unfoldable.value
  }

  const toggleNarrow = (value) => {
    if (typeof value === 'boolean') {
      narrow.value = value
      return
    }
    narrow.value = !narrow.value
  }

  return { visible, unfoldable, narrow, toggleVisible, toggleUnfoldable, toggleNarrow }
})
