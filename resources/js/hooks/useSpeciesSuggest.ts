import { useEffect, useState } from 'react'
import type { SpeciesSuggestion } from '@/api/types'
import { suggestSpecies } from '@/api/client'

export interface UseSuggestResult {
  results: SpeciesSuggestion[]
  loading: boolean
}

// Keeps GBIF traffic low: a typing burst collapses to one request, and nothing
// fires below the minimum query length. The cache and outbound throttle that
// further protect GBIF live server-side.
const MIN_QUERY_LENGTH = 3
const DEBOUNCE_MS = 300

export function useSpeciesSuggest(query: string): UseSuggestResult {
  const q = query.trim()
  const searchable = q.length >= MIN_QUERY_LENGTH
  const [results, setResults] = useState<SpeciesSuggestion[]>([])
  const [settledQuery, setSettledQuery] = useState<string | null>(null)

  if (!searchable && settledQuery !== null) {
    setResults([])
    setSettledQuery(null)
  }

  useEffect(() => {
    if (q.length < MIN_QUERY_LENGTH) return

    let ignore = false
    const timer = setTimeout(() => {
      suggestSpecies(q)
        .then(found => {
          if (!ignore) {
            setResults(found)
            setSettledQuery(q)
          }
        })
        .catch(() => {
          if (!ignore) {
            setResults([])
            setSettledQuery(q)
          }
        })
    }, DEBOUNCE_MS)

    // Dropping a superseded response keeps an out-of-order resolve from clobbering
    // newer results.
    return () => {
      ignore = true
      clearTimeout(timer)
    }
  }, [q])

  return { results, loading: searchable && settledQuery !== q }
}
