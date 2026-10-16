import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import AchievementCard from '../AchievementCard.vue'
import { firstStepFixture, streakWeekFixture } from '@/api/__tests__/fixtures'

describe('AchievementCard', () => {
  it('shows the date of an earned achievement and no progress bar', () => {
    const wrapper = mount(AchievementCard, { props: { achievement: firstStepFixture } })

    expect(wrapper.text()).toContain('Получено 13.10.2026')
    expect(wrapper.find('[role="progressbar"]').exists()).toBe(false)
    expect(wrapper.get('li').classes()).not.toContain('opacity-70')
  })

  it('shows the progress of an open achievement and the rare item it gives', () => {
    const wrapper = mount(AchievementCard, { props: { achievement: streakWeekFixture } })

    expect(wrapper.get('[role="progressbar"]').attributes('aria-valuenow')).toBe('3')
    expect(wrapper.text()).toContain('3 / 7')
    expect(wrapper.get('[data-testid="achievement-reward"]').text()).toBe('Награда: Штаны паладина')
    expect(wrapper.get('li').classes()).toContain('opacity-70')
  })
})
