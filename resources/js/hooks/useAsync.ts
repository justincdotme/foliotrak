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
  const fnRef = useRef(fn)

  // The caller's deps array is the intended re-run signal (as with useMemo), so
  // hold the latest factory in a ref rather than depending on its identity.
  useEffect(() => {
    fnRef.current = fn
  })

  // A variable-length deps array can only be a static effect dependency once
  // collapsed to a single key; spreading it leaves nothing to verify.
  const depsKey = JSON.stringify(deps)

  useEffect(() => {
    isMountedRef.current = true

    setState(s => ({ ...s, loading: true }))

    Promise.resolve(fnRef.current())
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
  }, [depsKey, storeVersion])

  return state
}

export function commit(): void {
  bus.bump()
}
