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
  const [results, setResults] = useState<SpeciesSuggestion[]>([])
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    const q = query.trim()
    if (q.length < MIN_QUERY_LENGTH) {
      setResults([])
      setLoading(false)
      return
    }

    let ignore = false
    setLoading(true)
    const timer = setTimeout(() => {
      suggestSpecies(q)
        .then(found => {
          if (!ignore) {
            setResults(found)
            setLoading(false)
          }
        })
        .catch(() => {
          if (!ignore) {
            setResults([])
            setLoading(false)
          }
        })
    }, DEBOUNCE_MS)

    // Dropping a superseded response keeps an out-of-order resolve from clobbering
    // newer results.
    return () => {
      ignore = true
      clearTimeout(timer)
    }
  }, [query])

  return { results, loading }
}
