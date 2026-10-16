import { beforeEach, describe, expect, it, vi } from 'vitest'

import { createCharacter, updateCharacter } from '../character'
import { http } from '../http'
import { characterFixture } from './fixtures'

vi.mock('../http', () => ({
  http: { post: vi.fn<typeof http.post>(), put: vi.fn<typeof http.put>() },
}))

const postMock = vi.mocked(http.post)
const putMock = vi.mocked(http.put)

describe('createCharacter', () => {
  beforeEach(() => {
    postMock.mockReset()
  })

  it('posts the name and appearance and returns the created character', async () => {
    postMock.mockResolvedValue({ data: { data: characterFixture } })
    const payload = { name: 'Торин', ...characterFixture.appearance }

    await expect(createCharacter(payload)).resolves.toEqual(characterFixture)
    expect(postMock).toHaveBeenCalledWith('/character', payload)
  })

  it('rejects a response it cannot render', async () => {
    postMock.mockResolvedValue({ data: { data: { ...characterFixture, appearance: null } } })

    await expect(
      createCharacter({ name: 'Торин', ...characterFixture.appearance }),
    ).rejects.toThrow('Unexpected character response')
  })
})

describe('updateCharacter', () => {
  beforeEach(() => {
    putMock.mockReset()
  })

  it('puts the new name and appearance and returns the changed character', async () => {
    const changed = { ...characterFixture, name: 'Гимли' }
    putMock.mockResolvedValue({ data: { data: changed } })
    const payload = { name: 'Гимли', ...characterFixture.appearance }

    await expect(updateCharacter(payload)).resolves.toEqual(changed)
    expect(putMock).toHaveBeenCalledWith('/character', payload)
  })

  it('rejects a response it cannot render', async () => {
    putMock.mockResolvedValue({ data: { data: { ...characterFixture, progress: null } } })

    await expect(
      updateCharacter({ name: 'Гимли', ...characterFixture.appearance }),
    ).rejects.toThrow('Unexpected character response')
  })
})
