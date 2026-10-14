import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'

import ConfirmDialog from '../ConfirmDialog.vue'

function mountDialog() {
  return mount(ConfirmDialog, {
    props: {
      title: 'Купить предмет?',
      message: '«Зелёная бандана» за 30 золота.',
      confirmLabel: 'Купить',
    },
    attachTo: document.body,
  })
}

type DialogWrapper = ReturnType<typeof mountDialog>

function button(wrapper: DialogWrapper, label: string) {
  const found = wrapper.findAll('button').find((candidate) => candidate.text() === label)

  if (found === undefined) {
    throw new Error(`No button "${label}"`)
  }

  return found
}

describe('ConfirmDialog', () => {
  afterEach(() => {
    vi.restoreAllMocks()
    document.body.innerHTML = ''
  })

  it('opens as a labelled modal when mounted', () => {
    const wrapper = mountDialog()
    const dialog = wrapper.get<HTMLDialogElement>('dialog')

    expect(dialog.element.open).toBe(true)
    expect(wrapper.get(`#${dialog.attributes('aria-labelledby')}`).text()).toBe('Купить предмет?')
    expect(wrapper.get(`#${dialog.attributes('aria-describedby')}`).text()).toBe(
      '«Зелёная бандана» за 30 золота.',
    )
  })

  it('reports the choice of the player', async () => {
    const wrapper = mountDialog()

    await button(wrapper, 'Купить').trigger('click')
    await button(wrapper, 'Отмена').trigger('click')

    expect(wrapper.emitted('confirm')).toHaveLength(1)
    expect(wrapper.emitted('cancel')).toHaveLength(1)
  })

  it('turns Escape into a cancel and leaves closing to the parent', () => {
    const wrapper = mountDialog()
    const escape = new Event('cancel', { cancelable: true })

    wrapper.get('dialog').element.dispatchEvent(escape)

    expect(wrapper.emitted('cancel')).toHaveLength(1)
    expect(escape.defaultPrevented).toBe(true)
  })

  it('cancels on a click on the backdrop but not inside the box', async () => {
    const wrapper = mountDialog()

    await wrapper.get('p').trigger('mousedown')
    await wrapper.get('p').trigger('click')
    expect(wrapper.emitted('cancel')).toBeUndefined()

    await wrapper.get('dialog').trigger('mousedown')
    await wrapper.get('dialog').trigger('click')
    expect(wrapper.emitted('cancel')).toHaveLength(1)
  })

  // Selecting the message and releasing over the backdrop makes the browser target the click at <dialog>
  it('stays open when a press inside the box ends on the backdrop', async () => {
    const wrapper = mountDialog()

    await wrapper.get('p').trigger('mousedown')
    await wrapper.get('dialog').trigger('click')

    expect(wrapper.emitted('cancel')).toBeUndefined()
  })

  it('holds the confirmation while busy but still lets the player cancel', async () => {
    const wrapper = mountDialog()
    await wrapper.setProps({ busy: true })

    expect(button(wrapper, 'Купить').attributes('disabled')).toBeDefined()
    expect(button(wrapper, 'Отмена').attributes('disabled')).toBeUndefined()
  })

  it('closes the native dialog when removed, so the focus goes back', () => {
    const close = vi.spyOn(HTMLDialogElement.prototype, 'close')
    const wrapper = mountDialog()

    wrapper.unmount()

    expect(close).toHaveBeenCalledOnce()
  })

  it('focuses the safe choice first', () => {
    const wrapper = mountDialog()

    expect(button(wrapper, 'Отмена').attributes('autofocus')).toBeDefined()
  })
})
