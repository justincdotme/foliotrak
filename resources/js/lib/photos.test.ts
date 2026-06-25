import { describe, it, expect } from 'vitest'
import { photoUrl } from './photos'

describe('photoUrl', () => {
  it('prefixes a stored hash with the uploads alias', () => {
    expect(photoUrl('a1b2c3.jpg')).toBe('/uploads/a1b2c3.jpg')
  })

  it('leaves an already-absolute path untouched', () => {
    expect(photoUrl('/storage/photos/x.jpg')).toBe('/storage/photos/x.jpg')
  })
})
