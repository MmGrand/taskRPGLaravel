import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'

import FormField from '../FormField.vue'

describe('FormField', () => {
  it('binds the input value through v-model', async () => {
    const wrapper = mount(FormField, {
      props: {
        label: 'Email',
        name: 'email',
        modelValue: '',
        'onUpdate:modelValue': (value: string) => wrapper.setProps({ modelValue: value }),
      },
    })

    await wrapper.get('input').setValue('hero@example.com')

    expect(wrapper.props('modelValue')).toBe('hero@example.com')
    expect(wrapper.get('label').attributes('for')).toBe(wrapper.get('input').attributes('id'))
  })

  it('shows the first error and marks the input invalid', () => {
    const wrapper = mount(FormField, {
      props: {
        label: 'Email',
        name: 'email',
        modelValue: '',
        errors: ['The email has already been taken.'],
      },
    })

    const input = wrapper.get('input')
    const error = wrapper.get('[role="alert"]')

    expect(error.text()).toBe('The email has already been taken.')
    expect(input.attributes('aria-invalid')).toBe('true')
    expect(input.attributes('aria-describedby')).toBe(error.attributes('id'))
  })
})
