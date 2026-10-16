import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

import TimezoneForm from '../TimezoneForm.vue'
import { updateTimezone } from '@/api/profile'
import { axiosError, userFixture } from '@/api/__tests__/fixtures'
import { useAuthStore } from '@/stores/auth'

vi.mock('@/api/profile', () => ({ updateTimezone: vi.fn<typeof updateTimezone>() }))
vi.mock('@/game/timezones', async (importOriginal) => ({
  ...(await importOriginal<typeof import('@/game/timezones')>()),
  browserTimezone: () => 'Asia/Tokyo',
}))

const updateTimezoneMock = vi.mocked(updateTimezone)

function mountForm() {
  const pinia = createPinia()
  setActivePinia(pinia)
  // The fixture user lives in Moscow
  useAuthStore().user = userFixture

  return mount(TimezoneForm, { global: { plugins: [pinia] } })
}

type FormWrapper = ReturnType<typeof mountForm>

function select(wrapper: FormWrapper) {
  return wrapper.get<HTMLSelectElement>('select[name="timezone"]')
}

function submitButton(wrapper: FormWrapper) {
  return wrapper.get<HTMLButtonElement>('button[type="submit"]')
}

describe('TimezoneForm', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('selects the current timezone and offers it with its offset', () => {
    const wrapper = mountForm()

    expect(select(wrapper).element.value).toBe('Europe/Moscow')
    expect(wrapper.text()).toContain('Europe/Moscow (GMT+3)')
  })

  it('cannot be saved until another timezone is picked', async () => {
    const wrapper = mountForm()
    expect(submitButton(wrapper).element.disabled).toBe(true)

    await select(wrapper).setValue('Europe/Berlin')

    expect(submitButton(wrapper).element.disabled).toBe(false)
  })

  it('saves the picked timezone and keeps the returned user', async () => {
    updateTimezoneMock.mockResolvedValue({ ...userFixture, timezone: 'Europe/Berlin' })
    const wrapper = mountForm()

    await select(wrapper).setValue('Europe/Berlin')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(updateTimezoneMock).toHaveBeenCalledWith('Europe/Berlin')
    expect(useAuthStore().user?.timezone).toBe('Europe/Berlin')
    expect(wrapper.get('[role="status"]').text()).toBe('Пояс сохранён')
  })

  it('shows why the server refused the change', async () => {
    updateTimezoneMock.mockRejectedValue(
      axiosError(422, {
        errors: { timezone: ['Часовой пояс недавно менялся. Попробуй через 3 ч.'] },
      }),
    )
    const wrapper = mountForm()

    await select(wrapper).setValue('Europe/Berlin')
    await wrapper.get('form').trigger('submit')
    await flushPromises()

    expect(wrapper.text()).toContain('Часовой пояс недавно менялся. Попробуй через 3 ч.')
    expect(useAuthStore().user?.timezone).toBe('Europe/Moscow')
    expect(select(wrapper).element.value).toBe('Europe/Berlin')
  })

  it('keeps the wall clock of the picked zone up to date', async () => {
    vi.useFakeTimers({ now: new Date('2026-10-13T12:00:00Z') })
    try {
      const wrapper = mountForm()
      expect(wrapper.text()).toContain('Сейчас там: 15:00')

      await vi.advanceTimersByTimeAsync(60_000)

      expect(wrapper.text()).toContain('Сейчас там: 15:01')
    } finally {
      vi.useRealTimers()
    }
  })

  it('picks the browser timezone on request', async () => {
    const wrapper = mountForm()

    await wrapper.get('[data-testid="use-browser-timezone"]').trigger('click')

    expect(select(wrapper).element.value).toBe('Asia/Tokyo')
    expect(wrapper.find('[data-testid="use-browser-timezone"]').exists()).toBe(false)
  })
})
