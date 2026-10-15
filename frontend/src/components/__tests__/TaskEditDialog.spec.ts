import { afterEach, describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import TaskEditDialog from '../TaskEditDialog.vue'
import { dailyTaskFixture, todoTaskFixture } from '@/api/__tests__/fixtures'
import type { Task } from '@/api/types'

function mountDialog(task: Task = dailyTaskFixture) {
  return mount(TaskEditDialog, { props: { task }, attachTo: document.body })
}

function option(wrapper: ReturnType<typeof mountDialog>, label: string) {
  const input = wrapper
    .findAll('label')
    .find((candidate) => candidate.text() === label)
    ?.find('input')

  if (input === undefined || !input.exists()) {
    throw new Error(`No option labelled ${label}`)
  }

  return input
}

describe('TaskEditDialog', () => {
  afterEach(() => {
    document.body.innerHTML = ''
  })

  it('opens as a modal filled with the task', () => {
    const wrapper = mountDialog({ ...dailyTaskFixture, difficulty: 'hard', weekdays: [1, 3] })

    expect(wrapper.get('dialog').attributes('open')).toBeDefined()
    expect(wrapper.get<HTMLInputElement>('input[name="edit-title"]').element.value).toBe(
      '30 минут Go',
    )
    expect(option(wrapper, 'Тяжёлая').element.checked).toBe(true)
    expect(wrapper.get<HTMLInputElement>('input[aria-label="Среда"]').element.checked).toBe(true)
  })

  it('sends only what changed', async () => {
    const wrapper = mountDialog()

    await wrapper.get('input[name="edit-title"]').setValue('  Час Go ')
    await option(wrapper, 'Лёгкая').setValue()
    await wrapper.get('input[aria-label="Воскресенье"]').setValue(false)
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('save')).toEqual([
      [{ title: 'Час Go', difficulty: 'easy', weekdays: [1, 2, 3, 4, 5, 6] }],
    ])
  })

  it('cancels when nothing changed', async () => {
    const wrapper = mountDialog()

    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('save')).toBeUndefined()
    expect(wrapper.emitted('cancel')).toHaveLength(1)
  })

  it('keeps an empty title from the server', async () => {
    const wrapper = mountDialog()

    await wrapper.get('input[name="edit-title"]').setValue('   ')
    await wrapper.get('form').trigger('submit')

    expect(wrapper.emitted('save')).toBeUndefined()
    expect(wrapper.get('[role="alert"]').text()).toBe('Введите название')
  })

  it('has no days for a todo', () => {
    const wrapper = mountDialog(todoTaskFixture)

    expect(wrapper.find('input[aria-label="Понедельник"]').exists()).toBe(false)
  })

  it('cancels on Escape and on the cancel button', async () => {
    const wrapper = mountDialog()

    await wrapper.get('dialog').trigger('cancel')
    await wrapper
      .findAll('button')
      .find((button) => button.text() === 'Отмена')
      ?.trigger('click')

    expect(wrapper.emitted('cancel')).toHaveLength(2)
  })

  it('holds the save while another action of the list is running', async () => {
    const wrapper = mount(TaskEditDialog, {
      props: { task: dailyTaskFixture, busy: true },
      attachTo: document.body,
    })

    const save = wrapper.findAll('button').find((button) => button.text() === 'Сохранить')

    expect(save?.attributes('disabled')).toBeDefined()
  })
})
