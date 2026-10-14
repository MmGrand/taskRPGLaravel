# Plan: UI-гигиена

## Summary
Сайт перестаёт выглядеть как отладочный стенд. Из подвала уходит техническая строка «API: ok · database: ok · redis: ok». Подтверждения покупки и удаления задачи показываются в окне в стиле сайта (`ConfirmDialog` на нативном `<dialog>`) вместо `window.confirm`. Все активные кнопки получают курсор-указатель, а ✎ и 🗑 становятся SVG-иконками, которые одинаково выглядят во всех ОС.

## User Story
Я как игрок хочу, чтобы интерфейс вёл себя как цельный сайт: без служебных строк, системных окон браузера и «мёртвого» курсора над кнопками, чтобы им было приятно пользоваться.

## Problem → Solution
Внизу каждой страницы висит статус API с внутренностями бэкенда. Подтверждения идут через `window.confirm`, а это серое окно браузера, которое не совпадает со стилем и блокирует вкладку. Tailwind 4 больше не ставит кнопкам `cursor: pointer`, поэтому над ними обычная стрелка. Иконки ✎ и 🗑 сделаны текстовыми символами, и эмодзи корзины в разных ОС выглядит по-разному. → Строки нет. Подтверждение открывается в модальном окне сайта: фокус внутри, Esc и клик по фону отменяют, фокус возвращается на кнопку. Над активными кнопками рука, над неактивными `not-allowed`. Иконки нарисованы SVG в цвет текста.

## Metadata
- **Complexity**: Medium
- **Source PRD**: `.claude/PRPs/prds/task-rpg.prd.md`
- **PRD Phase**: 8 — UI-гигиена
- **Estimated Files**: ~15 (создать 3, изменить 7, удалить 5)

## Decisions
Замечания автора от 2026-10-14: «строка про API не нужна на боевом», «подтверждения через модалку браузера, а не сайта», «наведение на кнопки не меняет курсор».

Решения по умолчанию (не спрашивались):
- **`ApiStatus` удаляется целиком**, а не прячется за `import.meta.env.DEV`. Разработчику строка тоже не нужна: health проверяют Docker и `curl /api/health`. Вместе с компонентом удаляются ставшие мёртвыми `api/health.ts`, его тест и типы `HealthData`/`CheckStatus`. Бэкенд-эндпоинт `/api/health` остаётся.
- **Диалог показывается через `v-if` у родителя**, а не через проп `open`. `ConfirmDialog` открывается в `onMounted` и закрывается в `onBeforeUnmount`. Так в списке из 100 задач нет 100 скрытых `<dialog>`, а у компонента нет состояния, которое надо синхронизировать.
- **Один `ConfirmDialog` на месте вызова**, без глобального `useConfirm()` с промисом и хостом в `App.vue`. Мест вызова два, а глобальный хост пришлось бы монтировать в тестах каждой вьюхи (YAGNI).
- **Иконки инлайн-SVG** по контурам Lucide (лицензия ISC), без npm-пакета ради двух иконок.
- Отдельного «опасного» стиля кнопки для удаления нет: кнопка подтверждения `btn-primary` в обоих диалогах.

Уже сделано в фазе 7, из скоупа фазы 8 убрано: смещение иконок предметов (`ICON_OFFSETS` в `frontend/src/game/equipment.ts:22`) и явные цвета неактивных кнопок (`main.css:66-73`).

---

## UX Design

### Before
```
┌ Магазин ───────────────────────────┐
│ [Купить за 30] ──клик──►           │
│ ┌ localhost:5173 says ───────────┐ │   системное окно браузера,
│ │ Купить «Зелёная бандана» за 30 │ │   курсор над кнопками — стрелка
│ │ золота?        [Cancel] [OK]   │ │
│ └────────────────────────────────┘ │
└────────────────────────────────────┘
● API: ok   database: ok   redis: ok        ← подвал на каждой странице
```

