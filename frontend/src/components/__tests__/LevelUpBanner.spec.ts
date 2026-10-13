import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import LevelUpBanner from '../LevelUpBanner.vue'

describe('LevelUpBanner', () => {
  it('celebrates the new level without announcing it twice', () => {
    const wrapper = mount(LevelUpBanner, { props: { level: 5 } })
    const banner = wrapper.get('[data-testid="level-up-banner"]')

    expect(banner.text()).toBe('Уровень 5!')
    expect(banner.attributes('aria-hidden')).toBe('true')
    expect(banner.classes()).toContain('motion-safe:animate-level-pop')
  })
})
