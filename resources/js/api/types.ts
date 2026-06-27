// The production API contract. The mock module returns these exact shapes; the
// real app swaps each mock body for a fetch() without changing the signatures.

export type PlantStatus = 'active' | 'archived' | 'dead'
export type CareType = 'watering' | 'fertilizing' | 'repotting' | 'observation' | 'relocation'
export type FertilizerForm = 'liquid' | 'powdered' | 'granular' | 'organic' | 'food' | 'other'
export type GrowthRate = 'none' | 'slow' | 'moderate' | 'fast'
export type CareStatus = 'ok' | 'due-soon' | 'overdue'

export interface User {
  id: number
  name: string
  email: string
  pushover_user_key: string | null
}

export interface Plant {
  id: number
  common_name: string | null
  scientific_name: string | null
  gbif_key: string | null
  location: string | null
  acquired_on: string | null
  status: PlantStatus
  notes: string | null
  watering_interval_days_override: number | null
  fertilizing_interval_days_override: number | null
  cover_photo_id: number | null
  cover_photo: Photo | null
  condition: Condition
  created_at: string
  updated_at: string
}

export interface Tag {
  id: number
  name: string
  color: string | null
}

export interface PlantWithTags extends Plant {
  tags: Tag[]
}

export interface Photo {
  id: number
  plant_id: number
  care_event_id: number | null
  path: string
  original_filename: string | null
  taken_on: string
  caption: string | null
  created_at: string
  updated_at: string
}

export interface WateringDetail {
  care_event_id: number
  amount_ml: number | null
}

export interface NutrientComponent {
  nutrient_id: number
  nutrient_key: string
  nutrient_label: string
  nutrient_symbol: string | null
  note: string | null
}

export interface FertilizingDetail {
  care_event_id: number
  fertilizer_form_id: number
  form: FertilizerForm
  brand: string | null
  product: string | null
  npk_n: number | null
  npk_p: number | null
  npk_k: number | null
  dose_pct: number | null
  amount_ml: number | null
  nutrients: NutrientComponent[]
}

export interface RepottingDetail {
  care_event_id: number
  soil_recipe: string | null
  pot_size_value: number | null
  pot_size_unit: 'in' | 'cm' | null
  fertilizer_added: boolean
}

export type SymptomCategory = 'leaf' | 'stem' | 'root' | 'pest' | 'disease' | 'general' | 'custom'

export interface Symptom {
  id: number | string
  category: SymptomCategory
  key: string
  label: string
  sort_order: number
  is_custom?: boolean
}

export interface RelocationDetail {
  care_event_id: number
  from_location: string | null
  to_location: string | null
}

export interface ObservationDetail {
  care_event_id: number
  overall_health: number | null
  health_note: string | null
  light_level: number | null
  growth_rate: GrowthRate | null
  growth_note: string | null
  leaf_size_mm: number | null
  weight_grams: number | null
  weight: WeightInput | null
  symptoms: Symptom[]
}

export interface CareEvent {
  id: number
  plant_id: number
  care_event_type_id: number
  type: CareType
  occurred_at: string
  logged_by_user_id: number | null
  note: string | null
  created_at: string
  updated_at: string
  watering?: WateringDetail
  fertilizing?: FertilizingDetail
  repotting?: RepottingDetail
  observation?: ObservationDetail
  relocation?: RelocationDetail
}

export interface Recommendation {
  id: number
  plant_id: number
  type: 'watering' | 'fertilizing'
  interval_days: number | null
  amount_ml: number | null
  sample_size: number
  computed_at: string
}

// Gated, health-aware recommendation served by GET /api/plants/{plant}/recommendations.
// Richer than the flat Recommendation above, which the timeline bundle keeps empty.
export type RecommendationState = 'countdown' | 'ready' | 'no_health_data'

export interface RecommendationGate {
  state: RecommendationState
  history_days: number
  required_days: number
  days_to_go: number
}

// stable: cadence steady, the plain median; revert: recommend the earlier cadence the plant
// was healthier at; maintain: the recent cadence held or improved health.
export type WateringRecommendationBasis = 'stable' | 'revert' | 'maintain'

