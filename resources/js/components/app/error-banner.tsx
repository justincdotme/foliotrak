import { AlertTriangle, X } from 'lucide-react'
import { useNotification } from './notification-context'

export function ErrorBanner() {
  const { error, clearError } = useNotification()
  if (!error) return null

  return (
    <div className="px-4 pt-2">
      <div
        className="animate-in slide-in-from-top-2 duration-200 flex items-center gap-2 rounded-[10px] border px-3 py-2.5 text-[13px]"
        style={{
          background: 'color-mix(in srgb, var(--overdue) 12%, transparent)',
          borderColor: 'color-mix(in srgb, var(--overdue) 35%, transparent)',
          color: 'var(--overdue)',
        }}
        role="alert"
      >
        <AlertTriangle size={15} className="shrink-0" />
        <span className="flex-1">{error}</span>
        <button
          onClick={clearError}
          className="shrink-0 p-0.5 hover:opacity-70"
          aria-label="Dismiss"
        >
          <X size={14} />
        </button>
      </div>
    </div>
  )
}
