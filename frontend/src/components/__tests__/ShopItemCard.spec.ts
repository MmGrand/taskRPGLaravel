import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import ShopItemCard from '../ShopItemCard.vue'
import { greenBandanaFixture } from '@/api/__tests__/fixtures'
import type { ShopItem } from '@/api/types'

const bandana: ShopItem = { item: greenBandanaFixture, owned: false, unlocked: true }

function mountCard(entry: ShopItem = bandana, gold = 100, disabled = false) {
  return mount(ShopItemCard, { props: { entry, gold, disabled } })
}

describe('ShopItemCard', () => {
  it('offers an affordable item and asks to buy it on click', async () => {
    const wrapper = mountCard()
    const button = wrapper.get('button')

    await button.trigger('click')

    expect(button.attributes('aria-label')).toBe('Купить «Зелёная бандана» за 30 золота')
    expect(button.text()).toBe('Купить за 30')
    expect(wrapper.emitted('buy')).toHaveLength(1)
  })

  it('names the card once and hides the decorative icon', () => {
    const wrapper = mountCard()

    expect(wrapper.get('article').attributes('aria-label')).toBe('Зелёная бандана')
    expect(wrapper.get('[role="img"]').element.closest('[aria-hidden="true"]')).not.toBeNull()
  })

  it('centres the icon of headgear drawn at the top of its tile', () => {
    const wrapper = mountCard()

    expect(wrapper.get('[data-testid="sprite-layer"]').attributes('style')).toContain(
      'transform: translate(0px, 15px)',
    )
  })

  it('shows a bought item without a button', () => {
    const wrapper = mountCard({ ...bandana, owned: true })

    expect(wrapper.text()).toContain('Куплено')
    expect(wrapper.find('button').exists()).toBe(false)
  })

  it('locks an item above the level of the hero', () => {
    const wrapper = mountCard({ ...bandana, unlocked: false })

    expect(wrapper.text()).toContain('С уровня 2')
    expect(wrapper.find('button').exists()).toBe(false)
    // Only the icon is dimmed: faded text would fall below the WCAG AA contrast
    expect(wrapper.get('[data-testid="shop-item-icon"]').classes()).toContain('opacity-50')
    expect(wrapper.get('article').classes()).not.toContain('opacity-50')
  })

  it('disables the purchase and tells how much gold is missing', () => {
    const wrapper = mountCard(bandana, 10)

    expect(wrapper.get('button').attributes('disabled')).toBeDefined()
    expect(wrapper.text()).toContain('Не хватает 20')
  })

  it('is locked while another purchase runs', () => {
    const wrapper = mountCard(bandana, 100, true)

    expect(wrapper.get('button').attributes('disabled')).toBeDefined()
  })
})
