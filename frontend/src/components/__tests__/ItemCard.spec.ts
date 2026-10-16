import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import ItemCard from '../ItemCard.vue'
import { paladinPantsFixture, woodenSwordFixture } from '@/api/__tests__/fixtures'

function mountCard(equipped = false, disabled = false) {
  return mount(ItemCard, { props: { item: woodenSwordFixture, equipped, disabled } })
}

describe('ItemCard', () => {
  it('asks to equip the item on click', async () => {
    const wrapper = mountCard()

    await wrapper.get('button').trigger('click')

    expect(wrapper.emitted('equip')).toHaveLength(1)
    expect(wrapper.get('[role="img"]').attributes('aria-label')).toBe('Деревянный меч')
  })

  it('marks a rare item and only a rare one', () => {
    const plain = mountCard()
    const rare = mount(ItemCard, {
      props: { item: paladinPantsFixture, equipped: false, disabled: false },
    })

    expect(plain.find('[data-testid="rare-mark"]').exists()).toBe(false)
    expect(rare.get('[data-testid="rare-mark"]').text()).toBe('Редкий')
    expect(rare.get('button').classes()).toContain('border-rare/60')
  })

  it('names the card once: the icon repeats the visible title, so it is hidden from screen readers', () => {
    const wrapper = mountCard()

    expect(wrapper.get('[role="img"]').element.closest('[aria-hidden="true"]')).not.toBeNull()
    expect(wrapper.get('button').text()).toBe('Деревянный меч')
  })

  it('centres the icon of an item drawn in the hand of the hero', () => {
    const wrapper = mountCard()

    expect(wrapper.get('[data-testid="sprite-layer"]').attributes('style')).toContain(
      'transform: translate(15px, 0px)',
    )
  })

  it('marks a worn item and does not equip it again', () => {
    const wrapper = mountCard(true)
    const button = wrapper.get('button')

    expect(button.attributes('aria-pressed')).toBe('true')
    expect(button.attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('Надето')
  })

  it('is locked while another action runs', () => {
    const wrapper = mountCard(false, true)

    expect(wrapper.get('button').attributes('disabled')).toBeDefined()
    expect(wrapper.get('button').attributes('aria-pressed')).toBe('false')
  })
})
