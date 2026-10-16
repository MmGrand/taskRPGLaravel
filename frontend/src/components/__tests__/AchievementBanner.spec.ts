import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import AchievementBanner from '../AchievementBanner.vue'
import { firstStepFixture, streakWeekFixture } from '@/api/__tests__/fixtures'

describe('AchievementBanner', () => {
  it('names every new achievement and stays out of the accessibility tree', () => {
    const wrapper = mount(AchievementBanner, {
      props: { achievements: [firstStepFixture, streakWeekFixture] },
    })

    const banner = wrapper.get('[data-testid="achievement-banner"]')
    expect(banner.text()).toBe('★ Первый шаг, Неделя без пропусков')
    expect(banner.attributes('aria-hidden')).toBe('true')
  })
})
