import { Activity } from 'lucide-react'
import { Card } from '@/components/ui/card'

export function CorrelationPending() {
  return (
    <Card className="p-6">
      <div className="flex flex-col items-center text-center gap-2">
        <span className="text-text-subtle">
          <Activity size={22} />
        </span>
        <h3 className="text-[14px] font-semibold text-text">Correlation analysis is coming</h3>
        <p className="text-[13px] text-text-muted max-w-prose">
          Once enough history accrues across the group, this view will surface patterns that
          coincided with plant outcomes, each shown with its sample size and an uncertainty band. It
          describes potential factors, never causes.
        </p>
      </div>
    </Card>
  )
}
