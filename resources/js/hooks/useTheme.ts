import { useEffect, useState } from 'react'

export type ThemeChoice = 'light' | 'dark' | 'system'

const KEY = 'foliotrak-theme'

function systemPrefersDark(): boolean {
  return window.matchMedia('(prefers-color-scheme: dark)').matches
}

function resolveDark(choice: ThemeChoice): boolean {
  return choice === 'system' ? systemPrefersDark() : choice === 'dark'
}

const store = {
  subscribers: new Set<(choice: ThemeChoice) => void>(),
  get(): ThemeChoice {
    const value = localStorage.getItem(KEY)
    return value === 'light' || value === 'dark' || value === 'system' ? value : 'system'
  },
  apply(choice: ThemeChoice): void {
    document.documentElement.classList.add('no-transitions')
    document.documentElement.classList.toggle('dark', resolveDark(choice))
    requestAnimationFrame(() => {
      document.documentElement.classList.remove('no-transitions')
    })
  },
  set(choice: ThemeChoice): void {
    localStorage.setItem(KEY, choice)
    this.apply(choice)
    this.subscribers.forEach(notify => notify(choice))
  },
}

export function useTheme() {
  const [theme, setTheme] = useState<ThemeChoice>(() => store.get())

  useEffect(() => {
    const notify = (choice: ThemeChoice) => setTheme(choice)
    store.subscribers.add(notify)
    store.apply(store.get())
    return () => {
      store.subscribers.delete(notify)
    }
  }, [])

  const isDark = document.documentElement.classList.contains('dark')

  return {
    theme,
    isDark,
    setTheme: (choice: ThemeChoice) => store.set(choice),
    toggle: () => store.set(isDark ? 'light' : 'dark'),
  }
}
