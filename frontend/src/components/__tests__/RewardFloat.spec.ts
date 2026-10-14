import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import RewardFloat from '../RewardFloat.vue'
import type { RewardNotice } from '@/stores/tasks'

const earned: RewardNotice = { xp: 20, gold: 10, capped: false, levelUp: null, id: 1 }

function mountFloat(notice: RewardNotice = earned) {
  return mount(RewardFloat, { props: { notice } })
}

describe('RewardFloat', () => {
  it('floats the reward and leaves the announcement to the status line', () => {
    const wrapper = mountFloat()
    const float = wrapper.get('[data-testid="reward-float"]')

    expect(float.text()).toBe('+20 XP · +10 золота')
    expect(float.attributes('aria-hidden')).toBe('true')
    expect(float.classes()).toContain('motion-safe:animate-reward-float')
  })

  it('disappears once the animation ends', async () => {
    const wrapper = mountFloat()

    await wrapper.get('[data-testid="reward-float"]').trigger('animationend')

    expect(wrapper.find('[data-testid="reward-float"]').exists()).toBe(false)
  })

  it('greys out a taken back reward', () => {
    const wrapper = mountFloat({ xp: -10, gold: -5, capped: false, levelUp: null, id: 2 })

    expect(wrapper.get('[data-testid="reward-float"]').classes()).toContain('text-slate-400')
  })
})
