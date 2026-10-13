import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'

import EquipmentView from '../EquipmentView.vue'
import { makeTestRouter } from './helpers'
import {
  axiosError,
  characterFixture,
  inventoryFixture,
  userFixture,
  woodenSwordFixture,
} from '@/api/__tests__/fixtures'
import { fetchCurrentUser, login, logout, register } from '@/api/auth'
import { equipItem, fetchInventory, unequipSlot } from '@/api/equipment'
import type { Character } from '@/api/types'
import { useAuthStore } from '@/stores/auth'

vi.mock('@/api/auth', () => ({
  fetchCurrentUser: vi.fn<typeof fetchCurrentUser>(),
  login: vi.fn<typeof login>(),
  logout: vi.fn<typeof logout>(),
  register: vi.fn<typeof register>(),
}))
vi.mock('@/api/equipment', () => ({
  fetchInventory: vi.fn<typeof fetchInventory>(),
  equipItem: vi.fn<typeof equipItem>(),
  unequipSlot: vi.fn<typeof unequipSlot>(),
}))

const armed: Character = {
  ...characterFixture,
  equipment: [{ slot: 'weapon', item: woodenSwordFixture }],
}

enableAutoUnmount(afterEach)

async function mountView(character: Character = characterFixture) {
  const pinia = createPinia()
  setActivePinia(pinia)
  useAuthStore().user = { ...userFixture, character }

  const router = makeTestRouter('equipment', EquipmentView)
  await router.push('/equipment')
  await router.isReady()

  const wrapper = mount(EquipmentView, { global: { plugins: [pinia, router] } })
  await flushPromises()

  return wrapper
}

type ViewWrapper = Awaited<ReturnType<typeof mountView>>

function itemButton(wrapper: ViewWrapper, name: string) {
  const button = wrapper
    .get('section[aria-label="Инвентарь"]')
    .findAll('button')
    .find((candidate) => candidate.text().includes(name))

  if (button === undefined) {
    throw new Error(`No item card for ${name}`)
  }

  return button
}

function layerCount(wrapper: ViewWrapper): number {
  return wrapper.get('[data-testid="character-sprite"]').findAll('[data-testid="sprite-layer"]')
    .length
}

describe('EquipmentView', () => {
  beforeEach(() => {
    vi.restoreAllMocks()
    vi.clearAllMocks()
    vi.mocked(fetchInventory).mockResolvedValue(inventoryFixture)
  })

  it('shows every slot and the inventory grouped by slot', async () => {
    const wrapper = await mountView()

    const worn = wrapper.get('section[aria-label="Надето"]')
    for (const label of ['Голова', 'Тело', 'Ноги', 'Оружие', 'Щит']) {
      expect(worn.text()).toContain(label)
    }
    expect(worn.text()).toContain('Пусто')
    const inventory = wrapper.get('section[aria-label="Инвентарь"]')
    expect(inventory.text()).toContain('Повязка')
    expect(inventory.text()).toContain('Деревянный меч')
  })

  it('puts an item on the hero', async () => {
    vi.mocked(equipItem).mockResolvedValue(armed)
    const wrapper = await mountView()
    const layersBefore = layerCount(wrapper)

    await itemButton(wrapper, 'Деревянный меч').trigger('click')
    await flushPromises()

    expect(equipItem).toHaveBeenCalledWith('weapon', woodenSwordFixture.id)
    expect(useAuthStore().user?.character?.equipment).toEqual(armed.equipment)
    expect(layerCount(wrapper)).toBe(layersBefore + 1)
    expect(itemButton(wrapper, 'Деревянный меч').attributes('aria-pressed')).toBe('true')
  })

  it('takes an item off', async () => {
    vi.mocked(unequipSlot).mockResolvedValue(characterFixture)
    const wrapper = await mountView(armed)

    await wrapper.get('button[aria-label="Снять: Оружие"]').trigger('click')
    await flushPromises()

    expect(unequipSlot).toHaveBeenCalledWith('weapon')
    expect(useAuthStore().user?.character?.equipment).toEqual([])
    expect(wrapper.find('button[aria-label="Снять: Оружие"]').exists()).toBe(false)
  })

  it('tells the user when the inventory cannot be loaded', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {})
    vi.mocked(fetchInventory).mockRejectedValue(new Error('Network Error'))

    const wrapper = await mountView()

    expect(wrapper.text()).toContain('Не удалось загрузить инвентарь. Обнови страницу.')
  })

  it('locks every button while an action runs', async () => {
    vi.mocked(equipItem).mockReturnValue(new Promise(() => {}))
    const wrapper = await mountView(armed)

    await itemButton(wrapper, 'Повязка').trigger('click')

    expect(
      wrapper.findAll('button').every((button) => button.attributes('disabled') !== undefined),
    ).toBe(true)
  })

  it('shows why an item could not be equipped', async () => {
    vi.mocked(equipItem).mockRejectedValue(
      axiosError(422, { errors: { item_id: ['Выбранное значение для предмет некорректно.'] } }),
    )
    const wrapper = await mountView()

    await itemButton(wrapper, 'Повязка').trigger('click')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain(
      'Выбранное значение для предмет некорректно.',
    )
  })
})
