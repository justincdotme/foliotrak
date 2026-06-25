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

  it('falls back to the placeholder icon, not a broken image, when there is no path', () => {
    const { container } = render(<PhotoTile photo={{ caption: null }} />)

    expect(container.querySelector('img')).toBeNull()
    expect(container.querySelector('svg')).not.toBeNull()
  })
})