### After
```
┌ Магазин ───────────────────────────┐
│░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░│   затемнённый фон (::backdrop)
│░░ ┌ Купить предмет? ───────────┐ ░░│   окно в цветах сайта (bg-panel)
│░░ │ «Зелёная бандана» за 30    │ ░░│
│░░ │ золота. Потраченное золото │ ░░│
│░░ │ не вернуть.                │ ░░│
│░░ │        [Отмена] [Купить]   │ ░░│   фокус на «Отмена», Esc/клик по фону = отмена
│░░ └────────────────────────────┘ ░░│
└────────────────────────────────────┘
                                           подвала нет
Задача: [☐] 30 минут Go   [✏️svg] [🗑svg]   курсор-рука над активными кнопками
```

### Interaction Changes
| Touchpoint | Before | After | Notes |
|---|---|---|---|
| Подвал всех страниц | «API: ok» и проверки БД/Redis | Нет | `/api/health` на бэкенде остаётся |
| «Купить за N» | `window.confirm` | `ConfirmDialog` «Купить предмет?» | «Купить» → `buyItem`, «Отмена»/Esc/фон → ничего |
| «Удалить» у задачи | `window.confirm` | `ConfirmDialog` «Удалить задачу?» | «Удалить» → emit `remove` |
| Наведение на кнопку | Стрелка | Рука; у `disabled` — `not-allowed` | Базовое правило в `main.css` |
| ✎ и 🗑 | Текстовые символы | SVG `size-4`, `currentColor` | `aria-label` на кнопках сохраняется |

---

## Mandatory Reading

| Priority | File | Lines | Why |
|---|---|---|---|
| P0 | `frontend/src/views/ShopView.vue` | 57-69, 74-118 | `onBuy` с `window.confirm`, место для диалога |
| P0 | `frontend/src/components/TaskItem.vue` | 47-51, 85-102 | `onRemove` с `window.confirm`, кнопки ✎ и 🗑 |
| P0 | `frontend/src/assets/main.css` | 57-74 | `@layer components`, кнопки, токены цветов |
| P0 | `frontend/src/views/__tests__/ShopView.spec.ts` | 92-145 | 4 теста на `window.confirm`, их надо переписать |
| P0 | `frontend/src/components/__tests__/TaskItem.spec.ts` | 38-46, 107-118 | «disables every action» и тест удаления |
| P1 | `frontend/src/App.vue` | all | Подвал с `ApiStatus` |
| P1 | `frontend/vitest.config.ts` | all | Сюда добавить `setupFiles` |
| P1 | `frontend/src/api/types.ts` | 6-11 | Удаляемые `CheckStatus`, `HealthData` |
| P2 | `frontend/src/components/LevelUpBanner.vue` | all | Стиль маленького компонента и комментариев |
| P2 | `frontend/src/components/TaskList.vue` | 52-62 | Куда уходит `remove` и как работает `disabled` |

## External Documentation

| Topic | Source | Key Takeaway |
|---|---|---|
| `<dialog>` и `showModal()` | MDN HTMLDialogElement | `showModal()` кладёт окно в top layer, делает фон инертным, фокусирует элемент с `autofocus`. Esc шлёт событие `cancel` (можно `preventDefault`), затем `close`. `close()` возвращает фокус на элемент, который был в фокусе до открытия |
| Tailwind 4: курсор кнопок | Tailwind v4 upgrade guide, «Buttons use the default cursor» | Preflight ставит кнопкам `cursor: default`; чтобы вернуть руку, нужно правило `@layer base { button:not(:disabled), [role="button"]:not(:disabled) { cursor: pointer; } }` |
| Tailwind 4 preflight и `<dialog>` | Tailwind preflight | Preflight обнуляет `margin` и `padding` у всех элементов, и диалог перестаёт центрироваться UA-стилем `margin: auto`. Нужен класс `m-auto`. Фон окна стилизуется вариантом `backdrop:` |
| jsdom 30.1.2 | `node_modules/jsdom/lib/jsdom/living/nodes/HTMLDialogElement-impl.js` | Класс пустой: **нет `showModal()` и `close()`**. Для тестов нужен полифил |
| Lucide pencil, trash-2 | lucide.dev (ISC) | Контуры для `viewBox="0 0 24 24"`, `stroke="currentColor"`, `stroke-width="2"` |

