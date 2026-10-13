<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'

import { equipItem, fetchInventory, unequipSlot } from '@/api/equipment'
import type { Character, InventoryItem, Item, ItemSlot } from '@/api/types'
import CharacterSprite from '@/components/CharacterSprite.vue'
import ItemCard from '@/components/ItemCard.vue'
import SpriteStack from '@/components/SpriteStack.vue'
import { useFormSubmit } from '@/composables/useFormSubmit'
import { ICON_OFFSETS, ITEM_SLOTS, SLOT_LABELS, wornItems } from '@/game/equipment'
import { useAuthStore } from '@/stores/auth'

const HERO_SCALE = 10
const SLOT_ICON_SCALE = 2
const LOAD_ERROR_MESSAGE = 'Не удалось загрузить инвентарь. Обнови страницу.'

const auth = useAuthStore()

const character = computed(() => auth.user?.character ?? null)
const inventory = ref<InventoryItem[]>([])
const loaded = ref(false)
const loadError = ref<string | null>(null)
const { errors, generalError, submitting, submit } = useFormSubmit()

const equippedBySlot = computed(
  () => new Map((character.value?.equipment ?? []).map((worn) => [worn.slot, worn.item])),
)
const wornBySlot = computed(() =>
  ITEM_SLOTS.map((slot) => ({ slot, item: equippedBySlot.value.get(slot) ?? null })),
)
const inventoryBySlot = computed(() =>
  ITEM_SLOTS.map((slot) => ({
    slot,
    items: inventory.value.map(({ item }) => item).filter((item) => item.slot === slot),
  })),
)
const actionError = computed(() => errors.value.item_id?.[0] ?? generalError.value)

async function loadInventory(): Promise<void> {
  try {
    inventory.value = await fetchInventory()
    loaded.value = true
    loadError.value = null
  } catch (error) {
    console.error('Failed to load the inventory', error)
    loadError.value = LOAD_ERROR_MESSAGE
  }
}

// The API answers with the whole character, so the hero updates on every screen at once
async function run(action: () => Promise<Character>): Promise<void> {
  await submit(async () => {
    auth.setCharacter(await action())
  })
}

function onEquip(item: Item): Promise<void> {
  return run(() => equipItem(item.slot, item.id))
}

function onUnequip(slot: ItemSlot): Promise<void> {
  return run(() => unequipSlot(slot))
}

onMounted(loadInventory)
</script>

<template>
  <div class="mx-auto flex max-w-5xl flex-col gap-8 px-4 py-8">
    <header class="flex flex-wrap items-center gap-4">
      <RouterLink :to="{ name: 'home' }" class="btn-secondary">← На главную</RouterLink>
      <h1 class="text-2xl font-bold text-accent">Снаряжение</h1>
    </header>

    <div v-if="character" class="flex flex-col gap-6 md:flex-row md:items-start">
      <aside class="card flex flex-col items-center gap-4">
        <CharacterSprite
          :appearance="character.appearance"
          :label="character.name"
          :equipment="wornItems(character)"
          :scale="HERO_SCALE"
        />
        <p class="text-2xl font-semibold">{{ character.name }}</p>
      </aside>

      <section class="card flex min-w-0 flex-1 flex-col gap-3" aria-label="Надето">
        <h2 class="text-lg font-semibold">Надето</h2>
        <ul class="divide-y divide-white/10">
          <li v-for="worn in wornBySlot" :key="worn.slot" class="flex items-center gap-3 py-2">
            <span class="w-20 shrink-0 text-sm text-slate-400">{{ SLOT_LABELS[worn.slot] }}</span>
            <template v-if="worn.item">
              <!-- Decorative: the item name follows as text -->
              <span aria-hidden="true">
                <SpriteStack
                  :tiles="[worn.item.sprite]"
                  :label="worn.item.name"
                  :scale="SLOT_ICON_SCALE"
                  :offset="ICON_OFFSETS[worn.slot]"
                />
              </span>
              <span class="min-w-0 flex-1 break-words">{{ worn.item.name }}</span>
              <button
                type="button"
                class="btn-secondary px-2 py-1"
                :aria-label="`Снять: ${SLOT_LABELS[worn.slot]}`"
                :disabled="submitting"
                @click="onUnequip(worn.slot)"
              >
                Снять
              </button>
            </template>
            <span v-else class="text-slate-500">Пусто</span>
          </li>
        </ul>
        <p v-if="actionError" class="text-sm text-danger" role="alert">{{ actionError }}</p>
      </section>
    </div>

    <section class="card flex flex-col gap-4" aria-label="Инвентарь">
      <h2 class="text-lg font-semibold">Инвентарь</h2>
      <p v-if="loadError" class="text-sm text-danger" role="alert">{{ loadError }}</p>
      <p v-else-if="!loaded" class="text-slate-400">Загружаем инвентарь…</p>
      <template v-else>
        <div v-for="group in inventoryBySlot" :key="group.slot" class="flex flex-col gap-2">
          <h3 class="text-sm text-slate-400">{{ SLOT_LABELS[group.slot] }}</h3>
          <div v-if="group.items.length > 0" class="grid grid-cols-3 gap-2 sm:grid-cols-5">
            <ItemCard
              v-for="item in group.items"
              :key="item.id"
              :item="item"
              :equipped="equippedBySlot.get(item.slot)?.id === item.id"
              :disabled="submitting"
              @equip="onEquip(item)"
            />
          </div>
          <p v-else class="text-sm text-slate-500">Нет вещей</p>
        </div>
      </template>
    </section>
  </div>
</template>
