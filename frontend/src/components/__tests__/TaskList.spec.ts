import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import { VueDraggable } from 'vue-draggable-plus'

import TaskList from '../TaskList.vue'
import { axiosError, dailyTaskFixture } from '@/api/__tests__/fixtures'
import {
  completeTask,
  createTask,
  deleteTask,
  fetchTasks,
  saveTaskOrder,
  uncompleteTask,
  updateTask,
} from '@/api/tasks'
import type { CompletionResult, Task } from '@/api/types'
import { useTasksStore } from '@/stores/tasks'

vi.mock('@/api/tasks', () => ({
  fetchTasks: vi.fn<typeof fetchTasks>(),
  createTask: vi.fn<typeof createTask>(),
  updateTask: vi.fn<typeof updateTask>(),
  saveTaskOrder: vi.fn<typeof saveTaskOrder>(),
  deleteTask: vi.fn<typeof deleteTask>(),
  completeTask: vi.fn<typeof completeTask>(),
  uncompleteTask: vi.fn<typeof uncompleteTask>(),
}))

const doneDaily: Task = { ...dailyTaskFixture, id: 3, title: 'Зарядка', is_completed: true }
const offToday: Task = { ...dailyTaskFixture, id: 4, title: 'Бассейн', scheduled_today: false }

function mountList(tasks: readonly Task[], showProgress = false, laterTasks: readonly Task[] = []) {
  const pinia = createPinia()
  setActivePinia(pinia)
  useTasksStore().tasks = [...tasks, ...laterTasks]

  return mount(TaskList, {
    props: { title: 'Ежедневные', tasks, showProgress, laterTasks },
    global: { plugins: [pinia] },
    attachTo: document.body,
  })
}

async function drop(wrapper: ReturnType<typeof mountList>, order: readonly Task[]): Promise<void> {
  const draggable = wrapper.findComponent(VueDraggable)
  draggable.vm.$emit('update:modelValue', [...order])
  await flushPromises()
  draggable.vm.$emit('end')
  await flushPromises()
}