export interface WateringRecommendation {
  interval_days: number
  amount_ml: number | null
  sample_size: number
  health_sample_size: number
  basis: WateringRecommendationBasis
  baseline_interval_days: number | null
  recent_interval_days: number | null
  rationale: string
  computed_at: string
}

export interface HealthSummary {
  median: number | null
  sample_size: number
}

export interface PositionInsight {
  moved_on: string
  from_location: string | null
  to_location: string | null
  health_before: HealthSummary
  health_after: HealthSummary
}

export interface PlantRecommendations {
  plant_id: number
  gate: RecommendationGate
  watering: WateringRecommendation | null
  position_insights: PositionInsight[]
}

export type ConditionKey =
  | 'healthy'
  | 'fair'
  | 'struggling'
  | 'diseased'
  | 'infested'
  | 'dry'
  | 'burnt'
  | 'unknown'
  | 'dead'

// Derived, presentation-only at-a-glance condition from the latest observation
// plus watering status. No storage; the rules live in the mock derivation.
export interface Condition {
  key: ConditionKey
  label: string
}

// Lookup rows served by the seeded-vocabulary read endpoints. The forms render
// chips and options from these live ids rather than hardcoding them.
export interface FertilizerFormOption {
  id: number
  key: FertilizerForm
  label: string
  sort_order: number
}

export interface NutrientOption {
  nutrient_id: number
  nutrient_key: string
  nutrient_label: string
  nutrient_symbol: string | null
}

export interface SpeciesSuggestion {
  gbif_key: string
  scientific_name: string
  canonical_name: string | null
  common_name: string | null
  rank: string | null
  family: string | null
}

export interface DueForCare {
  plant_id: number
  common_name: string | null
  scientific_name: string | null
  status: CareStatus
  due_date: string
  type: 'watering' | 'fertilizing'
  daysLeft: number
  interval: number
}

export interface RecentActivity {
  event_id: number
  plant_id: number
  plant_common_name: string | null
  type: string
  occurred_at: string
  note: string | null
}

export interface FlaggedProblem {
  plant_id: number
  common_name: string | null
  problem: string
  severity: 'warning' | 'alert'
}

export interface DashboardData {
  user: User
  due_for_care: DueForCare[]
  recent_activity: RecentActivity[]
  flagged_problems: FlaggedProblem[]
}

export interface TrendPoint {
  date: string
  value: number | null
}

export interface GrowthTrendPoint {
  date: string
  value: GrowthRate | null
}

export interface CorrelationPoint {
  x: number
  y: number
}

export interface CorrelationPair {
  x_variable: string
  y_variable: string
  correlation: number
  p_value: number
  sample_size: number
  confidence_band: { lower: number; upper: number }
  // Server-computed Benjamini-Hochberg significance across every tested pair, and the raw
  // observation points the scatter plots, not values synthesized from the coefficient.
  significant_after_fdr: boolean
  points: CorrelationPoint[]
}

export interface GroupComparison {
  plant_id: number
  common_name: string | null
  health_trend: TrendPoint[]
  watering_interval_days: number | null
  fertilizer_interval_days: number | null
}

export interface GroupInsights {
  tag_id: number
  tag_name: string
  plants: number[]
  comparison: GroupComparison[]
  correlation_pairs: CorrelationPair[]
}

export interface PlantTimeline {
  plant: PlantWithTags
  events: CareEvent[]
  health_trend: TrendPoint[]
  weight_trend: TrendPoint[]
  growth_trend: GrowthTrendPoint[]
  recommendations: Recommendation[]
  photos: Photo[]
}

export interface WeightInput {
  lb: number
  oz: number
  g: number
}

export function gramsToWeight(grams: number | null): WeightInput {
  if (grams == null) return { lb: 0, oz: 0, g: 0 }
  const lb = Math.floor(grams / 453.592)
  const remaining = grams - lb * 453.592
  const oz = Math.floor(remaining / 28.3495)
  const g = Math.round((remaining - oz * 28.3495) * 10) / 10
  return { lb, oz, g }
}

export function weightToGrams(weight: WeightInput): number {
  return Math.round((weight.lb * 453.592 + weight.oz * 28.3495 + weight.g) * 10) / 10
}
