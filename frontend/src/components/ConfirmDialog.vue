<script setup lang="ts">
import { onBeforeUnmount, onMounted, useId, useTemplateRef } from 'vue'

// busy: an action of the caller is still running, so a confirmation now would be dropped
withDefaults(
  defineProps<{
    title: string
    message: string
    confirmLabel: string
    busy?: boolean
  }>(),
  { busy: false },
)

const emit = defineEmits<{
  confirm: []
  cancel: []
}>()

const titleId = useId()
const messageId = useId()
const dialog = useTemplateRef<HTMLDialogElement>('dialog')

// The parent shows the dialog with v-if, so being mounted means being open
onMounted(() => dialog.value?.showModal())
// close() hands the focus back to the button that opened the dialog
onBeforeUnmount(() => dialog.value?.close())

let pressedOnBackdrop = false

function onMouseDown(event: MouseEvent): void {
  pressedOnBackdrop = event.target === dialog.value
}

// A click on the dialog box itself, not on its content, lands on the backdrop. A press that
// starts in the content (selecting the message) and ends outside is targeted there too, so
// only a click that also started on the backdrop counts
function onClick(event: MouseEvent): void {
  if (pressedOnBackdrop && event.target === dialog.value) {
    emit('cancel')
  }

  pressedOnBackdrop = false
}
</script>

<template>
  <!-- Escape is turned into "cancel" so the parent stays the only one closing the dialog.
       The padding sits on the inner box: a click on it must not count as the backdrop.
       max-w-sm replaces the browser's own max-width, so the side gap on a phone is set here -->
  <dialog
    ref="dialog"
    class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-xl bg-panel text-slate-100 shadow-lg backdrop:bg-black/60"
    :aria-labelledby="titleId"
    :aria-describedby="messageId"
    data-testid="confirm-dialog"
    @cancel.prevent="emit('cancel')"
    @mousedown="onMouseDown"
    @click="onClick"
  >
    <div class="flex flex-col gap-4 p-6">
      <h2 :id="titleId" class="text-lg font-semibold">{{ title }}</h2>
      <p :id="messageId" class="break-words text-slate-300">{{ message }}</p>
      <div class="flex justify-end gap-2">
        <!-- The safe choice is focused first: a stray Enter neither spends gold nor deletes -->
        <button type="button" class="btn-secondary" autofocus @click="emit('cancel')">
          Отмена
        </button>
        <button type="button" class="btn-primary" :disabled="busy" @click="emit('confirm')">
          {{ confirmLabel }}
        </button>
      </div>
    </div>
  </dialog>
</template>
