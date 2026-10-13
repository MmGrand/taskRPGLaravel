import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import SpriteStack from '../SpriteStack.vue'

describe('SpriteStack', () => {
  it('draws one layer per tile under an accessible label', () => {
    const wrapper = mount(SpriteStack, {
      props: {
        tiles: [
          { col: 0, row: 0 },
          { col: 42, row: 6 },
        ],
        label: 'Деревянный меч',
      },
    })

    expect(wrapper.findAll('[data-testid="sprite-layer"]')).toHaveLength(2)
    expect(wrapper.get('[role="img"]').attributes('aria-label')).toBe('Деревянный меч')
  })

  it('positions each tile on the sheet according to the scale', () => {
    const wrapper = mount(SpriteStack, {
      props: { tiles: [{ col: 42, row: 6 }], label: 'Деревянный меч', scale: 3 },
    })

    expect(wrapper.get('[role="img"]').attributes('style')).toContain('width: 48px')
    expect(wrapper.get('[data-testid="sprite-layer"]').attributes('style')).toContain(
      'background-position: -2142px -306px',
    )
  })
})
