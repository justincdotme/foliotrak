import {
  AlertTriangle,
  Bug,
  CircleCheck,
  ClipboardList,
  Droplets,
  Flame,
  FlaskConical,
  HeartPulse,
  Info,
  Leaf,
  Move,
  Shovel,
  X,
  type LucideIcon,
} from 'lucide-react'
import type { CareType, ConditionKey } from '@/api/types'

export const HEALTH_LABELS: Record<number, string> = {
  1: 'Struggling',
  2: 'Fair',
  3: 'Okay',
  4: 'Good',
  5: 'Excellent',
}

export const HEALTH_VAR: Record<number, string> = {
  1: 'var(--health-1)',
  2: 'var(--health-2)',
  3: 'var(--health-3)',
  4: 'var(--health-4)',
  5: 'var(--health-5)',
}

export const SERIES = [
  'var(--series-1)',
  'var(--series-2)',
  'var(--series-3)',
  'var(--series-4)',
  'var(--series-5)',
  'var(--series-6)',
]

export const GROWTH_NUM: Record<string, number> = { none: 0, slow: 1, moderate: 2, fast: 3 }
export const GROWTH_LABEL = ['None', 'Slow', 'Moderate', 'Fast']

export interface CareMeta {
  icon: LucideIcon
  label: string
  color: string
}

export const CARE_META: Record<CareType, CareMeta> = {
  watering: { icon: Droplets, label: 'Watering', color: 'var(--info)' },
  fertilizing: { icon: FlaskConical, label: 'Fertilizing', color: 'var(--accent)' },
  repotting: { icon: Shovel, label: 'Repotting', color: 'var(--series-4)' },
  observation: { icon: ClipboardList, label: 'Observation', color: 'var(--primary)' },
  relocation: { icon: Move, label: 'Moved', color: 'var(--series-3)' },
}

export const CONDITION_COLOR: Record<ConditionKey, string> = {
  healthy: 'var(--ok)',
  fair: 'var(--due-soon)',
  struggling: 'var(--overdue)',
  diseased: 'var(--series-5)',
  infested: 'var(--accent)',
  dry: 'var(--accent)',
  burnt: 'var(--series-4)',
  unknown: 'var(--text-subtle)',
  dead: 'var(--text-subtle)',
}

export const CONDITION_ICON: Record<ConditionKey, LucideIcon> = {
  healthy: CircleCheck,
  fair: Leaf,
  struggling: AlertTriangle,
  diseased: HeartPulse,
  infested: Bug,
  dry: Droplets,
  burnt: Flame,
  unknown: Info,
  dead: X,
}

export interface StatusStyle {
  bg: string
  label: string
}

export const STATUS_STYLE: Record<string, StatusStyle> = {
  ok: { bg: 'var(--ok)', label: 'On track' },
  'due-soon': { bg: 'var(--due-soon)', label: 'Due soon' },
  overdue: { bg: 'var(--overdue)', label: 'Overdue' },
  active: { bg: 'var(--ok)', label: 'Active' },
  archived: { bg: 'var(--text-subtle)', label: 'Archived' },
  dead: { bg: 'var(--overdue)', label: 'Dead' },
}
