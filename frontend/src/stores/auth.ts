import { defineStore } from 'pinia'
import { api, type User } from '../api'

export const useAuth = defineStore('auth', {
  state: () => ({ user: null as User | null, loaded: false }),
  actions: {
    async load(force = false) {
      if (this.loaded && !force) return
      try {
        this.user = await api<User>('/api/auth/me')
      } catch {
        this.user = null
      } finally {
        this.loaded = true
      }
    },
    async logout() {
      await api('/api/auth/logout', { method: 'POST' })
      this.user = null
      this.loaded = true
    },
  },
})
