import { Check, ClipboardList, Clock, Droplets, FlaskConical, Info } from 'lucide-react'
import type { Plant, PlantRecommendations } from '@/api/types'
import { TooltipButton } from '@/components/ui/tooltip-button'
import { Spinner } from '@/components/app/spinner'
import { useNotification } from '@/components/app/notification-context'
import { handleApiError } from '@/lib/handle-api-error'
import { useUpdatePlant } from '@/hooks/usePlantMutations'

// The Recommended tab below the four-week gate: a calm countdown, never an error state.
function CountdownGate({
  historyDays,
  daysToGo,
  requiredDays,
}: {
  historyDays: number
  daysToGo: number
  requiredDays: number
}) {
  return (
    <div
      dusk="recommended-gate"
      className="rounded-[8px] border border-dashed border-border-strong bg-surface-raised p-4 text-center opacity-95"
    >
      <Clock size={22} className="mx-auto text-text-subtle mb-2" />
      <div className="font-medium">Keep logging.</div>
      <div dusk="recommended-countdown" className="text-[13px] text-text-muted mt-1">
        {daysToGo} day{daysToGo === 1 ? '' : 's'} remaining to unlock recommendations.
      </div>
      <div
        dusk="recommended-progress"
        className="mt-3 h-1.5 rounded-full bg-border overflow-hidden"
      >
        <div
          className="h-full bg-primary"
          style={{ width: Math.min(100, (historyDays / requiredDays) * 100) + '%' }}
        />
      </div>
      <div className="text-[11px] text-text-subtle mt-1.5 tnum">
        {historyDays} of {requiredDays} days of history
      </div>
    </div>
  )
}

// Past the gate but no health readings yet, so the health-aware cadence cannot be derived.
function NoHealthDataGate() {
  return (
    <div className="rounded-[8px] border border-dashed border-border-strong bg-surface-raised p-4 text-center">
      <ClipboardList size={22} className="mx-auto text-text-subtle mb-2" />
      <div className="font-medium">Add a health reading.</div>
      <div className="text-[13px] text-text-muted mt-1">
        There is enough history, but no overall-health observations yet. Log one and a watering
        suggestion will appear here.
      </div>
    </div>
  )
}

// A settled-but-failed recommendations fetch must not read as a perpetual spinner.
function RecommendationsUnavailable() {
  return (
    <div className="rounded-[8px] border border-dashed border-border-strong bg-surface-raised p-4 text-center text-[13px] text-text-muted">
      Recommendations could not load right now. Try again in a moment.
    </div>
  )
}

function FertilizerPending() {
  return (
    <div className="rounded-[8px] border border-border p-3 flex items-start gap-3 opacity-90">
      <FlaskConical size={18} className="text-accent mt-0.5 shrink-0" />
      <div className="flex-1">
        <div className="font-medium text-text-muted">Fertilizer suggestion coming later</div>
        <div className="text-[12px] text-text-subtle mt-0.5">
          Once there is enough feeding history to compare, a cadence will appear here. Set your own
          under My schedule for now.
        </div>
      </div>
    </div>
  )
}

interface RecommendedTabProps {
  plant: Plant
  recommendations: PlantRecommendations | null
  recommendationsLoading?: boolean
  recommendationsError?: boolean
  onAdopted: () => void
}

export function RecommendedTab({
  plant,
  recommendations,
  recommendationsLoading = false,
  recommendationsError = false,
  onAdopted,
}: RecommendedTabProps) {
  const { showError } = useNotification()
  const update = useUpdatePlant(plant.id)
  const gate = recommendations?.gate
  const watering = recommendations?.watering ?? null

  const adoptWatering = async (days: number) => {
    try {
      await update.mutateAsync({ watering_interval_days_override: days })
      onAdopted()
    } catch (err) {
      showError(handleApiError(err))
    }
  }

  if (recommendationsLoading)
    return (
      <div dusk="recommended-tab">
        <Spinner />
      </div>
    )
  if (recommendationsError || !recommendations || !gate)
    return (
      <div dusk="recommended-tab">
        <RecommendationsUnavailable />
      </div>
    )
  if (gate.state === 'countdown')
    return (
      <div dusk="recommended-tab">
        <CountdownGate
          historyDays={gate.history_days}
          daysToGo={gate.days_to_go}
          requiredDays={gate.required_days}
        />
      </div>
    )
  if (gate.state === 'no_health_data')
    return (
      <div dusk="recommended-tab">
        <NoHealthDataGate />
        <FertilizerPending />
      </div>
    )

  return (
    <div dusk="recommended-tab">
      {watering ? (
        <div className="rounded-[8px] border border-border p-3">
          <div className="flex items-start gap-3">
            <Droplets size={18} className="text-info mt-0.5 shrink-0" />
            <div className="flex-1">
              <div dusk="recommended-median" className="font-medium">
                Water about every <span className="tnum">{watering.interval_days}</span> days
                {watering.amount_ml != null && (
                  <>
                    , about <span className="tnum">{watering.amount_ml} ml</span>
                  </>
                )}
              </div>
              <div className="text-[12px] text-text-muted mt-1">{watering.rationale}</div>
            </div>
          </div>
          {plant.watering_interval_days_override !== watering.interval_days && (
            <TooltipButton
              dusk="adopt-schedule"
              size="sm"
              variant="ghost"
              className="mt-2"
              onClick={() => adoptWatering(watering.interval_days)}
              disabled={update.isPending}
              tooltipContent={update.isPending ? 'Saving...' : undefined}
            >
              <Check size={14} />
              Use this for my schedule
            </TooltipButton>
          )}
        </div>
      ) : (
        <div
          dusk="recommended-empty"
          className="rounded-[8px] border border-border p-3 text-[13px] text-text-muted"
        >
          Not enough waterings logged yet to suggest a cadence.
        </div>
      )}
      <FertilizerPending />
      <p className="text-[11px] text-text-subtle flex items-center gap-1.5">
        <Info size={12} />
        Suggestions reflect your own logged cadence, not a universal rule.
      </p>
    </div>
  )
}
