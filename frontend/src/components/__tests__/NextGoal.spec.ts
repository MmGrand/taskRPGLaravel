import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import NextGoal from '../NextGoal.vue'
import { streakWeekFixture } from '@/api/__tests__/fixtures'

describe('NextGoal', () => {
  it('shows the goal, how far along it is and the item it gives', () => {
    const wrapper = mount(NextGoal, { props: { goal: streakWeekFixture } })

    const bar = wrapper.get('[role="progressbar"]')
    expect(wrapper.text()).toContain('Следующая цель')
    expect(wrapper.text()).toContain('Неделя без пропусков')
    expect(wrapper.text()).toContain('3 / 7')
    expect(wrapper.text()).toContain('Награда: Штаны паладина')
    expect(bar.attributes('aria-valuetext')).toBe('3 из 7')
    expect(bar.get('div').attributes('style')).toContain('width: 42%')
  })

  it('leaves the reward out of a goal without an item', () => {
    const wrapper = mount(NextGoal, {
      props: { goal: { ...streakWeekFixture, reward_item: null } },
    })

    expect(wrapper.text()).not.toContain('Награда')
  })
})
