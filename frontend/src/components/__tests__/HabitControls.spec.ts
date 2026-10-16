import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import HabitControls from '../HabitControls.vue'

function mountControls(up = 0, down = 0, disabled = false) {
  return mount(HabitControls, {
    props: { title: 'Пить воду', counts: { up_today: up, down_today: down }, disabled },
  })
}

describe('HabitControls', () => {
  it('reads today counts out for screen readers', () => {
    const wrapper = mountControls(3, 1)

    expect(wrapper.get('[data-testid="habit-counts-spoken"]').text()).toBe(
      'Сегодня: плюсов 3, минусов 1',
    )
    expect(wrapper.get('[data-testid="habit-counts"]').attributes('aria-hidden')).toBe('true')
  })

  it('asks for a plus, a minus and an undo', async () => {
    const wrapper = mountControls(1)

    await wrapper.get('[data-testid="habit-plus"]').trigger('click')
    await wrapper.get('[data-testid="habit-minus"]').trigger('click')
    await wrapper.get('[data-testid="habit-undo"]').trigger('click')

    expect(wrapper.emitted('mark')).toEqual([['up'], ['down']])
    expect(wrapper.emitted('undo')).toHaveLength(1)
  })

  it('locks every button while another action runs', () => {
    const wrapper = mountControls(1, 0, true)

    expect(
      wrapper.findAll('button').every((button) => button.attributes('disabled') !== undefined),
    ).toBe(true)
  })
})