---

## Patterns to Mirror

### COMPONENT (script setup, defineProps/defineEmits с кортежами)
```ts
// SOURCE: frontend/src/components/TaskItem.vue:6-15
const props = defineProps<{
  task: Task
  disabled: boolean
}>()

const emit = defineEmits<{
  toggle: [completed: boolean]
  rename: [title: string]
  remove: []
}>()
```

### TEMPLATE REF
```ts
// SOURCE: frontend/src/components/TaskItem.vue:2, 19
import { nextTick, ref, useTemplateRef } from 'vue'
const titleInput = useTemplateRef<HTMLInputElement>('titleInput')
```

### COMMENTS (английский, объясняют «почему», над кодом или в шаблоне)
```vue
<!-- SOURCE: frontend/src/components/ShopItemCard.vue:31-32 -->
<!-- Decorative: the title below already names the item. A locked item dims only its icon,
     so the text keeps its contrast -->
<span aria-hidden="true" ...>
```

### ERROR_HANDLING / LOGGING (без изменений, но поведение надо сохранить)
```ts
// SOURCE: frontend/src/views/ShopView.vue:63-68
await submit(async () => {
  auth.setCharacter(await buyItem(entry.item.id))
  catalog.value = catalog.value.map((other) =>
    other.item.id === entry.item.id ? { ...other, owned: true } : other,
  )
})
```
Ошибки покупки показывает `useFormSubmit` через `actionError` с `role="alert"`. Диалог закрывается **до** `submit`, иначе ошибка окажется под модальным фоном.

### CSS (компонентные классы через @apply)
```css
/* SOURCE: frontend/src/assets/main.css:61-74 */
@layer components {
  .card {
    @apply rounded-xl bg-panel p-6 shadow-lg;
  }
  .btn-primary { ... disabled:cursor-not-allowed ... }
}
```

### TEST_STRUCTURE (attachTo + очистка, поиск по aria-label)
```ts
// SOURCE: frontend/src/components/__tests__/TaskItem.spec.ts:8-16
function mountItem(task: Task = dailyTaskFixture, disabled = false) {
  return mount(TaskItem, { props: { task, disabled }, attachTo: document.body })
}

describe('TaskItem', () => {
  afterEach(() => {
    vi.restoreAllMocks()
    document.body.innerHTML = ''
  })
```

---

## Files to Change

| File | Action | Justification |
|---|---|---|
| `frontend/src/components/ConfirmDialog.vue` | CREATE | Модальное окно подтверждения |
| `frontend/src/components/__tests__/ConfirmDialog.spec.ts` | CREATE | Тесты окна |
| `frontend/src/__tests__/setup.ts` | CREATE | Полифил `showModal`/`close` для jsdom |
| `frontend/vitest.config.ts` | UPDATE | `setupFiles` |
| `frontend/src/views/ShopView.vue` | UPDATE | Диалог вместо `window.confirm` |
| `frontend/src/views/__tests__/ShopView.spec.ts` | UPDATE | Тесты через диалог |
| `frontend/src/components/TaskItem.vue` | UPDATE | Диалог вместо `window.confirm`, SVG-иконки |
| `frontend/src/components/__tests__/TaskItem.spec.ts` | UPDATE | Тесты через диалог, иконки |
| `frontend/src/assets/main.css` | UPDATE | `cursor: pointer` в `@layer base` |
| `frontend/src/App.vue` | UPDATE | Убрать подвал |
| `frontend/src/components/ApiStatus.vue` | DELETE | Строка статуса не нужна |
| `frontend/src/components/__tests__/ApiStatus.spec.ts` | DELETE | Тест удалённого компонента |
| `frontend/src/api/health.ts` | DELETE | Единственным потребителем был `ApiStatus` |
| `frontend/src/api/__tests__/health.spec.ts` | DELETE | Тест удалённого модуля |
| `frontend/src/api/types.ts` | UPDATE | Удалить `CheckStatus`, `HealthData` |
| `docs/screenshots/*.png` | UPDATE | Переснять кадры, где виден подвал |
| `.claude/PRPs/prds/task-rpg.prd.md` | UPDATE | Статус фазы, ссылка на план |

