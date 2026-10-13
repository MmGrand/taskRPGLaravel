import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import CharacterStats from '../CharacterStats.vue'
import { characterFixture } from '@/api/__tests__/fixtures'
import type { Character } from '@/api/types'

const seasonedHero: Character = {
  ...characterFixture,
  progress: { level: 2, xp_total: 70, xp_in_level: 20, xp_to_next_level: 141 },
  gold: 35,
  streak: { days: 3, active_today: true },
}

function mountStats(character: Character) {
  return mount(CharacterStats, { props: { character } })
}

describe('CharacterStats', () => {
  it('shows the level, XP, gold and streak', () => {
    const wrapper = mountStats(seasonedHero)

    expect(wrapper.text()).toContain('Уровень 2')
    expect(wrapper.text()).toContain('20 / 141 XP')
    expect(wrapper.text()).toContain('Золото: 35')
    expect(wrapper.text()).toContain('Стрик: 3 дня')
  })

  it('exposes the XP bar to assistive technology and fills it by the earned share', () => {
    const wrapper = mountStats(seasonedHero)

    const bar = wrapper.get('[role="progressbar"]')

    expect(bar.attributes('aria-valuenow')).toBe('20')
    expect(bar.attributes('aria-valuemax')).toBe('141')
    expect(bar.attributes('aria-valuetext')).toBe('20 из 141 XP')
    expect(wrapper.get('[data-testid="xp-bar"]').attributes('style')).toContain('width: 14%')
  })

  it('hints that a living streak waits for today', () => {
    const waiting = mountStats({ ...seasonedHero, streak: { days: 3, active_today: false } })

    expect(waiting.find('[data-testid="streak-hint"]').exists()).toBe(true)
    expect(mountStats(seasonedHero).find('[data-testid="streak-hint"]').exists()).toBe(false)
    expect(mountStats(characterFixture).find('[data-testid="streak-hint"]').exists()).toBe(false)
  })
})
