<script setup lang="ts">
import type { Item } from '@/api/types'
import SpriteStack from '@/components/SpriteStack.vue'
import { ICON_OFFSETS } from '@/game/equipment'

const ICON_SCALE = 3

defineProps<{
  item: Item
  equipped: boolean
  disabled: boolean
}>()

const emit = defineEmits<{
  equip: []
}>()
</script>

<template>
  <button
    type="button"
    class="flex flex-col items-center gap-1 rounded-lg border bg-ink p-2 text-center transition disabled:cursor-not-allowed"
    :class="equipped ? 'border-accent' : 'border-white/10 hover:border-accent disabled:opacity-60'"
    :aria-pressed="equipped"
    :disabled="disabled || equipped"
    @click="emit('equip')"
  >
    <!-- Decorative: the title below already names the item. The offset centres items
         drawn where they sit on the hero -->
    <span aria-hidden="true">
      <SpriteStack
        :tiles="[item.sprite]"
        :label="item.name"
        :scale="ICON_SCALE"
        :offset="ICON_OFFSETS[item.slot]"
      />
    </span>
    <span class="text-sm">{{ item.name }}</span>
    <span v-if="equipped" class="text-xs text-accent">Надето</span>
  </button>
</template>
