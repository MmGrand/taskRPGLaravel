import { beforeEach, describe, expect, it, vi } from 'vitest'

import { createCharacter } from '../character'
import { http } from '../http'
import { characterFixture } from './fixtures'

vi.mock('../http', () => ({ http: { post: vi.fn<typeof http.post>() } }))

const postMock = vi.mocked(http.post)

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
