<script setup lang="ts">
import { computed, nextTick, onMounted, ref, useTemplateRef } from 'vue'

import { buyItem, fetchShop, markShopSeen } from '@/api/shop'
import type { ShopItem } from '@/api/types'
import CharacterSprite from '@/components/CharacterSprite.vue'
import ConfirmDialog from '@/components/ConfirmDialog.vue'
import ShopItemCard from '@/components/ShopItemCard.vue'
import { useFormSubmit } from '@/composables/useFormSubmit'
import { ITEM_SLOTS, SLOT_LABELS, wornItems } from '@/game/equipment'
import { useAuthStore } from '@/stores/auth'

const HERO_SCALE = 8
const LOAD_ERROR_MESSAGE = 'Не удалось загрузить магазин. Обнови страницу.'

const auth = useAuthStore()

const character = computed(() => auth.user?.character ?? null)
const catalog = ref<ShopItem[]>([])
const loaded = ref(false)
const loadError = ref<string | null>(null)
const pendingPurchase = ref<ShopItem | null>(null)
const goods = useTemplateRef<HTMLElement>('goods')
const { errors, generalError, submitting, submit } = useFormSubmit()

const catalogBySlot = computed(() =>
  ITEM_SLOTS.map((slot) => ({
    slot,
    entries: catalog.value.filter((entry) => entry.item.slot === slot),
  })),
)
const actionError = computed(() => errors.value.item_id?.[0] ?? generalError.value)

async function loadShop(): Promise<void> {
  try {
    catalog.value = await fetchShop()
    loaded.value = true
    loadError.value = null
  } catch (error) {
    console.error('Failed to load the shop', error)
    loadError.value = LOAD_ERROR_MESSAGE
  }
}

// The "new" mark is secondary: if clearing it fails, the shop still works.
// Only the shop part is taken: a purchase may finish first, and its gold must not be overwritten
async function markSeen(): Promise<void> {
  try {
    const { shop } = await markShopSeen()
    const current = auth.user?.character

    if (current) {
      auth.setCharacter({ ...current, shop })
    }
  } catch (error) {
    console.error('Failed to mark the shop as seen', error)
  }
}

// Spent gold cannot be returned, so the player confirms first
function onBuy(entry: ShopItem): void {
  pendingPurchase.value = entry
}

// The dialog closes before the request, so a refusal is not hidden behind its backdrop
async function confirmPurchase(): Promise<void> {
  const entry = pendingPurchase.value
  pendingPurchase.value = null

  if (entry === null) {
    return
  }

  await submit(async () => {
    auth.setCharacter(await buyItem(entry.item.id))
    catalog.value = catalog.value.map((other) =>
      other.item.id === entry.item.id ? { ...other, owned: true } : other,
    )
  })
  await focusCard(entry)
}

// The buy button that opened the dialog is disabled during the request and gone after a
// purchase, so the focus would drop to <body>; the card keeps the player in place
async function focusCard(entry: ShopItem): Promise<void> {
  await nextTick()
  goods.value?.querySelector<HTMLElement>(`[data-item-id="${entry.item.id}"]`)?.focus()
}

onMounted(() => Promise.all([loadShop(), markSeen()]))
</script>

<template>
  <div class="mx-auto flex max-w-5xl flex-col gap-8 px-4 py-8">
    <header class="flex flex-wrap items-center gap-4">
      <RouterLink :to="{ name: 'home' }" class="btn-secondary">← На главную</RouterLink>
      <h1 class="mr-auto text-2xl font-bold text-accent">Магазин</h1>
      <RouterLink :to="{ name: 'equipment' }" class="btn-secondary">Снаряжение</RouterLink>
    </header>

    <div class="flex flex-col gap-6 md:flex-row md:items-start">
      <aside v-if="character" class="card flex flex-col items-center gap-2 md:sticky md:top-8">
        <CharacterSprite
          :appearance="character.appearance"
          :label="character.name"
          :equipment="wornItems(character)"
          :scale="HERO_SCALE"
        />
        <p class="text-2xl font-semibold">{{ character.name }}</p>
        <p>Уровень {{ character.progress.level }}</p>
        <p data-testid="shop-gold">Золото: {{ character.gold }}</p>
      </aside>

      <section ref="goods" class="card flex min-w-0 flex-1 flex-col gap-4" aria-label="Товары">
        <h2 class="text-lg font-semibold">Товары</h2>
        <p v-if="loadError" class="text-sm text-danger" role="alert">{{ loadError }}</p>
        <p v-else-if="!loaded" class="text-slate-400">Загружаем магазин…</p>
        <template v-else>
          <div v-for="group in catalogBySlot" :key="group.slot" class="flex flex-col gap-2">
            <h3 class="text-sm text-slate-400">{{ SLOT_LABELS[group.slot] }}</h3>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
              <ShopItemCard
                v-for="entry in group.entries"
                :key="entry.item.id"
                :entry="entry"
                :gold="character?.gold ?? 0"
                :disabled="submitting"
                @buy="onBuy(entry)"
              />
            </div>
          </div>
          <p v-if="actionError" class="text-sm text-danger" role="alert">{{ actionError }}</p>
        </template>
      </section>
    </div>

    <ConfirmDialog
      v-if="pendingPurchase"
      title="Купить предмет?"
      :message="`«${pendingPurchase.item.name}» за ${pendingPurchase.item.price} золота. Потраченное золото не вернуть.`"
      confirm-label="Купить"
      :busy="submitting"
      @confirm="confirmPurchase"
      @cancel="pendingPurchase = null"
    />
  </div>
</template>
