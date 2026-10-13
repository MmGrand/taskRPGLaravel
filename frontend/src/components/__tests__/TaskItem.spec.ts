import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import TaskItem from '../TaskItem.vue'
import { dailyTaskFixture } from '@/api/__tests__/fixtures'
import type { Task } from '@/api/types'

function mountItem(task: Task = dailyTaskFixture, disabled = false) {
  return mount(TaskItem, { props: { task, disabled }, attachTo: document.body })
}

describe('TaskItem', () => {
  afterEach(() => {
    vi.restoreAllMocks()
    document.body.innerHTML = ''
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
    const wrapper = mountItem(dailyTaskFixture, true)

    expect(
      wrapper
        .findAll('input, button')
        .every((element) => element.attributes('disabled') !== undefined),
    ).toBe(true)
  })

  it('renames once on Enter even though blur follows', async () => {
    const wrapper = mountItem()
    await wrapper.get('button[aria-label="Переименовать"]').trigger('click')
    const input = wrapper.get<HTMLInputElement>('input[aria-label="Новое название"]')

    await input.setValue('  Час Go ')
    await input.trigger('keydown', { key: 'Enter' })
    await input.trigger('blur')

    expect(wrapper.emitted('rename')).toEqual([['Час Go']])
    expect(wrapper.find('input[aria-label="Новое название"]').exists()).toBe(false)
  })

  it('keeps the draft while another action of the list is running', async () => {
    const wrapper = mountItem()
    await wrapper.get('button[aria-label="Переименовать"]').trigger('click')
    const input = wrapper.get('input[aria-label="Новое название"]')
    await input.setValue('Час Go')

    await wrapper.setProps({ disabled: true })
    await input.trigger('keydown', { key: 'Enter' })
    await input.trigger('blur')

    expect(wrapper.emitted('rename')).toBeUndefined()
    expect(wrapper.get<HTMLInputElement>('input[aria-label="Новое название"]').element.value).toBe(
      'Час Go',
    )

    await wrapper.setProps({ disabled: false })
    await wrapper.get('input[aria-label="Новое название"]').trigger('keydown', { key: 'Enter' })

    expect(wrapper.emitted('rename')).toEqual([['Час Go']])
  })

  it('cancels renaming on Escape', async () => {
    const wrapper = mountItem()
    await wrapper.get('button[aria-label="Переименовать"]').trigger('click')
    const input = wrapper.get('input[aria-label="Новое название"]')

    await input.setValue('Час Go')
    await input.trigger('keydown', { key: 'Escape' })

    expect(wrapper.emitted('rename')).toBeUndefined()
    expect(wrapper.text()).toContain('30 минут Go')
  })

  it('does not rename to an empty or unchanged title', async () => {
    const wrapper = mountItem()

    for (const draft of ['   ', '30 минут Go']) {
      await wrapper.get('button[aria-label="Переименовать"]').trigger('click')
      const input = wrapper.get('input[aria-label="Новое название"]')
      await input.setValue(draft)
      await input.trigger('keydown', { key: 'Enter' })
    }

    expect(wrapper.emitted('rename')).toBeUndefined()
  })

  it('removes only after confirmation', async () => {
    const confirm = vi.spyOn(window, 'confirm').mockReturnValueOnce(false).mockReturnValueOnce(true)
    const wrapper = mountItem()
    const removeButton = wrapper.get('button[aria-label="Удалить"]')

    await removeButton.trigger('click')
    expect(wrapper.emitted('remove')).toBeUndefined()

    await removeButton.trigger('click')
    expect(wrapper.emitted('remove')).toHaveLength(1)
    expect(confirm).toHaveBeenCalledWith('Удалить «30 минут Go»?')
  })
})
