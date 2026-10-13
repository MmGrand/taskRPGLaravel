import { onBeforeUnmount, onMounted } from 'vue'

// Reload data when the tab becomes visible again: after midnight the dailies reset
export function useRefreshOnVisible(refresh: () => Promise<void>): void {
  async function onVisibilityChange(): Promise<void> {
    if (document.visibilityState !== 'visible') {
      return
    }

    try {
      await refresh()
    } catch (error) {
      console.error('Background refresh failed', error)
    }
  }

  onMounted(() => document.addEventListener('visibilitychange', onVisibilityChange))
  onBeforeUnmount(() => document.removeEventListener('visibilitychange', onVisibilityChange))
}
