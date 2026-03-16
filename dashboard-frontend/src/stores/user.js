import { defineStore } from 'pinia'

export const useUserStore = defineStore('user', {
  state: () => ({
    role: 'Staff', // default
  }),
  actions: {
    setRole(role) {
      this.role = role
    }
  }
})