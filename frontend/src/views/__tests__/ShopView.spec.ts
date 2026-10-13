import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

import ShopView from '../ShopView.vue'
import { makeTestRouter } from './helpers'
import {
  axiosError,
  characterFixture,
  goldenHelmetFixture,
  greenBandanaFixture,
  headbandFixture,
  tealShirtFixture,
  userFixture,
} from '@/api/__tests__/fixtures'
import { buyItem, fetchShop, markShopSeen } from '@/api/shop'
import type { Character, ShopItem } from '@/api/types'
import { useAuthStore } from '@/stores/auth'

vi.mock('@/api/shop', () => ({
  fetchShop: vi.fn<typeof fetchShop>(),
  buyItem: vi.fn<typeof buyItem>(),
  markShopSeen: vi.fn<typeof markShopSeen>(),
}))

const levelTwoHero: Character = {
  ...characterFixture,
  progress: { level: 2, xp_total: 50, xp_in_level: 0, xp_to_next_level: 141 },
  gold: 40,
}

const catalog: ShopItem[] = [
  { item: headbandFixture, owned: true, unlocked: true },
  { item: greenBandanaFixture, owned: false, unlocked: true },
  { item: goldenHelmetFixture, owned: false, unlocked: false },
  { item: tealShirtFixture, owned: true, unlocked: true },
]

enableAutoUnmount(afterEach)

async function mountView(character: Character = levelTwoHero) {
  const pinia = createPinia()
  setActivePinia(pinia)
  useAuthStore().user = { ...userFixture, character }

  const router = makeTestRouter('shop', ShopView)
  await router.push('/shop')
  await router.isReady()

  const wrapper = mount(ShopView, { global: { plugins: [pinia, router] } })
  await flushPromises()

  return wrapper
}

type ViewWrapper = Awaited<ReturnType<typeof mountView>>

function card(wrapper: ViewWrapper, name: string) {
  return wrapper.get(`article[aria-label="${name}"]`)
}

describe('ShopView', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    vi.clearAllMocks()
    vi.mocked(fetchShop).mockResolvedValue(catalog)
    vi.mocked(markShopSeen).mockResolvedValue(levelTwoHero)
  })

  it('shows the catalog grouped by slot and the gold of the hero', async () => {
    const wrapper = await mountView()

    const goods = wrapper.get('section[aria-label="Товары"]')
    expect(goods.text()).toContain('Голова')
    expect(goods.text()).toContain('Тело')
    expect(card(wrapper, 'Повязка').text()).toContain('Куплено')
    expect(card(wrapper, 'Зелёная бандана').text()).toContain('Купить за 30')
    expect(card(wrapper, 'Золотой шлем').text()).toContain('С уровня 9')
    expect(wrapper.get('[data-testid="shop-gold"]').text()).toBe('Золото: 40')
  })

  it('clears the new mark when opened', async () => {
    const seen = { ...levelTwoHero, shop: { new_items: [] } }
    vi.mocked(markShopSeen).mockResolvedValue(seen)

    await mountView({ ...levelTwoHero, shop: { new_items: [greenBandanaFixture] } })

    expect(markShopSeen).toHaveBeenCalledOnce()
    expect(useAuthStore().user?.character?.shop.new_items).toEqual([])
  })

  it('buys a confirmed item and marks it as bought', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    vi.mocked(buyItem).mockResolvedValue({ ...levelTwoHero, gold: 10 })
    const wrapper = await mountView()

    await card(wrapper, 'Зелёная бандана').get('button').trigger('click')
    await flushPromises()

    expect(window.confirm).toHaveBeenCalledWith('Купить «Зелёная бандана» за 30 золота?')
    expect(buyItem).toHaveBeenCalledWith(greenBandanaFixture.id)
    expect(wrapper.get('[data-testid="shop-gold"]').text()).toBe('Золото: 10')
    expect(card(wrapper, 'Зелёная бандана').text()).toContain('Куплено')
  })

  it('keeps the gold of a purchase that finished before the shop was marked as seen', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    let answerSeen: (character: Character) => void = () => {}
    vi.mocked(markShopSeen).mockReturnValue(new Promise((resolve) => (answerSeen = resolve)))
    vi.mocked(buyItem).mockResolvedValue({ ...levelTwoHero, gold: 10 })
    const wrapper = await mountView({ ...levelTwoHero, shop: { new_items: [greenBandanaFixture] } })

    await card(wrapper, 'Зелёная бандана').get('button').trigger('click')
    await flushPromises()
    // The answer still describes the hero before the purchase
    answerSeen({ ...levelTwoHero, gold: 40, shop: { new_items: [] } })
    await flushPromises()

    expect(wrapper.get('[data-testid="shop-gold"]').text()).toBe('Золото: 10')
    expect(useAuthStore().user?.character?.shop.new_items).toEqual([])
  })

  it('buys nothing when the player changes their mind', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(false)
    const wrapper = await mountView()

    await card(wrapper, 'Зелёная бандана').get('button').trigger('click')
    await flushPromises()

    expect(buyItem).not.toHaveBeenCalled()
  })

  it('shows why a purchase was refused', async () => {
    vi.spyOn(window, 'confirm').mockReturnValue(true)
    vi.mocked(buyItem).mockRejectedValue(
      axiosError(422, { errors: { item_id: ['Не хватает золота: нужно 30, есть 10.'] } }),
    )
    const wrapper = await mountView()

    await card(wrapper, 'Зелёная бандана').get('button').trigger('click')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toBe('Не хватает золота: нужно 30, есть 10.')
    expect(card(wrapper, 'Зелёная бандана').text()).toContain('Купить за 30')
  })

  it('tells the user when the shop cannot be loaded', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {})
    vi.mocked(fetchShop).mockRejectedValue(new Error('Network Error'))

    const wrapper = await mountView()

    expect(wrapper.text()).toContain('Не удалось загрузить магазин. Обнови страницу.')
  })

  it('keeps working when the new mark cannot be cleared', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {})
    vi.mocked(markShopSeen).mockRejectedValue(new Error('Network Error'))

    const wrapper = await mountView()

    expect(card(wrapper, 'Зелёная бандана').text()).toContain('Купить за 30')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })
})
