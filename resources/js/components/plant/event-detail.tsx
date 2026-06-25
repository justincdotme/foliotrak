import { Check } from 'lucide-react'
import type { CareEvent } from '@/api/types'
import { gramsToWeight } from '@/api/types'
import { Chip } from '@/components/app/chip'

interface EventDetailProps {
  e: CareEvent
}

export function EventDetail({ e }: EventDetailProps) {
  if (e.type === 'watering') {
    return (
      <div className="text-[13px] text-text-muted">
        Amount:{' '}
        <span className="tnum text-text">
          {e.watering?.amount_ml != null ? `${e.watering.amount_ml} ml` : 'not recorded'}
        </span>
      </div>
    )
  }

  if (e.type === 'fertilizing') {
    const f = e.fertilizing
    if (!f) return null
    return (
      <div className="text-[13px] text-text-muted space-y-1">
        <div className="capitalize">
          Form: <span className="text-text">{f.form}</span>
          {f.brand && (
            <span className="text-text">
              {' '}
              · {f.brand}
              {f.product ? ` ${f.product}` : ''}
            </span>
          )}
        </div>
        {(f.npk_n != null || f.npk_p != null || f.npk_k != null) && (
          <div>
            NPK:{' '}
            <span className="tnum text-text">
              {[f.npk_n, f.npk_p, f.npk_k].map(x => x ?? '–').join(' - ')}
            </span>
            {f.dose_pct != null && (
              <span>
                {' '}
                at <span className="tnum text-text">{f.dose_pct}%</span>
              </span>
            )}
            {f.amount_ml != null && (
              <span>
                {' '}
                · <span className="tnum text-text">{f.amount_ml} ml</span>
              </span>
            )}
          </div>
        )}
        {f.nutrients?.length > 0 && (
          <div className="flex flex-wrap gap-1 pt-0.5">
            {f.nutrients.map(n => (
              <Chip key={n.nutrient_id} color="var(--accent)">
                {n.nutrient_label}
                {n.note ? ` (${n.note})` : ''}
              </Chip>
            ))}
          </div>
        )}
      </div>
    )
  }

  if (e.type === 'repotting') {
    const r = e.repotting
    if (!r) return null
    return (
      <div className="text-[13px] text-text-muted space-y-1">
        {r.pot_size_value != null && (
          <div>
            Pot size:{' '}
            <span className="tnum text-text">
              {r.pot_size_value} {r.pot_size_unit}
            </span>
          </div>
        )}
        {r.soil_recipe && (
          <div>
            Soil: <span className="text-text">{r.soil_recipe}</span>
          </div>
        )}
        {r.fertilizer_added && (
          <div className="flex items-center gap-1 text-text">
            <Check size={13} className="text-primary" />
            Fertilizer added
          </div>
        )}
      </div>
    )
  }

  if (e.type === 'relocation') {
    const r = e.relocation
    if (!r) return null
    return (
      <div className="text-[13px] text-text-muted">
        Moved{' '}
        {r.from_location && (
          <>
            from <span className="text-text">{r.from_location}</span>{' '}
          </>
        )}
        to <span className="text-text">{r.to_location}</span>
      </div>
    )
  }

  const o = e.observation
  if (!o) return null
  const w = o.weight_grams != null ? gramsToWeight(o.weight_grams) : null

  return (
    <div className="text-[13px] text-text-muted space-y-1.5">
      {o.overall_health != null && (
        <div className="flex items-center gap-2">
          Health: <span className="text-text">{o.overall_health}</span>
        </div>
      )}
      {o.health_note && <div className="text-text">{o.health_note}</div>}
      <div className="flex flex-wrap gap-x-4 gap-y-1">
        {o.light_level != null && (
          <span>
            Light: <span className="tnum text-text">{o.light_level}/10</span>
          </span>
        )}
        {o.growth_rate && (
          <span className="capitalize">
            Growth: <span className="text-text">{o.growth_rate}</span>
          </span>
        )}
        {o.leaf_size_mm != null && (
          <span>
            Leaf: <span className="tnum text-text">{o.leaf_size_mm} mm</span>
          </span>
        )}
        {o.weight_grams != null && w && (
          <span>
            Weight: <span className="tnum text-text">{o.weight_grams} g</span>{' '}
            <span className="text-text-subtle">
              ({w.lb}lb {w.oz}oz {w.g}g)
            </span>
          </span>
        )}
      </div>
      {o.symptoms?.length > 0 && (
        <div className="flex flex-wrap gap-1 pt-0.5">
          {o.symptoms.map(s => (
            <Chip key={s.id} color="var(--accent)">
              {s.label}
            </Chip>
          ))}
        </div>
      )}
    </div>
  )
}
