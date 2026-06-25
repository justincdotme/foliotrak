// Uploaded photos sit on the `photos` disk (storage/app/uploads); nginx serves
// that directory at /uploads. A stored path is the hashed filename, so it needs
// the alias prefix. A path that already looks absolute is returned untouched.
export function photoUrl(path: string): string {
  return path.startsWith('/') ? path : `/uploads/${path}`
}
