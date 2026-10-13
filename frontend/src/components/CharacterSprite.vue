<script setup lang="ts">
import { computed } from 'vue'

import SpriteStack from '@/components/SpriteStack.vue'
import { appearanceLayers, type Appearance } from '@/game/appearance'
import type { WornItem } from '@/game/equipment'

const props = withDefaults(
  defineProps<{
    appearance: Appearance
    label: string
    scale?: number
    equipment?: readonly WornItem[]
  }>(),
  { scale: 4, equipment: () => [] },
)

const tiles = computed(() => appearanceLayers(props.appearance, props.equipment))
</script>

<template>
  <SpriteStack :tiles="tiles" :label="label" :scale="scale" data-testid="character-sprite" />
</template>
