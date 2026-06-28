import { describe, it, expect } from 'vitest'
import { render, screen } from '@testing-library/react'
import { PhotoTile } from './photo-tile'

describe('PhotoTile', () => {
  it('renders the image from the uploads alias when a path is set', () => {
    render(<PhotoTile photo={{ path: 'hash.jpg', caption: 'New leaf' }} />)

    expect(screen.getByRole('img', { name: 'New leaf' })).toHaveAttribute(
      'src',
      '/uploads/hash.jpg'
    )
  })

  it('renders the silhouette placeholder when there is no path', () => {
    render(<PhotoTile photo={{ caption: null }} />)

    const img = screen.getByRole('img')
    expect(img).toHaveAttribute('src', '/images/plant-silhouette-thumb.png')
    expect(img.className).toContain('opacity-20')
  })
})
