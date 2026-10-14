import { flushPromises, type VueWrapper } from '@vue/test-utils'

type Wrapper = Pick<VueWrapper, 'find'>

export function confirmDialog(wrapper: Wrapper) {
  return wrapper.find('[data-testid="confirm-dialog"]')
}

// Throws instead of returning nothing, so a renamed button fails the test rather than clicking nothing
export function dialogButton(wrapper: Wrapper, label: string) {
  const button = confirmDialog(wrapper)
    .findAll('button')
    .find((candidate) => candidate.text() === label)

  if (button === undefined) {
    throw new Error(`No dialog button "${label}"`)
  }

  return button
}

export async function answerDialog(wrapper: Wrapper, label: string): Promise<void> {
  await dialogButton(wrapper, label).trigger('click')
  await flushPromises()
}
