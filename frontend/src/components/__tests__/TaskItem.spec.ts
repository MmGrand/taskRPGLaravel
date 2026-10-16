import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'

import TaskItem from '../TaskItem.vue'
import { dailyTaskFixture, habitTaskFixture } from '@/api/__tests__/fixtures'
import type { Task, UpdateTaskPayload } from '@/api/types'
import { answerDialog, confirmDialog as dialog, dialogButton } from '@/__tests__/confirmDialog'

// The dialog buttons have text and no aria-label, so they never match the row buttons
function mountItem(
  task: Task = dailyTaskFixture,
  disabled = false,
  draggable = false,
  save = vi.fn<(payload: UpdateTaskPayload) => Promise<boolean>>().mockResolvedValue(true),
) {
  return mount(TaskItem, {
    props: { task, disabled, draggable, save },
    attachTo: document.body,
  })
}

function editDialog(wrapper: ReturnType<typeof mountItem>) {
  return wrapper.find('[data-testid="task-edit-dialog"]')
}

describe('TaskItem', () => {
  afterEach(() => {
    vi.restoreAllMocks()
    document.body.innerHTML = ''
  })

  it('shows plus and minus instead of a checkbox for a habit and passes them on', async () => {
    const wrapper = mountItem({ ...habitTaskFixture, habit: { up_today: 2, down_today: 1 } })

    expect(wrapper.find('input[type="checkbox"]').exists()).toBe(false)
    expect(wrapper.get('[data-testid="habit-counts"]').text()).toBe('+2 / −1')

    await wrapper.get('[aria-label="Плюс: Пить воду"]').trigger('click')
    await wrapper.get('[aria-label="Минус: Пить воду"]').trigger('click')
    await wrapper.get('[aria-label="Отменить последнюю отметку: Пить воду"]').trigger('click')

    expect(wrapper.emitted('mark')).toEqual([['up'], ['down']])
    expect(wrapper.emitted('undo-mark')).toHaveLength(1)
  })

  it('offers no undo for a habit before its first mark of the day', () => {
    const wrapper = mountItem(habitTaskFixture)

    expect(wrapper.get('[data-testid="habit-counts"]').text()).toBe('+0 / −0')
    expect(wrapper.find('[data-testid="habit-undo"]').exists()).toBe(false)
  })

  it('reflects the completion state', () => {
    const wrapper = mountItem({ ...dailyTaskFixture, is_completed: true })

    const checkbox = wrapper.get<HTMLInputElement>('input[type="checkbox"]')

    expect(checkbox.element.checked).toBe(true)
    expect(checkbox.attributes('aria-label')).toBe('Выполнено: 30 минут Go')
    expect(wrapper.get('[data-testid="task-title"]').classes()).toContain('line-through')
  })

  it('asks to toggle without changing the checkbox itself', async () => {
    const wrapper = mountItem()
    const checkbox = wrapper.get<HTMLInputElement>('input[type="checkbox"]')

    await checkbox.trigger('click')

    expect(wrapper.emitted('toggle')).toEqual([[true]])
    expect(checkbox.element.checked).toBe(false)
  })

  it('disables every action while busy', () => {
    const wrapper = mountItem(dailyTaskFixture, true, true)

    expect(
      wrapper
        .findAll('input, button')
        .every((element) => element.attributes('disabled') !== undefined),
    ).toBe(true)
  })

  it('shows the difficulty and the days, but not the usual ones', () => {
    const marked = mountItem({ ...dailyTaskFixture, difficulty: 'hard', weekdays: [1, 3, 5] })
    const plain = mountItem()

    expect(marked.get('[data-testid="task-details"]').text()).toBe('тяжёлая · пн, ср, пт')
    expect(plain.find('[data-testid="task-details"]').exists()).toBe(false)
  })

  it('hides the checkbox of a daily off its schedule', () => {
    const wrapper = mountItem({ ...dailyTaskFixture, scheduled_today: false })

    expect(wrapper.find('input[type="checkbox"]').exists()).toBe(false)
    expect(wrapper.get('li').classes()).toContain('opacity-60')
  })

  it('keeps the checkbox of a daily done today before its day left the schedule', () => {
    const wrapper = mountItem({ ...dailyTaskFixture, scheduled_today: false, is_completed: true })

    expect(wrapper.find('input[type="checkbox"]').exists()).toBe(true)
  })

  it('edits the task in a dialog and closes it once saved', async () => {
    const save = vi.fn<(payload: UpdateTaskPayload) => Promise<boolean>>().mockResolvedValue(true)
    const wrapper = mountItem(dailyTaskFixture, false, false, save)

    await wrapper.get('button[aria-label="Изменить"]').trigger('click')
    await editDialog(wrapper).get('input[name="edit-title"]').setValue('Час Go')
    await editDialog(wrapper).get('form').trigger('submit')
    await flushPromises()

    expect(save).toHaveBeenCalledWith({ title: 'Час Go' })
    expect(editDialog(wrapper).exists()).toBe(false)
  })

  it('keeps the dialog and the entered changes when the server refuses them', async () => {
    const save = vi.fn<(payload: UpdateTaskPayload) => Promise<boolean>>().mockResolvedValue(false)
    const wrapper = mountItem(dailyTaskFixture, false, false, save)

    await wrapper.get('button[aria-label="Изменить"]').trigger('click')
    await editDialog(wrapper).get('input[name="edit-title"]').setValue('Слишком длинное')
    await editDialog(wrapper).get('form').trigger('submit')
    await flushPromises()
    await wrapper.setProps({ errors: { title: ['Не больше 120 символов.'] } })

    expect(
      editDialog(wrapper).get<HTMLInputElement>('input[name="edit-title"]').element.value,
    ).toBe('Слишком длинное')
    expect(editDialog(wrapper).get('[role="alert"]').text()).toBe('Не больше 120 символов.')
  })

  it('does not show errors of other actions in a dialog that has not saved yet', async () => {
    const wrapper = mountItem()
    await wrapper.setProps({ errors: { title: ['Чужая ошибка'] }, generalError: 'Сеть' })

    await wrapper.get('button[aria-label="Изменить"]').trigger('click')

    expect(editDialog(wrapper).find('[role="alert"]').exists()).toBe(false)
  })

  it('closes the dialog without changes on cancel', async () => {
    const wrapper = mountItem()

    await wrapper.get('button[aria-label="Изменить"]').trigger('click')
    await editDialog(wrapper).trigger('cancel')

    expect(editDialog(wrapper).exists()).toBe(false)
  })

  it('draws no handle unless the row can be dragged', () => {
    expect(mountItem().find('.drag-handle').exists()).toBe(false)
    expect(mountItem(dailyTaskFixture, false, true).find('.drag-handle').exists()).toBe(true)
  })

  it('moves with the arrow keys on the handle', async () => {
    const wrapper = mountItem(dailyTaskFixture, false, true)
    const handle = wrapper.get('button[aria-label="Переместить «30 минут Go»"]')

    await handle.trigger('keydown', { key: 'ArrowDown' })
    await handle.trigger('keydown', { key: 'ArrowUp' })

    expect(wrapper.emitted('move')).toEqual([[1], [-1]])
  })

  it('asks before removing', async () => {
    const wrapper = mountItem()

    await wrapper.get('button[aria-label="Удалить"]').trigger('click')

    expect(dialog(wrapper).text()).toContain('«30 минут Go» пропадёт из списка.')
    expect(wrapper.emitted('remove')).toBeUndefined()
  })

  it('warns that the reward of today goes back when a rewarded task is removed', async () => {
    const wrapper = mountItem({ ...dailyTaskFixture, is_completed: true, rewarded_today: true })

    await wrapper.get('button[aria-label="Удалить"]').trigger('click')

    expect(dialog(wrapper).text()).toContain(
      '«30 минут Go» пропадёт из списка. Награда за сегодня вернётся.',
    )
  })

  // Done past the daily limit, or a todo done before midnight: nothing comes back
  it('promises no refund for a completed task that gave nothing today', async () => {
    const wrapper = mountItem({ ...dailyTaskFixture, is_completed: true, rewarded_today: false })

    await wrapper.get('button[aria-label="Удалить"]').trigger('click')

    expect(dialog(wrapper).text()).not.toContain('Награда за сегодня вернётся.')
  })

  it('keeps the task when the player changes their mind', async () => {
    const wrapper = mountItem()

    await wrapper.get('button[aria-label="Удалить"]').trigger('click')
    await answerDialog(wrapper, 'Отмена')

    expect(dialog(wrapper).exists()).toBe(false)
    expect(wrapper.emitted('remove')).toBeUndefined()
  })

  it('removes once confirmed', async () => {
    const wrapper = mountItem()

    await wrapper.get('button[aria-label="Удалить"]').trigger('click')
    await answerDialog(wrapper, 'Удалить')

    expect(dialog(wrapper).exists()).toBe(false)
    expect(wrapper.emitted('remove')).toHaveLength(1)
  })

  it('holds the confirmation while another action of the list is running', async () => {
    const wrapper = mountItem()
    await wrapper.get('button[aria-label="Удалить"]').trigger('click')

    await wrapper.setProps({ disabled: true })
    expect(dialogButton(wrapper, 'Удалить').attributes('disabled')).toBeDefined()

    await wrapper.setProps({ disabled: false })
    await answerDialog(wrapper, 'Удалить')
    expect(wrapper.emitted('remove')).toHaveLength(1)
  })

  it('draws the action icons and names the actions for screen readers', () => {
    const wrapper = mountItem(dailyTaskFixture, false, true)

    for (const label of ['Изменить', 'Удалить', 'Переместить «30 минут Go»']) {
      const button = wrapper.get(`button[aria-label="${label}"]`)

      expect(button.find('svg[aria-hidden="true"]').exists()).toBe(true)
      expect(button.text()).toBe('')
    }
  })
})
