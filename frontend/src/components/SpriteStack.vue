<script setup lang="ts">
import { computed } from 'vue'

import sheetUrl from '@/assets/sprites/roguelike-characters.png'
import { SHEET_HEIGHT, SHEET_WIDTH, TILE_SIZE, TILE_STEP, type Tile } from '@/game/appearance'

// Draws tiles of the sprite sheet on top of each other, first tile at the bottom
const props = withDefaults(
  defineProps<{
    tiles: readonly Tile[]
    label: string
    scale?: number
    // Sheet pixels to shift every layer by, e.g. to centre an item in its icon
    offset?: { x: number; y: number }
  }>(),
  { scale: 4 },
)

// Only integer scales keep pixel art crisp and neighbouring tiles out of frame
const pixelScale = computed(() => Math.max(1, Math.round(props.scale)))
const size = computed(() => `${TILE_SIZE * pixelScale.value}px`)

// The layer moves, not its background: shifting the background would pull a neighbouring tile into the frame
const shift = computed(() =>
  props.offset === undefined
    ? {}
    : {
        transform: `translate(${props.offset.x * pixelScale.value}px, ${props.offset.y * pixelScale.value}px)`,
      },
)

const layers = computed(() =>
  props.tiles.map((tile) => ({
    backgroundImage: `url(${sheetUrl})`,
    backgroundSize: `${SHEET_WIDTH * pixelScale.value}px ${SHEET_HEIGHT * pixelScale.value}px`,
    backgroundPosition: `-${tile.col * TILE_STEP * pixelScale.value}px -${tile.row * TILE_STEP * pixelScale.value}px`,
    ...shift.value,
  })),
)
</script>

<template>
  <div
    role="img"
    :aria-label="label"
    class="relative shrink-0 overflow-hidden"
    :style="{ width: size, height: size }"
    data-testid="sprite-stack"
  >
    <div
      v-for="(style, index) in layers"
      :key="index"
      class="absolute inset-0 [image-rendering:pixelated]"
      :style="style"
      data-testid="sprite-layer"
    ></div>
  </div>
</template>
