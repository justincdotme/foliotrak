import type { QueryKey } from '@tanstack/react-query'

// Every plant-scoped write (care events, plant edits) feeds these five reads;
// centralizing the set means a new consumer adds one key here, not at every call site.
export function plantInvalidationKeys(plantId: number): QueryKey[] {
  return [
    ['timeline', plantId],
    ['plant', plantId],
    ['plants'],
    ['recommendations', plantId],
    ['dashboard'],
  ]
}
