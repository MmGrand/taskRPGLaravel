import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import CharacterEditor from '../CharacterEditor.vue'
import { characterFixture } from '@/api/__tests__/fixtures'
import type { Appearance } from '@/game/appearance'

function mountEditor(props: { nameErrors?: string[] } = {}) {
  return mount(CharacterEditor, {
    props: { name: 'Торин', appearance: characterFixture.appearance, ...props },
    slots: { default: '<button type="submit">Сохранить</button>' },
  })
}

type EditorWrapper = ReturnType<typeof mountEditor>

function radio(wrapper: EditorWrapper, label: string) {
  const input = wrapper
    .findAll('label')
    .find((option) => option.text() === label)
    ?.find<HTMLInputElement>('input[type="radio"]')

  if (input === undefined || !input.exists()) {
    throw new Error(`No option labelled ${label}`)
  }

  return input
}

describe('CharacterEditor', () => {
  it('shows the current name and look', () => {
    const wrapper = mountEditor()

    expect(wrapper.get<HTMLInputElement>('input[name="name"]').element.value).toBe('Торин')
    expect(radio(wrapper, 'Косы').element.checked).toBe(true)
    expect(wrapper.text()).toContain('Сохранить')
  })

  it('emits a new appearance object with the picked option', async () => {
    const wrapper = mountEditor()

    await radio(wrapper, 'Ирокез').setValue()

    const emitted = wrapper.emitted<[Appearance]>('update:appearance')?.[0]?.[0]
    expect(emitted).toEqual({ ...characterFixture.appearance, hair_style: 'mohawk' })
    expect(emitted).not.toBe(characterFixture.appearance)
    expect(characterFixture.appearance.hair_style).toBe('braids')
  })

  it('emits a random appearance', async () => {
    const wrapper = mountEditor()

    await wrapper.get('[data-testid="randomize"]').trigger('click')

    expect(wrapper.emitted('update:appearance')).toHaveLength(1)
  })

  it('emits the typed name', async () => {
    const wrapper = mountEditor()

    await wrapper.get('input[name="name"]').setValue('Гимли')

    expect(wrapper.emitted('update:name')?.[0]).toEqual(['Гимли'])
  })

  it('shows the name error', () => {
    const wrapper = mountEditor({ nameErrors: ['Слишком короткое имя.'] })

    expect(wrapper.text()).toContain('Слишком короткое имя.')
  })
})
