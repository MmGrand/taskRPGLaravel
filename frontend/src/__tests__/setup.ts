// jsdom has <dialog> without showModal() and close(); this mimics only what the components use
const dialogPrototype = HTMLDialogElement.prototype

if (!('showModal' in dialogPrototype)) {
  Object.assign(dialogPrototype, {
    showModal(this: HTMLDialogElement): void {
      this.setAttribute('open', '')
    },
    // Like the browser: closing a closed dialog does nothing and fires no "close"
    close(this: HTMLDialogElement): void {
      if (!this.open) {
        return
      }

      this.removeAttribute('open')
      this.dispatchEvent(new Event('close'))
    },
  })
}
