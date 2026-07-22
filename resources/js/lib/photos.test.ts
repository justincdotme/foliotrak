import { describe, it, expect } from 'vitest'
import type { Photo } from '@/api/types'
import { groupPhotosByCareEvent, photoUrl } from './photos'

describe('photoUrl', () => {
  it('prefixes a stored hash with the uploads alias', () => {
    expect(photoUrl('a1b2c3.jpg')).toBe('/uploads/a1b2c3.jpg')
  })

  it('leaves an already-absolute path untouched', () => {
    expect(photoUrl('/storage/photos/x.jpg')).toBe('/storage/photos/x.jpg')
  })
})

const photo = (id: number, careEventId: number | null): Photo => ({
  id,
  plant_id: 1,
  care_event_id: careEventId,
  path: `p${id}.jpg`,
  thumb_path: null,
  original_filename: null,
  taken_on: '2026-06-20',
  caption: null,
  created_at: '2026-06-20T00:00:00.000Z',
  updated_at: '2026-06-20T00:00:00.000Z',
})

describe('groupPhotosByCareEvent', () => {
  it('groups photos under their care event and drops gallery-only photos', () => {
    const grouped = groupPhotosByCareEvent([photo(1, 9), photo(2, null), photo(3, 9), photo(4, 12)])

    expect(Object.keys(grouped)).toEqual(['9', '12'])
    expect(grouped[9]?.map(p => p.id)).toEqual([1, 3])
    expect(grouped[12]?.map(p => p.id)).toEqual([4])
  })
})
