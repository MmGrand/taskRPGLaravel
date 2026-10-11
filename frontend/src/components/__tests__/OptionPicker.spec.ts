import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'

import OptionPicker from '../OptionPicker.vue'

const options = [
  { value: 'short', label: 'Короткие' },
  { value: 'mohawk', label: 'Ирокез' },
] as const

function mountPicker(modelValue: 'short' | 'mohawk' = 'short') {
  const wrapper = mount(OptionPicker, {
    props: {
      legend: 'Причёска',
      options,
      modelValue,
      'onUpdate:modelValue': (value: string) => wrapper.setProps({ modelValue: value }),
    },
  })

  return wrapper
}

describe('OptionPicker', () => {
  it('renders a native radio group so arrow keys and Tab work out of the box', () => {
    const wrapper = mountPicker()

    const inputs = wrapper.findAll<HTMLInputElement>('input[type="radio"]')
    const names = new Set(inputs.map((input) => input.attributes('name')))

    expect(inputs).toHaveLength(2)
    expect(names.size).toBe(1)
    expect(inputs[0]?.element.checked).toBe(true)
    expect(inputs[1]?.element.checked).toBe(false)
    expect(wrapper.findAll('label').map((label) => label.text())).toEqual(['Короткие', 'Ирокез'])
  })

  it('updates the model when another option is chosen', async () => {
    const wrapper = mountPicker()

    await wrapper.findAll('input[type="radio"]')[1]?.setValue()

    expect(wrapper.props('modelValue')).toBe('mohawk')
  })

  it('gives every picker on a page its own radio group name', () => {
    const Page = defineComponent(() => () => [
      h(OptionPicker, { legend: 'Причёска', options, modelValue: 'short' }),
      h(OptionPicker, { legend: 'Борода', options, modelValue: 'short' }),
    ])

    const names = mount(Page)
      .findAll('fieldset')
      .map((fieldset) => fieldset.get('input').attributes('name'))

    expect(new Set(names).size).toBe(2)
  })
})
