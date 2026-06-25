import { describe, it, expect, vi, beforeEach } from 'vitest'
import type { AxiosResponse } from 'axios'

vi.mock('@/lib/api', () => ({
  default: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}))

import api from '@/lib/api'
import { listPlants, suggestSpecies, uploadPhoto } from './client'

const envelope = <T>(data: T) => ({ data: { data } }) as unknown as AxiosResponse<{ data: T }>

describe('api client', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('unwraps the data envelope when listing plants', async () => {
    vi.mocked(api.get).mockResolvedValue(envelope([{ id: 1 }]))

    await expect(listPlants()).resolves.toEqual([{ id: 1 }])
    expect(api.get).toHaveBeenCalledWith('/api/plants')
  })

  it('passes the query and limit to species suggest', async () => {
    vi.mocked(api.get).mockResolvedValue(envelope([]))

    await suggestSpecies('pothos', 5)

    expect(api.get).toHaveBeenCalledWith('/api/species/suggest', {
      params: { q: 'pothos', limit: 5 },
    })
  })

  it('builds multipart form data and flags the cover on upload', async () => {
    vi.mocked(api.post).mockResolvedValue(envelope({ id: 9 }))
    const file = new File(['x'], 'hero.jpg', { type: 'image/jpeg' })

    await uploadPhoto(3, { file, caption: 'Cover photo', setAsCover: true })

    expect(api.post).toHaveBeenCalledWith('/api/plants/3/photos', expect.any(FormData))
    const form = vi.mocked(api.post).mock.calls[0]?.[1] as FormData
    expect(form.get('photo')).toBe(file)
    expect(form.get('caption')).toBe('Cover photo')
    expect(form.get('set_as_cover')).toBe('1')
  })

  it('omits the caption and cover flag when they are not provided', async () => {
    vi.mocked(api.post).mockResolvedValue(envelope({ id: 9 }))
    const file = new File(['x'], 'leaf.jpg', { type: 'image/jpeg' })

    await uploadPhoto(3, { file })

    const form = vi.mocked(api.post).mock.calls[0]?.[1] as FormData
    expect(form.get('caption')).toBeNull()
    expect(form.get('set_as_cover')).toBeNull()
  })
})
