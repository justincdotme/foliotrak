import { Info } from 'lucide-react'
import { Card } from '@/components/ui/card'
import type { ReactNode } from 'react'

interface ChartShellProps {
  title: string
  n?: number | null
  children: ReactNode
  note?: string
  height?: number
}

export function ChartShell({ title, n, children, note, height = 180 }: ChartShellProps) {
  return (
    <Card className="p-4">
      <div className="flex items-baseline gap-2 mb-3">
        <h3 className="text-[13px] font-semibold text-text">{title}</h3>
        {n != null && <span className="text-[11px] tnum text-text-subtle ml-auto">n = {n}</span>}
      </div>
      <div style={{ height }}>{children}</div>
      {note && (
        <div className="mt-2 text-[11px] text-text-subtle flex items-center gap-1.5">
          <Info size={12} />
          {note}
        </div>
      )}
    </Card>
  )
}

export function TipBox({ children }: { children: ReactNode }) {
  return (
    <div className="rounded-[8px] border border-border bg-surface-raised px-2.5 py-1.5 text-[12px] shadow-md">
      {children}
    </div>
  )
}
