<script setup lang="ts">
import { computed } from 'vue'

import type { ShopItem } from '@/api/types'
import SpriteStack from '@/components/SpriteStack.vue'
import { ICON_OFFSETS } from '@/game/equipment'
import { shopOffer } from '@/game/shop'

const ICON_SCALE = 3

const props = defineProps<{
  entry: ShopItem
  gold: number
  disabled: boolean
}>()

const emit = defineEmits<{
  buy: []
}>()

const offer = computed(() => shopOffer(props.entry, props.gold))
</script>

<template>
  <!-- tabindex -1: the shop moves the focus here after a purchase removes the buy button -->
  <article
    class="flex flex-col items-center gap-1 rounded-lg border bg-ink p-2 text-center outline-none focus-visible:ring-2 focus-visible:ring-accent"
    :class="offer.kind === 'locked' ? 'border-white/5' : 'border-white/10'"
    :aria-label="entry.item.name"
    :data-item-id="entry.item.id"
    tabindex="-1"
    data-testid="shop-item"
  >
    <!-- Decorative: the title below already names the item. A locked item dims only its icon,
         so the text keeps its contrast -->
    <span
      aria-hidden="true"
      data-testid="shop-item-icon"
      :class="{ 'opacity-50': offer.kind === 'locked' }"
    >
      <SpriteStack
        :tiles="[entry.item.sprite]"
        :label="entry.item.name"
        :scale="ICON_SCALE"
        :offset="ICON_OFFSETS[entry.item.slot]"
      />
    </span>
    <span class="text-sm">{{ entry.item.name }}</span>

    <span v-if="offer.kind === 'owned'" class="text-xs text-success">Куплено</span>
    <span v-else-if="offer.kind === 'locked'" class="text-xs text-slate-400">
      С уровня {{ offer.level }}
    </span>
    <template v-else>
      <button
        type="button"
        class="btn-primary px-2 py-1 text-xs"
        :disabled="disabled || offer.kind === 'too-expensive'"
        :aria-label="`Купить «${entry.item.name}» за ${entry.item.price} золота`"
        @click="emit('buy')"
      >
        Купить за {{ entry.item.price }}
      </button>
      <span v-if="offer.kind === 'too-expensive'" class="text-xs text-slate-400">
        Не хватает {{ offer.missing }}
      </span>
    </template>
  </article>
</template>
