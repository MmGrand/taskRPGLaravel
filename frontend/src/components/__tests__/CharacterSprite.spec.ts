import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import CharacterSprite from '../CharacterSprite.vue'
import { appearanceLayers, type Appearance } from '@/game/appearance'

const appearance: Appearance = {
  skin_tone: 'tan',
  hair_style: 'braids',
  hair_color: 'ginger',
  beard: 'full',
}

describe('CharacterSprite', () => {
  it('renders one layer per appearance tile with an accessible label', () => {
    const wrapper = mount(CharacterSprite, { props: { appearance, label: 'Торин' } })

    expect(wrapper.findAll('[data-testid="sprite-layer"]')).toHaveLength(
      appearanceLayers(appearance).length,
    )
    expect(wrapper.get('[data-testid="character-sprite"]').attributes('aria-label')).toBe('Торин')
  })

  it('positions each layer on the sheet according to the scale', () => {
    const wrapper = mount(CharacterSprite, { props: { appearance, label: 'Торин', scale: 4 } })

    const sprite = wrapper.get('[data-testid="character-sprite"]')
    const layers = wrapper.findAll('[data-testid="sprite-layer"]')
    const hair = layers[layers.length - 1]

    expect(sprite.attributes('style')).toContain('width: 64px')
    expect(hair?.attributes('style')).toContain('background-position: -1632px -68px')
    expect(hair?.attributes('style')).toContain('background-size: 3672px 812px')
  })

  it('draws worn items on top of the hero', () => {
    const wrapper = mount(CharacterSprite, {
      props: {
        appearance,
        label: 'Торин',
        scale: 1,
        equipment: [{ slot: 'weapon', sprite: { col: 42, row: 6 } }],
      },
    })

    const layers = wrapper.findAll('[data-testid="sprite-layer"]')

    expect(layers).toHaveLength(appearanceLayers(appearance).length + 1)
    expect(layers[layers.length - 1]?.attributes('style')).toContain(
      'background-position: -714px -102px',
    )
  })

  it('rounds a fractional scale to keep pixels crisp', () => {
    const wrapper = mount(CharacterSprite, { props: { appearance, label: 'Торин', scale: 2.6 } })

    expect(wrapper.get('[data-testid="character-sprite"]').attributes('style')).toContain(
      'width: 48px',
    )
  })
})
