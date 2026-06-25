import { useEffect, useRef, useState } from 'react'
import { mockApi } from '@/api/mock'
import type { SpeciesSuggestion } from '@/api/types'

export interface UseSuggestResult {
  results: SpeciesSuggestion[]
  loading: boolean
}

export function useSpeciesSuggest(query: string): UseSuggestResult {
  const [results, setResults] = useState<SpeciesSuggestion[]>([])
  const [loading, setLoading] = useState(false)
  const timeoutRef = useRef<NodeJS.Timeout | null>(null)

  useEffect(() => {
    if (!query || query.trim().length < 2) {
      setResults([])
      return
    }

    setLoading(true)

    timeoutRef.current = setTimeout(() => {
      mockApi
        .suggestSpecies(query)
        .then(r => {
          setResults(r)
          setLoading(false)
        })
        .catch(() => {
          setResults([])
          setLoading(false)
        })
    }, 300)

    return () => {
      if (timeoutRef.current) {
        clearTimeout(timeoutRef.current)
      }
    }
  }, [query])

  return { results, loading }
}