## NOT Building

- Глобальный сервис `useConfirm()` и хост диалогов в `App.vue`.
- Тосты и уведомления вместо `role="alert"`-строк.
- Отдельный «опасный» (красный) стиль кнопки.
- Кастомные ✎/🗑 в других местах: эмодзи есть только в `TaskItem`.
- Изменения бэкенда: `/api/health` остаётся как есть (docker healthcheck, мониторинг).
- Смещение иконок и контраст неактивных кнопок: сделано в фазе 7.

---

## Step-by-Step Tasks

### Task 1: Полифил `<dialog>` для jsdom
- **ACTION**: Создать `frontend/src/__tests__/setup.ts` и подключить его в `vitest.config.ts` через `test.setupFiles: ['./src/__tests__/setup.ts']`.
- **IMPLEMENT**:
  ```ts
  // jsdom has <dialog> without showModal() and close(); this mimics only what the components use
  const proto = HTMLDialogElement.prototype

  if (!('showModal' in proto)) {
    proto.showModal = function (this: HTMLDialogElement) {
      this.setAttribute('open', '')
    }
    proto.close = function (this: HTMLDialogElement) {
      this.removeAttribute('open')
      this.dispatchEvent(new Event('close'))
    }
  }
  ```
  Если `'open' in proto` ложно (проверить в jsdom), добавить геттер `Object.defineProperty(proto, 'open', { get() { return this.hasAttribute('open') }, configurable: true })`.
- **MIRROR**: Комментарии на английском, «почему».
- **GOTCHA**: Путь лежит под `src/**/__tests__/**`, поэтому уже исключён из покрытия (`vitest.config.ts`, `coverage.exclude`). Нужно проверить, что `vue-tsc --build` видит файл: tsconfig для тестов (`tsconfig.vitest.json`) должен включать `src/**/__tests__/*`. Если не включает, добавить.
- **VALIDATE**: `npm run test:unit -- run` зелёный, как раньше (полифил ничего не ломает).

### Task 2: `ConfirmDialog.vue`
- **ACTION**: Создать компонент.
- **IMPLEMENT**:
  ```vue
  <script setup lang="ts">
  import { onBeforeUnmount, onMounted, useId, useTemplateRef } from 'vue'

  defineProps<{
    title: string
    message: string
    confirmLabel: string
  }>()

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

  // A click on the dialog box itself, not on its content, lands on the backdrop
  function onClick(event: MouseEvent): void {
    if (event.target === dialog.value) {
      emit('cancel')
    }
  }
  </script>

  <template>
    <!-- Escape is turned into "cancel" so the parent stays the only one closing the dialog -->
    <dialog
      ref="dialog"
      class="m-auto max-w-sm rounded-xl bg-panel text-slate-100 shadow-lg backdrop:bg-black/60"
      :aria-labelledby="titleId"
      :aria-describedby="messageId"
      data-testid="confirm-dialog"
      @cancel.prevent="emit('cancel')"
      @click="onClick"
    >
      <div class="flex flex-col gap-4 p-6">
        <h2 :id="titleId" class="text-lg font-semibold">{{ title }}</h2>
        <p :id="messageId" class="text-slate-300">{{ message }}</p>
        <div class="flex justify-end gap-2">
          <button type="button" class="btn-secondary" autofocus @click="emit('cancel')">
            Отмена
          </button>
          <button type="button" class="btn-primary" @click="emit('confirm')">
            {{ confirmLabel }}
          </button>
        </div>
      </div>
    </dialog>
  </template>
  ```