describe('TaskList', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('shows an empty state', () => {
    const wrapper = mountList([])

    expect(wrapper.text()).toContain('Пока пусто.')
  })

  it('shows progress for dailies', () => {
    const wrapper = mountList([dailyTaskFixture, doneDaily], true)

    expect(wrapper.get('[data-testid="task-progress"]').text()).toBe('1 / 2')
  })

  it('lists dailies off their schedule apart and leaves them out of the progress', () => {
    const wrapper = mountList([dailyTaskFixture, doneDaily], true, [offToday])

    expect(wrapper.get('[data-testid="task-progress"]').text()).toBe('1 / 2')
    expect(wrapper.get('[data-testid="later-tasks"] summary').text()).toBe('Не сегодня (1)')
    expect(wrapper.get('[data-testid="later-tasks"]').text()).toContain('Бассейн')
    expect(wrapper.find('[data-testid="later-tasks"] .drag-handle').exists()).toBe(false)
  })

  it('locks the list while an action is running', async () => {
    let finish: (result: CompletionResult) => void = () => {}
    vi.mocked(completeTask).mockReturnValue(new Promise((resolve) => (finish = resolve)))
    const wrapper = mountList([dailyTaskFixture, doneDaily])

    await wrapper.get('input[type="checkbox"]').trigger('click')

    expect(
      wrapper
        .findAll('input[type="checkbox"]')
        .every((box) => box.attributes('disabled') !== undefined),
    ).toBe(true)
    finish({ task: { ...dailyTaskFixture, is_completed: true }, character: null, reward: null })
    await flushPromises()
    expect(wrapper.get('input[type="checkbox"]').attributes('disabled')).toBeUndefined()
  })

  it('reloads the list when the task is already gone', async () => {
    vi.mocked(completeTask).mockRejectedValue(axiosError(404))
    vi.mocked(fetchTasks).mockResolvedValue([])
    const wrapper = mountList([dailyTaskFixture])

    await wrapper.get('input[type="checkbox"]').trigger('click')
    await flushPromises()

    expect(fetchTasks).toHaveBeenCalledOnce()
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('shows a validation error of an edit', async () => {
    vi.mocked(updateTask).mockRejectedValue(
      axiosError(422, {
        errors: { title: ['Количество символов в поле название не может превышать 120.'] },
      }),
    )
    const wrapper = mountList([dailyTaskFixture])

    await wrapper.get('button[aria-label="Изменить"]').trigger('click')
    await wrapper.get('input[name="edit-title"]').setValue('Очень длинное название')
    await wrapper.get('[data-testid="task-edit-dialog"] form').trigger('submit')
    await flushPromises()

    // The dialog stays open with the message, so the entered changes are not lost
    expect(wrapper.get('[data-testid="task-edit-dialog"] [role="alert"]').text()).toContain(
      'не может превышать 120',
    )
  })

  it('says a list with dailies only for other days is free today, without a counter', () => {
    const wrapper = mountList([], true, [offToday])

    expect(wrapper.text()).toContain('На сегодня по расписанию ничего нет.')
    expect(wrapper.find('[data-testid="task-progress"]').exists()).toBe(false)
  })

  it('does not redraw the rows under a drag and takes changes in on drop', async () => {
    const wrapper = mountList([dailyTaskFixture, doneDaily])
    const draggable = wrapper.findComponent(VueDraggable)

    draggable.vm.$emit('start')
    await wrapper.setProps({ tasks: [dailyTaskFixture, doneDaily, offToday] })
    expect(wrapper.findAll('[data-testid="task-title"]')).toHaveLength(2)

    draggable.vm.$emit('end')
    await flushPromises()

    expect(wrapper.findAll('[data-testid="task-title"]')).toHaveLength(3)
    expect(saveTaskOrder).not.toHaveBeenCalled()
  })

  it('explains why a daily cannot be completed today', async () => {
    vi.mocked(completeTask).mockRejectedValue(
      axiosError(422, { errors: { task: ['Эта задача сегодня не по расписанию.'] } }),
    )
    const wrapper = mountList([dailyTaskFixture])

    await wrapper.get('input[type="checkbox"]').trigger('click')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toBe('Эта задача сегодня не по расписанию.')
  })

  it('saves the order after a drag', async () => {
    vi.mocked(saveTaskOrder).mockResolvedValue()
    const wrapper = mountList([dailyTaskFixture, doneDaily])

    await drop(wrapper, [doneDaily, dailyTaskFixture])

    expect(saveTaskOrder).toHaveBeenCalledWith([3, 1])
    expect(useTasksStore().tasks.map((task) => task.id)).toEqual([3, 1])
  })

  it('saves nothing when a task is dropped where it was', async () => {
    const wrapper = mountList([dailyTaskFixture, doneDaily])

    await drop(wrapper, [dailyTaskFixture, doneDaily])

    expect(saveTaskOrder).not.toHaveBeenCalled()
  })

  it('moves a task with the arrow keys and keeps the focus on its handle', async () => {
    vi.mocked(saveTaskOrder).mockResolvedValue()
    const wrapper = mountList([dailyTaskFixture, doneDaily])

    await wrapper.get('button[aria-label="Переместить «30 минут Go»"]').trigger('keydown', {
      key: 'ArrowDown',
    })
    await flushPromises()

    expect(saveTaskOrder).toHaveBeenCalledWith([3, 1])
    expect(document.activeElement?.getAttribute('aria-label')).toBe('Переместить «30 минут Go»')
  })

  it('does not move the first task up or the last one down', async () => {
    const wrapper = mountList([dailyTaskFixture, doneDaily])

    await wrapper.get('button[aria-label="Переместить «30 минут Go»"]').trigger('keydown', {
      key: 'ArrowUp',
    })
    await wrapper.get('button[aria-label="Переместить «Зарядка»"]').trigger('keydown', {
      key: 'ArrowDown',
    })
    await flushPromises()

    expect(saveTaskOrder).not.toHaveBeenCalled()
  })
})
