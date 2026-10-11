import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

import ApiStatus from '../ApiStatus.vue'
import { fetchHealth } from '@/api/health'

vi.mock('@/api/health', () => ({ fetchHealth: vi.fn<typeof fetchHealth>() }))

const fetchHealthMock = vi.mocked(fetchHealth)

describe('ApiStatus', () => {
  beforeEach(() => {
    fetchHealthMock.mockReset()
  })

  it('shows loading state until the health check resolves', () => {
    fetchHealthMock.mockReturnValue(new Promise(() => {}))

    const wrapper = mount(ApiStatus)

    expect(wrapper.get('[data-testid="api-status"]').attributes('data-state')).toBe('loading')
  })

  it('shows ok state with dependency checks', async () => {
    fetchHealthMock.mockResolvedValue({ status: 'ok', checks: { database: 'ok', redis: 'ok' } })

    const wrapper = mount(ApiStatus)
    await flushPromises()

    expect(wrapper.get('[data-testid="api-status"]').attributes('data-state')).toBe('ok')
    expect(wrapper.text()).toContain('database: ok')
    expect(wrapper.text()).toContain('redis: ok')
  })

  it('shows degraded state with the failing dependency', async () => {
    fetchHealthMock.mockResolvedValue({
      status: 'degraded',
      checks: { database: 'fail', redis: 'ok' },
    })

    const wrapper = mount(ApiStatus)
    await flushPromises()

    expect(wrapper.get('[data-testid="api-status"]').attributes('data-state')).toBe('degraded')
    expect(wrapper.text()).toContain('database: fail')
  })

  it('shows unreachable state and logs when the request fails', async () => {
    const consoleError = vi.spyOn(console, 'error').mockImplementation(() => {})
    fetchHealthMock.mockRejectedValue(new Error('Network Error'))

    const wrapper = mount(ApiStatus)
    await flushPromises()

    expect(wrapper.get('[data-testid="api-status"]').attributes('data-state')).toBe('unreachable')
    expect(wrapper.text()).toContain('API: недоступен')
    expect(consoleError).toHaveBeenCalled()
    consoleError.mockRestore()
  })
})