- **MIRROR**: COMPONENT, TEMPLATE REF, COMMENTS.
- **IMPORTS**: `vue` (`useId` есть с Vue 3.5, в проекте `^3.5.42`).
- **GOTCHA**:
  - Без `m-auto` preflight Tailwind сдвинет окно в левый верхний угол.
  - Паддинг должен быть на внутреннем `div`, а не на `<dialog>`, иначе клик по паддингу примется за клик по фону.
  - `autofocus` на «Отмена»: случайный Enter не потратит золото и не удалит задачу.
  - ESLint `vuejs-accessibility` в проекте не подключён. Если `no-autofocus` всё же сработает, autofocus внутри модального окна — рекомендуемый способ, поэтому правило можно отключить для этой строки с комментарием.
- **VALIDATE**: Тесты Task 3.

### Task 3: Тесты `ConfirmDialog`
- **ACTION**: Создать `frontend/src/components/__tests__/ConfirmDialog.spec.ts`.
- **IMPLEMENT**: Монтировать с `attachTo: document.body`, в `afterEach` делать `document.body.innerHTML = ''`. Кейсы:
  1. Открывается при монтировании: `dialog.element.open === true`, видны title и message, `aria-labelledby` указывает на заголовок.
  2. Кнопка с `confirmLabel` эмитит `confirm`, «Отмена» эмитит `cancel`.
  3. Событие `cancel` на `<dialog>` (Esc) эмитит `cancel` и вызывает `preventDefault` (`new Event('cancel', { cancelable: true })`, затем `defaultPrevented === true`).
  4. Клик по самому `<dialog>` эмитит `cancel`, клик по тексту сообщения ничего не эмитит.
  5. `unmount()` вызывает `close()` (`vi.spyOn(HTMLDialogElement.prototype, 'close')`).
- **MIRROR**: TEST_STRUCTURE.
- **VALIDATE**: `npm run test:unit -- run ConfirmDialog` зелёный.

### Task 4: Покупка через диалог (`ShopView`)
- **ACTION**: Заменить `window.confirm` состоянием `pendingPurchase`.
- **IMPLEMENT**:
  ```ts
  const pendingPurchase = ref<ShopItem | null>(null)

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

    await submit(async () => { /* прежнее тело без изменений */ })
  }
  ```
  В шаблоне в конце корневого `div`:
  ```vue
  <ConfirmDialog
    v-if="pendingPurchase"
    title="Купить предмет?"
    :message="`«${pendingPurchase.item.name}» за ${pendingPurchase.item.price} золота. Потраченное золото не вернуть.`"
    confirm-label="Купить"
    @confirm="confirmPurchase"
    @cancel="pendingPurchase = null"
  />
  ```
- **IMPORTS**: `import ConfirmDialog from '@/components/ConfirmDialog.vue'`.
- **MIRROR**: ERROR_HANDLING. Тело `submit` не меняется.
- **GOTCHA**: Если строка `:message` слишком длинная для Prettier, вынести её в `computed` (`purchaseMessage`).
- **VALIDATE**: Тесты Task 5.

### Task 5: Тесты `ShopView`
- **ACTION**: Переписать 4 теста, которые используют `vi.spyOn(window, 'confirm')`, и добавить новые.
- **IMPLEMENT**: Добавить хелпер:
  ```ts
  async function answerDialog(wrapper: ViewWrapper, label: 'Купить' | 'Отмена') {
    const button = wrapper
      .get('[data-testid="confirm-dialog"]')
      .findAll('button')
      .find((candidate) => candidate.text() === label)
    await button?.trigger('click')
    await flushPromises()
  }
  ```
  - «buys a confirmed item»: клик по карточке, затем проверка текста диалога ««Зелёная бандана» за 30 золота», затем `answerDialog(wrapper, 'Купить')`, затем прежние ожидания. Диалог исчез.
  - «keeps the gold…» и «shows why a purchase was refused»: вместо `confirm` использовать `answerDialog(..., 'Купить')`.
  - «buys nothing when the player changes their mind»: `answerDialog(..., 'Отмена')`, затем `buyItem` не вызван, диалога нет.
  - Новый: без ответа диалог открыт, и `buyItem` не вызван.
  - В «shows why a purchase was refused» `wrapper.get('[role="alert"]')` по-прежнему однозначен: в диалоге нет `role="alert"`.
