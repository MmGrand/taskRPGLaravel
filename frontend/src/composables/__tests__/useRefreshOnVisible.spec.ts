import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent } from 'vue'

import { useRefreshOnVisible } from '../useRefreshOnVisible'

function mountWith(refresh: () => Promise<void>) {
  return mount(
    defineComponent({
      setup() {
        useRefreshOnVisible(refresh)

        return () => null
      },
    }),
  )
}

function changeVisibility(state: DocumentVisibilityState): void {
  vi.spyOn(document, 'visibilityState', 'get').mockReturnValue(state)
  document.dispatchEvent(new Event('visibilitychange'))
}

describe('useRefreshOnVisible', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
  })

  it('refreshes when the tab becomes visible', () => {
    const refresh = vi.fn<() => Promise<void>>().mockResolvedValue()
    mountWith(refresh)

    changeVisibility('visible')

    expect(refresh).toHaveBeenCalledOnce()
  })

  it('does not refresh when the tab gets hidden', () => {
    const refresh = vi.fn<() => Promise<void>>().mockResolvedValue()
    mountWith(refresh)

    changeVisibility('hidden')

    expect(refresh).not.toHaveBeenCalled()
  })

  it('stops listening after unmount', () => {
    const refresh = vi.fn<() => Promise<void>>().mockResolvedValue()
    mountWith(refresh).unmount()

    changeVisibility('visible')

    expect(refresh).not.toHaveBeenCalled()
  })

  it('logs a failed refresh instead of throwing', async () => {
    const error = new Error('Network Error')
    const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {})
    mountWith(vi.fn<() => Promise<void>>().mockRejectedValue(error))

    changeVisibility('visible')
    await flushPromises()

    expect(consoleError).toHaveBeenCalledWith('Background refresh failed', error)
  })
})
