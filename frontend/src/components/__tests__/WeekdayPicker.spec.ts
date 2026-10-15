import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import WeekdayPicker from '../WeekdayPicker.vue'
import type { Weekday } from '@/api/types'

function mountPicker(modelValue: Weekday[]) {
  return mount(WeekdayPicker, { props: { legend: 'Дни', modelValue } })
}

function day(wrapper: ReturnType<typeof mountPicker>, name: string) {
  return wrapper.get<HTMLInputElement>(`input[aria-label="${name}"]`)
}

describe('WeekdayPicker', () => {
  it('shows the chosen days as checked', () => {
    const wrapper = mountPicker([1, 3])

    expect(day(wrapper, 'Понедельник').element.checked).toBe(true)
    expect(day(wrapper, 'Вторник').element.checked).toBe(false)
    expect(wrapper.text()).toContain('Пн')
  })

  it('adds a day in week order without changing the given array', async () => {
    const days: Weekday[] = [1, 5]
    const wrapper = mountPicker(days)

    await day(wrapper, 'Среда').setValue(true)

    expect(wrapper.emitted('update:modelValue')).toEqual([[[1, 3, 5]]])
    expect(days).toEqual([1, 5])
  })

  it('removes a day', async () => {
    const wrapper = mountPicker([1, 3])

    await day(wrapper, 'Понедельник').setValue(false)

    expect(wrapper.emitted('update:modelValue')).toEqual([[[3]]])
  })

  it('keeps the last day: a daily needs at least one', () => {
    const wrapper = mountPicker([4])

    expect(day(wrapper, 'Четверг').attributes('disabled')).toBeDefined()
    expect(day(wrapper, 'Пятница').attributes('disabled')).toBeUndefined()
  })
})