- **MIRROR**: стиль файла: `card(wrapper, name)`, `flushPromises`.
- **GOTCHA**: `wrapper` без `attachTo`. `onMounted` диалога всё равно вызовет `showModal()` (полифил), так что `attachTo` не нужен.
- **VALIDATE**: `npm run test:unit -- run ShopView` зелёный, `grep -r "window.confirm" frontend/src` пуст.

### Task 6: Удаление задачи через диалог и SVG-иконки (`TaskItem`)
- **ACTION**: Состояние `confirmingRemoval`, диалог, иконки.
- **IMPLEMENT**:
  ```ts
  const confirmingRemoval = ref(false)

  // The list may have started another action while the dialog was open; it will refuse
  // a second one anyway, so the item only reports the choice
  function confirmRemoval(): void {
    confirmingRemoval.value = false
    emit('remove')
  }
  ```
  Кнопка 🗑: `@click="confirmingRemoval = true"`. После `</button>` удаления:
  ```vue
  <ConfirmDialog
    v-if="confirmingRemoval"
    title="Удалить задачу?"
    :message="`«${task.title}» пропадёт из списка.`"
    confirm-label="Удалить"
    @confirm="confirmRemoval"
    @cancel="confirmingRemoval = false"
  />
  ```
  Иконки вместо текста внутри кнопок, кнопки получают `inline-flex items-center` для центрирования:
  ```vue
  <!-- Pencil and trash outlines from Lucide (ISC); the button label names the action -->
  <svg aria-hidden="true" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
       stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M21.174 6.812a1 1 0 0 0-3.986-3.987L3.842 16.174a2 2 0 0 0-.5.83l-1.321 4.352a.5.5 0 0 0 .623.622l4.353-1.32a2 2 0 0 0 .83-.497z" />
    <path d="m15 5 4 4" />
  </svg>
  ```
  ```vue
  <svg aria-hidden="true" class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
       stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M3 6h18" />
    <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
    <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
    <line x1="10" x2="10" y1="11" y2="17" />
    <line x1="14" x2="14" y1="11" y2="17" />
  </svg>
  ```
- **IMPORTS**: `import ConfirmDialog from '@/components/ConfirmDialog.vue'`.
- **GOTCHA**:
  - `<dialog>` внутри `<li>` допустим (flow content), а в top layer окно выходит из потока списка.
  - Диалог рендерится через `v-if`, поэтому в обычном состоянии его кнопок нет. Тест «disables every action while busy» (`findAll('input, button')`) остаётся верным.
  - Высота кнопок с `size-4` и `py-1` должна совпасть с прежней (символ ~16px). Проверить глазами.
  - `remove` при занятом списке: `TaskList.run` → `useFormSubmit.submit`. Проверить, что `submit` игнорирует повторный вызов, пока `submitting === true`. Если не игнорирует, в `confirmRemoval` не эмитить при `props.disabled` и оставить окно открытым.
- **VALIDATE**: Тесты Task 7.

### Task 7: Тесты `TaskItem`
- **ACTION**: Переписать «removes only after confirmation» и добавить кейсы.
- **IMPLEMENT**:
  - Клик по `button[aria-label="Удалить"]` открывает `[data-testid="confirm-dialog"]` с ««30 минут Go» пропадёт из списка.», `remove` не эмитится.
  - Кнопка «Отмена» в диалоге: окно исчезает, `remove` нет.
  - Повторный клик и кнопка «Удалить» в диалоге: `emitted('remove')` длины 1, окна нет.
  - Иконки: обе кнопки содержат `svg[aria-hidden="true"]`, текст кнопок пуст (`button.text() === ''`), `aria-label` на месте.
- **GOTCHA**: Селектор `button[aria-label="Удалить"]` не совпадёт с кнопкой «Удалить» в диалоге: у неё есть текст, но нет `aria-label`. Кнопку диалога искать внутри `[data-testid="confirm-dialog"]` по тексту.
- **VALIDATE**: `npm run test:unit -- run TaskItem` зелёный.

