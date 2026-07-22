import type { Photo } from '@/api/types'

// Uploaded photos sit on the `photos` disk (storage/app/uploads); nginx serves
// that directory at /uploads. A stored path is the hashed filename, so it needs
// the alias prefix. A path that already looks absolute is returned untouched.
export function photoUrl(path: string): string {
  return path.startsWith('/') ? path : `/uploads/${path}`
}

// Groups a plant's photos by the care event they document so the timeline can
// show each event's photos. Gallery-only photos (no care event) are dropped.
export function groupPhotosByCareEvent(photos: Photo[]): Record<number, Photo[]> {
  const byEvent: Record<number, Photo[]> = {}
  for (const photo of photos) {
    if (photo.care_event_id != null) {
      ;(byEvent[photo.care_event_id] ??= []).push(photo)
    }
  }
  return byEvent
}
