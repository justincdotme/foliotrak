import { useState, useEffect, useRef } from 'react'

interface AsyncState<T> {
  data: T | null
  loading: boolean
  error: Error | null
}

class Bus {
  v = 0
  subs = new Set<(version: number) => void>()

  bump() {
    this.v++
    this.subs.forEach(f => f(this.v))
  }
}

const bus = new Bus()

export function useStoreVersion(): number {
  const [version, setVersion] = useState(0)

  useEffect(() => {
    const handler = (v: number) => setVersion(v)
    bus.subs.add(handler)
    return () => {
      bus.subs.delete(handler)
    }
  }, [])

  return version
}

export function useAsync<T>(fn: () => Promise<T>, deps: unknown[]): AsyncState<T> {
  const [state, setState] = useState<AsyncState<T>>({
    data: null,
    loading: true,
    error: null,
  })

  const storeVersion = useStoreVersion()
  const isMountedRef = useRef(true)

  useEffect(() => {
    isMountedRef.current = true

    setState(s => ({ ...s, loading: true }))

    Promise.resolve(fn())
      .then(d => {
        if (isMountedRef.current) {
          setState({ data: d, loading: false, error: null })
        }
      })
      .catch(e => {
        if (isMountedRef.current) {
          setState({ data: null, loading: false, error: e as Error })
        }
      })

    return () => {
      isMountedRef.current = false
    }
  }, [...deps, storeVersion])

  return state
}

export function commit(): void {
  bus.bump()
}