### Task 8: Курсор у кнопок
- **ACTION**: Добавить в `main.css` между `body {}` и `@layer components` блок:
  ```css
  /* Tailwind 4 preflight gives buttons the default cursor; a game UI wants the hand back */
  @layer base {
    button:not(:disabled),
    [role='button']:not(:disabled) {
      cursor: pointer;
    }
  }
  ```
- **GOTCHA**: `disabled:cursor-not-allowed` в `.btn-*` и `ItemCard` остаётся и не конфликтует (`:not(:disabled)`). Ссылки `RouterLink class="btn-secondary"` — это `<a href>`, у них рука и так. Проверить, что Prettier оставляет одинарные кавычки (`[role='button']`).
- **VALIDATE**: В браузере `getComputedStyle(button).cursor === 'pointer'` для активной кнопки и `'not-allowed'` для неактивной (`ItemCard` с «Надето», «Купить» без золота).

### Task 9: Удаление `ApiStatus`
- **ACTION**: Удалить `components/ApiStatus.vue`, `components/__tests__/ApiStatus.spec.ts`, `api/health.ts`, `api/__tests__/health.spec.ts`. Убрать из `api/types.ts` `CheckStatus` и `HealthData` (строки 6-11). В `App.vue` убрать импорт и `<footer>`:
  ```vue
  <script setup lang="ts">
  import { RouterView } from 'vue-router'
  </script>

  <template>
    <RouterView />
  </template>
  ```
- **GOTCHA**: Обёртка `flex min-h-screen flex-col` нужна была только для прижатого подвала. `body` уже `min-h-screen` (`main.css:58`), поэтому её можно убрать. Нужно проверить, что ни одна вьюха не рассчитывает на `flex-1` родителя (корни вьюх — `mx-auto ... px-4 py-8`). Перед удалением выполнить `grep -rn "health\|HealthData\|CheckStatus\|ApiStatus" frontend/src`: должны остаться только удаляемые файлы.
- **VALIDATE**: `npm run type-check`, `npm run check:lint`, тесты зелёные. Покрытие ≥ 80% (удаляются и код, и его тесты).

### Task 10: Скриншоты, PRD
- **ACTION**:
  1. Открыть `docs/screenshots/*.png`. Если на кадре виден подвал «API: ok», переснять на демо-аккаунте с тем же размером окна, что в фазе 7 (порядок съёмки описан в `.claude/PRPs/reports/polish-and-portfolio-report.md`). После съёмки пересоздать демо командами из README («Известные ограничения»).
  2. В PRD у фазы 8: статус `complete`, ссылки на план и отчёт. Scope выровнять с фактом: убрать пункты про смещение иконок и контраст, сделанные в фазе 7.
- **VALIDATE**: README показывает экраны без подвала.

---

## Testing Strategy

### Unit Tests

| Test | Input | Expected Output | Edge Case? |
|---|---|---|---|
| ConfirmDialog opens on mount | mount | `open`, заголовок, сообщение, aria-связи | |
| ConfirmDialog buttons | клик «Купить»/«Отмена» | `confirm` / `cancel` | |
| ConfirmDialog Escape | событие `cancel` | `cancel`, `defaultPrevented` | ✓ |
| ConfirmDialog backdrop | клик по `<dialog>` и по `<p>` | `cancel` только в первом случае | ✓ |
| ConfirmDialog unmount | unmount | вызван `close()` | ✓ |
| ShopView buy confirmed | «Купить» в окне | `buyItem(id)`, золото, «Куплено» | |
| ShopView cancelled | «Отмена» | `buyItem` не вызван, окна нет | |
| ShopView no answer | только клик по карточке | окно открыто, запроса нет | ✓ |
| ShopView refused | 422 | `role="alert"` с причиной, окна нет | ✓ |
| TaskItem remove confirmed | «Удалить» в окне | один `remove` | |
| TaskItem remove cancelled | «Отмена» | нет `remove` | |
| TaskItem icons | render | `svg[aria-hidden]`, `aria-label` | |
| TaskItem busy | `disabled: true` | все `input, button` disabled | ✓ |

### Edge Cases Checklist
- [ ] Esc закрывает окно и ничего не покупает и не удаляет
- [ ] Клик по фону = отмена, клик внутри окна — нет
- [ ] Ошибка покупки видна (окно закрыто до запроса)
- [ ] Фокус после закрытия возвращается на кнопку, открывшую окно (браузер)
- [ ] Окно на 375px не шире экрана (`max-w-sm` + UA `max-width: calc(100% - 2em)`, проверить)
- [ ] Длинное название задачи переносится внутри окна (`break-words` на `<p>`, если нужно)

---

## Validation Commands

### Static Analysis
```bash
docker compose exec frontend npm run type-check
docker compose exec frontend npm run check:lint
docker compose exec frontend npm run check:format
```
EXPECT: Ноль ошибок

### Unit Tests
```bash
docker compose exec frontend npx vitest run src/components/__tests__/ConfirmDialog.spec.ts src/components/__tests__/TaskItem.spec.ts src/views/__tests__/ShopView.spec.ts
```
EXPECT: Все тесты проходят

### Full Test Suite
```bash
docker compose exec frontend npm run test:ci
docker compose exec frontend npm run build-only
```
EXPECT: Регрессий нет, покрытие ≥ 80% по всем метрикам, сборка проходит

### Leftovers
```bash
grep -rn "window.confirm\|ApiStatus\|fetchHealth\|HealthData" frontend/src
```
EXPECT: Пусто

### Browser Validation
```bash
docker compose up -d   # http://localhost:5173, demo@example.com / demo-password
```
EXPECT: Как в Manual Validation

### Manual Validation
- [ ] Ни на одной странице (вход, регистрация, главная, снаряжение, магазин) нет строки «API»
- [ ] Магазин: «Купить за N» открывает окно сайта; Esc, клик по фону и «Отмена» закрывают без покупки; «Купить» покупает; фокус вернулся на кнопку карточки
- [ ] Главная: 🗑 открывает окно «Удалить задачу?»; «Удалить» удаляет; «Отмена» оставляет
- [ ] Курсор-рука над активными кнопками (задачи, магазин, снаряжение, выход, формы), `not-allowed` над неактивными
- [ ] Иконки ✎/🗑 одного размера, по центру кнопки, в цвет текста; при наведении рамка подсвечивается как раньше
- [ ] 375px: окно помещается, горизонтальной прокрутки нет
- [ ] Консоль без ошибок

---

## Acceptance Criteria
- [ ] Все задачи выполнены
- [ ] Все проверки проходят
- [ ] Тесты написаны и зелёные
- [ ] Нет ошибок типов и линтера
- [ ] UX совпадает с разделом UX Design

## Completion Checklist
- [ ] Код следует найденным паттернам (script setup, кортежные emits, английские комментарии «почему»)
- [ ] Обработка ошибок покупки и удаления не изменилась
- [ ] Тесты в стиле файлов (`attachTo`, `flushPromises`, поиск по aria-label)
- [ ] Тексты интерфейса на русском, без хардкода вне шаблонов
- [ ] README и скриншоты актуальны
- [ ] Ничего сверх скоупа

## Risks
| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Полифил jsdom расходится с браузером, и тесты зелёные при сломанном UI | M | M | Ручная проверка в браузере: Esc, фон, фокус. Полифил минимальный |
| Preflight ломает позицию или размер `<dialog>` | M | L | `m-auto`, паддинг на внутреннем блоке, проверка на 375px |
| Двойное удаление, если список занят в момент подтверждения | L | L | Проверить `useFormSubmit.submit` (Task 6, GOTCHA) |
| `vue-tsc` не видит `setup.ts` | L | L | Проверить `include` в `tsconfig.vitest.json` |

## Notes
- `/api/health` остаётся на бэкенде и в README (таблица API, раздел «Запуск»). Пропадает только фронтовый клиент.
- Если позже понадобятся подтверждения в других местах (например, смена внешности в фазе 11), `ConfirmDialog` уже готов. Глобальный `useConfirm()` стоит вводить, когда мест станет 4–5.
