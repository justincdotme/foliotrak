import { NOW } from '@/lib/format'
import { gramsToWeight } from '@/api/types'
import type {
  User,
  Plant,
  Tag,
  PlantWithTags,
  Photo,
  CareEvent,
  Symptom,
  Recommendation,
  DashboardData,
  PlantTimeline,
  DueForCare,
  RecentActivity,
  FlaggedProblem,
  GroupInsights,
  SpeciesSuggestion,
  FertilizerForm,
  GrowthRate,
  Condition,
  CareStatus,
  SymptomCategory,
  TrendPoint,
  GrowthTrendPoint,
} from '@/api/types'

const DAY = 86400000

const iso = (daysAgo: number, hour: number = 9, min: number = 0): string => {
  const d = new Date(NOW.getTime() - daysAgo * DAY)
  d.setHours(hour, min, 0, 0)
  return d.toISOString()
}

const dateOnly = (s: string): string => s.slice(0, 10)

const addDays = (isoStr: string, n: number): string => {
  const d = new Date(new Date(isoStr).getTime() + n * DAY)
  return d.toISOString()
}

const CARE_TYPE_IDS = { watering: 1, fertilizing: 2, repotting: 3, observation: 4 }
const FORM_IDS = { liquid: 1, powdered: 2, granular: 3, organic: 4, food: 5, other: 6 }
const FORM_BY_ID: Record<number, FertilizerForm> = Object.fromEntries(
  Object.entries(FORM_IDS).map(([k, v]) => [v, k])
) as Record<number, FertilizerForm>

export const SYMPTOMS: Symptom[] = [
  { id: 1, category: 'leaf', key: 'yellow_leaf', label: 'Yellowing leaves', sort_order: 1 },
  { id: 2, category: 'leaf', key: 'brown_tips', label: 'Brown leaf tips', sort_order: 2 },
  { id: 3, category: 'leaf', key: 'leaf_drop', label: 'Leaf drop', sort_order: 3 },
  { id: 4, category: 'leaf', key: 'leaf_curl', label: 'Leaf curl', sort_order: 4 },
  { id: 5, category: 'leaf', key: 'leaf_spots', label: 'Leaf spots', sort_order: 5 },
  { id: 6, category: 'stem', key: 'soft_stem', label: 'Soft stem', sort_order: 6 },
  { id: 7, category: 'stem', key: 'leggy', label: 'Leggy growth', sort_order: 7 },
  { id: 8, category: 'root', key: 'root_bound', label: 'Root-bound', sort_order: 8 },
  { id: 9, category: 'root', key: 'root_rot', label: 'Root rot', sort_order: 9 },
  { id: 10, category: 'pest', key: 'spider_mites', label: 'Spider mites', sort_order: 10 },
  { id: 11, category: 'pest', key: 'fungus_gnats', label: 'Fungus gnats', sort_order: 11 },
  { id: 12, category: 'pest', key: 'mealybugs', label: 'Mealybugs', sort_order: 12 },
  {
    id: 13,
    category: 'disease',
    key: 'powdery_mildew',
    label: 'Powdery mildew',
    sort_order: 13,
  },
  { id: 14, category: 'general', key: 'wilting', label: 'Wilting', sort_order: 14 },
  { id: 15, category: 'general', key: 'drooping', label: 'Drooping', sort_order: 15 },
]

const symptomById = (id: number): Symptom | undefined => SYMPTOMS.find(s => s.id === id)

export const NUTRIENTS = [
  { nutrient_id: 1, nutrient_key: 'kelp', nutrient_label: 'Kelp / seaweed', nutrient_symbol: null },
  {
    nutrient_id: 2,
    nutrient_key: 'fish_emulsion',
    nutrient_label: 'Fish emulsion',
    nutrient_symbol: null,
  },
  {
    nutrient_id: 3,
    nutrient_key: 'worm_castings',
    nutrient_label: 'Worm castings',
    nutrient_symbol: null,
  },
  {
    nutrient_id: 4,
    nutrient_key: 'humic_acid',
    nutrient_label: 'Humic acid',
    nutrient_symbol: null,
  },
]

export const GBIF: SpeciesSuggestion[] = [
  {
    gbif_key: '2872573',
    scientific_name: 'Epipremnum aureum (Linden & André) G.S.Bunting',
    canonical_name: 'Epipremnum aureum',
    common_name: 'Golden pothos',
    rank: 'SPECIES',
    family: 'Araceae',
  },
  {
    gbif_key: '2872586',
    scientific_name: 'Monstera deliciosa Liebm.',
    canonical_name: 'Monstera deliciosa',
    common_name: 'Swiss cheese plant',
    rank: 'SPECIES',
    family: 'Araceae',
  },
  {
    gbif_key: '2769096',
    scientific_name: 'Dracaena trifasciata (Prain) Mabb.',
    canonical_name: 'Dracaena trifasciata',
    common_name: 'Snake plant',
    rank: 'SPECIES',
    family: 'Asparagaceae',
  },
  {
    gbif_key: '5361896',
    scientific_name: 'Ficus lyrata Warb.',
    canonical_name: 'Ficus lyrata',
    common_name: 'Fiddle-leaf fig',
    rank: 'SPECIES',
    family: 'Moraceae',
  },
  {
    gbif_key: '2872588',
    scientific_name: 'Zamioculcas zamiifolia (Lodd.) Engl.',
    canonical_name: 'Zamioculcas zamiifolia',
    common_name: 'ZZ plant',
    rank: 'SPECIES',
    family: 'Araceae',
  },
  {
    gbif_key: '2872999',
    scientific_name: 'Spathiphyllum wallisii Regel',
    canonical_name: 'Spathiphyllum wallisii',
    common_name: 'Peace lily',
    rank: 'SPECIES',
    family: 'Araceae',
  },
  {
    gbif_key: '2873012',
    scientific_name: 'Chlorophytum comosum (Thunb.) Jacques',
    canonical_name: 'Chlorophytum comosum',
    common_name: 'Spider plant',
    rank: 'SPECIES',
    family: 'Asparagaceae',
  },
  {
    gbif_key: '3084283',
    scientific_name: 'Calathea orbifolia (Linden) H.Kenn.',
    canonical_name: 'Calathea orbifolia',
    common_name: 'Prayer plant',
    rank: 'SPECIES',
    family: 'Marantaceae',
  },
]

export const TAGS: Tag[] = [
  { id: 1, name: 'Living room', color: 'var(--series-1)' },
  { id: 2, name: 'Low light', color: 'var(--series-3)' },
  { id: 3, name: 'Office', color: 'var(--series-4)' },
  { id: 4, name: 'Bright window', color: 'var(--series-2)' },
]

export const USER: User = {
  id: 1,
  name: 'Avery Quill',
  email: 'avery@home.lan',
  pushover_user_key: 'u9kx2…7fp',
}

let EID = 1000

interface WateringOptions {
  note?: string | null
}

const watering = (
  plantId: number,
  daysAgo: number,
  amount: number,
  options?: WateringOptions
): CareEvent => {
  const note = options?.note ?? null
  return {
    id: ++EID,
    plant_id: plantId,
    care_event_type_id: CARE_TYPE_IDS.watering,
    type: 'watering',
    occurred_at: iso(daysAgo, 8, 30),
    logged_by_user_id: 1,
    note,
    created_at: iso(daysAgo, 8, 31),
    updated_at: iso(daysAgo, 8, 31),
    watering: { care_event_id: EID, amount_ml: amount },
  }
}

interface FertilizingOptions {
  form?: FertilizerForm
  brand?: string | null
  product?: string | null
  npk_n?: number | null
  npk_p?: number | null
  npk_k?: number | null
  dose_pct?: number | null
  amount_ml?: number | null
  nutrients?: Array<{ id: number; note?: string | null }>
  note?: string | null
}

const fertilizing = (plantId: number, daysAgo: number, opts: FertilizingOptions): CareEvent => {
  const o = {
    form: 'liquid' as FertilizerForm,
    brand: null as string | null,
    product: null as string | null,
    npk_n: null as number | null,
    npk_p: null as number | null,
    npk_k: null as number | null,
    dose_pct: null as number | null,
    amount_ml: null as number | null,
    nutrients: [] as Array<{ id: number; note?: string | null }>,
    note: null as string | null,
    ...opts,
  }

  return {
    id: ++EID,
    plant_id: plantId,
    care_event_type_id: CARE_TYPE_IDS.fertilizing,
    type: 'fertilizing',
    occurred_at: iso(daysAgo, 9, 15),
    logged_by_user_id: 1,
    note: o.note,
    created_at: iso(daysAgo, 9, 16),
    updated_at: iso(daysAgo, 9, 16),
    fertilizing: {
      care_event_id: EID,
      fertilizer_form_id: FORM_IDS[o.form],
      form: o.form,
      brand: o.brand,
      product: o.product,
      npk_n: o.npk_n,
      npk_p: o.npk_p,
      npk_k: o.npk_k,
      dose_pct: o.dose_pct,
      amount_ml: o.amount_ml,
      nutrients: (o.nutrients || []).map(n => {
        const meta = NUTRIENTS.find(x => x.nutrient_id === n.id)
        return meta
          ? { ...meta, note: n.note ?? null }
          : {
              nutrient_id: 0,
              nutrient_key: '',
              nutrient_label: '',
              nutrient_symbol: null,
              note: n.note ?? null,
            }
      }),
    },
  }
}

interface RepottingOptions {
  soil_recipe?: string | null
  pot_size_value?: number | null
  pot_size_unit?: 'in' | 'cm'
  fertilizer_added?: boolean
  note?: string | null
}

const repotting = (plantId: number, daysAgo: number, opts: RepottingOptions): CareEvent => {
  const o = {
    soil_recipe: null as string | null,
    pot_size_value: null as number | null,
    pot_size_unit: 'in' as 'in' | 'cm',
    fertilizer_added: false,
    note: null as string | null,
    ...opts,
  }

  return {
    id: ++EID,
    plant_id: plantId,
    care_event_type_id: CARE_TYPE_IDS.repotting,
    type: 'repotting',
    occurred_at: iso(daysAgo, 11, 0),
    logged_by_user_id: 1,
    note: o.note,
    created_at: iso(daysAgo, 11, 1),
    updated_at: iso(daysAgo, 11, 1),
    repotting: {
      care_event_id: EID,
      soil_recipe: o.soil_recipe,
      pot_size_value: o.pot_size_value,
      pot_size_unit: o.pot_size_unit,
      fertilizer_added: o.fertilizer_added,
    },
  }
}

interface ObservationOptions {
  overall_health?: number | null
  health_note?: string | null
  light_level?: number | null
  growth_rate?: GrowthRate | null
  growth_note?: string | null
  leaf_size_mm?: number | null
  weight_grams?: number | null
  symptom_ids?: number[]
  custom_symptoms?: string[]
  note?: string | null
}

const observation = (plantId: number, daysAgo: number, opts: ObservationOptions): CareEvent => {
  const o = {
    overall_health: null as number | null,
    health_note: null as string | null,
    light_level: null as number | null,
    growth_rate: null as GrowthRate | null,
    growth_note: null as string | null,
    leaf_size_mm: null as number | null,
    weight_grams: null as number | null,
    symptom_ids: [] as number[],
    custom_symptoms: [] as string[],
    note: null as string | null,
    ...opts,
  }

  const customs: Symptom[] = (o.custom_symptoms || []).map((label, idx) => ({
    id: `c${EID}_${idx}`,
    category: 'custom' as SymptomCategory,
    key: 'custom',
    label,
    sort_order: 99,
    is_custom: true,
  }))

  return {
    id: ++EID,
    plant_id: plantId,
    care_event_type_id: CARE_TYPE_IDS.observation,
    type: 'observation',
    occurred_at: iso(daysAgo, 18, 0),
    logged_by_user_id: 1,
    note: o.note,
    created_at: iso(daysAgo, 18, 1),
    updated_at: iso(daysAgo, 18, 1),
    observation: {
      care_event_id: EID,
      overall_health: o.overall_health,
      health_note: o.health_note,
      light_level: o.light_level,
      growth_rate: o.growth_rate,
      growth_note: o.growth_note,
      leaf_size_mm: o.leaf_size_mm,
      weight_grams: o.weight_grams,
      weight: o.weight_grams != null ? gramsToWeight(o.weight_grams) : null,
      symptoms: [
        ...(o.symptom_ids || []).map(symptomById).filter(s => s !== undefined),
        ...customs,
      ],
    },
  }
}

interface RelocationOptions {
  from?: string | null
  to?: string | null
  note?: string | null
}

const relocation = (plantId: number, daysAgo: number, opts: RelocationOptions): CareEvent => {
  const o = {
    from: null as string | null,
    to: null as string | null,
    note: null as string | null,
    ...opts,
  }

  return {
    id: ++EID,
    plant_id: plantId,
    care_event_type_id: 5,
    type: 'relocation',
    occurred_at: iso(daysAgo, 12, 0),
    logged_by_user_id: 1,
    note: o.note,
    created_at: iso(daysAgo, 12, 1),
    updated_at: iso(daysAgo, 12, 1),
    relocation: { care_event_id: EID, from_location: o.from, to_location: o.to },
  }
}

const waterSeries = (
  plantId: number,
  intervalDays: number,
  startDaysAgo: number,
  amount: number,
  jitter: number = 0
): CareEvent[] => {
  const out: CareEvent[] = []
  let d = startDaysAgo
  while (d >= 1) {
    const amt = amount + Math.round(Math.sin(d) * jitter)
    out.push(watering(plantId, d, Math.max(40, amt)))
    d -= intervalDays
  }
  return out
}

interface StoreData {
  events: CareEvent[]
  plants: PlantWithTags[]
  photos: Photo[]
  recs: Recommendation[]
}

const buildSeed = (): StoreData => {
  const events: CareEvent[] = []

  // Pothos: 12 weeks of frequent watering, healthy, recs unlock
  events.push(...waterSeries(1, 4, 82, 200, 25))
  ;[80, 52, 24].forEach((d, i) =>
    events.push(
      fertilizing(1, d, {
        form: 'liquid',
        brand: 'Dyna-Gro',
        product: 'Foliage-Pro',
        npk_n: 9,
        npk_p: 3,
        npk_k: 6,
        dose_pct: 50,
        amount_ml: 240,
        note: i === 0 ? 'Diluted half strength' : null,
      })
    )
  )
  ;[
    { d: 78, h: 4, gr: 'moderate' as GrowthRate, ls: 70, w: 820, note: null },
    { d: 60, h: 4, gr: 'moderate' as GrowthRate, ls: 82, w: 910, note: null },
    { d: 42, h: 5, gr: 'fast' as GrowthRate, ls: 96, w: 1010, note: 'New leaf unfurled' },
    { d: 28, h: 5, gr: 'fast' as GrowthRate, ls: 110, w: 1120, note: null },
    { d: 14, h: 5, gr: 'moderate' as GrowthRate, ls: 121, w: 1190, note: null },
    { d: 3, h: 5, gr: 'moderate' as GrowthRate, ls: 130, w: 1240, note: null },
  ].forEach(entry =>
    events.push(
      observation(1, entry.d, {
        overall_health: entry.h,
        light_level: 6,
        growth_rate: entry.gr,
        leaf_size_mm: entry.ls,
        weight_grams: entry.w,
        note: entry.note,
      })
    )
  )

  // Monstera: 11 weeks, slower watering, repotted 6 weeks ago, one health dip
  events.push(...waterSeries(2, 9, 76, 420, 40))
  events.push(
    repotting(2, 42, {
      soil_recipe: '5 parts bark, 2 parts coco coir, 1 part perlite',
      pot_size_value: 10,
      pot_size_unit: 'in',
      fertilizer_added: true,
      note: 'Roots were circling the old nursery pot',
    })
  )
  ;[70, 46].forEach(d =>
    events.push(
      fertilizing(2, d, {
        form: 'organic',
        brand: "Neptune's Harvest",
        product: 'Fish & Seaweed',
        dose_pct: 50,
        amount_ml: 300,
        nutrients: [{ id: 2, note: '2-3-1' }, { id: 1 }],
        note: null,
      })
    )
  )
  events.push(
    observation(2, 72, {
      overall_health: 4,
      light_level: 7,
      growth_rate: 'moderate',
      leaf_size_mm: 240,
      weight_grams: 2650,
    })
  )
  events.push(
    observation(2, 50, {
      overall_health: 2,
      health_note: 'Older leaf yellowing near the base after a cold snap by the window',
      light_level: 7,
      growth_rate: 'slow',
      leaf_size_mm: 250,
      weight_grams: 2600,
      symptom_ids: [1],
    })
  )
  events.push(
    observation(2, 30, {
      overall_health: 3,
      light_level: 7,
      growth_rate: 'slow',
      leaf_size_mm: 255,
      weight_grams: 2700,
    })
  )
  events.push(
    observation(2, 10, {
      overall_health: 4,
      light_level: 7,
      growth_rate: 'moderate',
      leaf_size_mm: 268,
      weight_grams: 2810,
      note: 'New fenestrated leaf opening',
    })
  )

  // Snake plant: 9 weeks, sparse watering, very stable
  events.push(...waterSeries(3, 18, 64, 150, 10))
  ;[60, 30].forEach(d =>
    events.push(
      observation(3, d, {
        overall_health: 4,
        light_level: 3,
        growth_rate: 'slow',
        weight_grams: 1450,
      })
    )
  )
  events.push(
    observation(3, 6, {
      overall_health: 4,
      light_level: 3,
      growth_rate: 'none',
      weight_grams: 1460,
    })
  )

  // Fiddle-leaf fig: 8 weeks, irregular watering, struggles then recovers
  ;[54, 49, 40, 33, 22, 11].forEach((d, i) => events.push(watering(4, d, 300 + (i % 2 ? 60 : 0))))
  events.push(
    fertilizing(4, 35, {
      form: 'liquid',
      brand: 'FoxFarm',
      product: 'Grow Big',
      npk_n: 6,
      npk_p: 4,
      npk_k: 4,
      dose_pct: 25,
      amount_ml: 250,
    })
  )
  events.push(
    observation(4, 53, {
      overall_health: 2,
      health_note: 'Two lower leaves dropped after I moved it away from the window',
      light_level: 5,
      growth_rate: 'none',
      leaf_size_mm: 180,
      weight_grams: 1980,
      symptom_ids: [3, 2],
    })
  )
  events.push(
    observation(4, 38, {
      overall_health: 2,
      light_level: 5,
      growth_rate: 'none',
      leaf_size_mm: 178,
      weight_grams: 1940,
      symptom_ids: [3],
    })
  )
  events.push(
    observation(4, 24, {
      overall_health: 3,
      health_note: 'Moved back to the bright window; no new drop this week',
      light_level: 8,
      growth_rate: 'slow',
      leaf_size_mm: 182,
      weight_grams: 1990,
    })
  )
  events.push(
    observation(4, 8, {
      overall_health: 4,
      health_note: 'New leaf bud at the crown',
      light_level: 8,
      growth_rate: 'moderate',
      leaf_size_mm: 196,
      weight_grams: 2070,
    })
  )

  // ZZ plant: ~2.5 weeks only (under 4-week gate, countdown), limited data
  ;[16, 9, 6].forEach(d => events.push(watering(5, d, 180)))
  events.push(
    observation(5, 15, {
      overall_health: 4,
      light_level: 2,
      growth_rate: 'none',
      weight_grams: 1120,
    })
  )
  events.push(
    observation(5, 3, {
      overall_health: 4,
      light_level: 2,
      growth_rate: 'slow',
      weight_grams: 1130,
    })
  )

  const plants: PlantWithTags[] = [
    plant(
      1,
      'Pothos',
      'Epipremnum aureum',
      '2872573',
      'Living room shelf',
      84,
      ['Living room', 'Low light'],
      'Trailing over the bookcase. Cutting from a friend.'
    ),
    plant(
      2,
      'Monstera',
      'Monstera deliciosa',
      '2872586',
      'Living room corner',
      77,
      ['Living room', 'Bright window'],
      null
    ),
    plant(
      3,
      'Snake plant',
      'Dracaena trifasciata',
      '2769096',
      'Hallway',
      66,
      ['Low light'],
      'Basically indestructible.'
    ),
    plant(
      4,
      'Fiddle-leaf fig',
      'Ficus lyrata',
      '5361896',
      'Bright window',
      56,
      ['Living room', 'Bright window'],
      'The drama queen.'
    ),
    plant(
      5,
      'ZZ plant',
      'Zamioculcas zamiifolia',
      '2872588',
      'Office desk',
      18,
      ['Office', 'Low light'],
      'Just brought home.'
    ),
  ]

  const photos: Photo[] = [
    photo(1, 1, 'pothos-1', 78, 'Just settled in on the shelf'),
    photo(2, 1, 'pothos-2', 28, 'Filling out nicely'),
    photo(3, 1, 'pothos-3', 3, 'Newest leaf'),
    photo(4, 2, 'monstera-1', 72, 'Before repot'),
    photo(5, 2, 'monstera-2', 10, 'New fenestrated leaf'),
    photo(6, 4, 'fiddle-1', 53, 'After the leaf drop'),
    photo(7, 4, 'fiddle-2', 8, 'Recovering at the window'),
  ]

  const recs: Recommendation[] = [
    {
      id: 1,
      plant_id: 1,
      type: 'watering',
      interval_days: 5,
      amount_ml: 200,
      sample_size: 21,
      computed_at: iso(1),
    },
    {
      id: 2,
      plant_id: 1,
      type: 'fertilizing',
      interval_days: 28,
      amount_ml: 240,
      sample_size: 3,
      computed_at: iso(1),
    },
    {
      id: 3,
      plant_id: 2,
      type: 'watering',
      interval_days: 9,
      amount_ml: 420,
      sample_size: 9,
      computed_at: iso(1),
    },
    {
      id: 4,
      plant_id: 4,
      type: 'watering',
      interval_days: 8,
      amount_ml: 320,
      sample_size: 7,
      computed_at: iso(1),
    },
    {
      id: 5,
      plant_id: 3,
      type: 'watering',
      interval_days: 18,
      amount_ml: 150,
      sample_size: 4,
      computed_at: iso(1),
    },
  ]

  return { events, plants, photos, recs }

  function plant(
    id: number,
    common: string,
    sci: string,
    gbif: string,
    location: string,
    acquiredDaysAgo: number,
    tagNames: string[],
    notes: string | null
  ): PlantWithTags {
    return {
      id,
      common_name: common,
      scientific_name: sci,
      gbif_key: gbif,
      location,
      acquired_on: dateOnly(iso(acquiredDaysAgo)),
      status: 'active',
      notes,
      watering_interval_days_override: null,
      fertilizing_interval_days_override: null,
      cover_photo_id: null,
      cover_photo: null,
      condition: { key: 'unknown', label: 'No reading' },
      created_at: iso(acquiredDaysAgo),
      updated_at: iso(1),
      tags: tagNames.map(n => TAGS.find(t => t.name === n) ?? { id: 0, name: '', color: null }),
    }
  }

  function photo(
    id: number,
    plant_id: number,
    name: string,
    daysAgo: number,
    caption: string
  ): Photo {
    return {
      id,
      plant_id,
      care_event_id: null,
      path: `/storage/photos/${name}.jpg`,
      original_filename: `${name}.jpg`,
      taken_on: dateOnly(iso(daysAgo)),
      caption,
      created_at: iso(daysAgo),
      updated_at: iso(daysAgo),
    }
  }
}

const STORE = buildSeed()

const clone = <T>(x: T): T => JSON.parse(JSON.stringify(x)) as T
const wait = (ms: number = 160): Promise<void> =>
  new Promise(r => {
    setTimeout(r, ms)
  })
const ageDays = (isoStr: string): number =>
  Math.floor((NOW.getTime() - new Date(isoStr).getTime()) / DAY)

const lastOfType = (plantId: number, type: string): CareEvent | null => {
  const last = STORE.events
    .filter(e => e.plant_id === plantId && e.type === type)
    .sort((a, b) => new Date(b.occurred_at).getTime() - new Date(a.occurred_at).getTime())[0]
  return last ?? null
}

export const nextDue = (
  plant: Plant
): {
  due_date: string
  daysLeft: number
  status: CareStatus
  type: 'watering'
  interval: number
  last_watered: string
} | null => {
  const lastW = lastOfType(plant.id, 'watering')
  const rec = STORE.recs.find(r => r.plant_id === plant.id && r.type === 'watering')
  const interval = plant.watering_interval_days_override ?? rec?.interval_days ?? 7
  if (!lastW) return null
  const due = addDays(lastW.occurred_at, interval)
  const daysLeft = Math.round((new Date(due).getTime() - NOW.getTime()) / DAY)
  const status: CareStatus = daysLeft < 0 ? 'overdue' : daysLeft <= 1 ? 'due-soon' : 'ok'
  return {
    due_date: dateOnly(due),
    daysLeft,
    status,
    type: 'watering',
    interval,
    last_watered: lastW.occurred_at,
  }
}

export const plantCondition = (plant: Plant): Condition => {
  const obs = STORE.events
    .filter(e => e.plant_id === plant.id && e.type === 'observation')
    .sort((a, b) => new Date(b.occurred_at).getTime() - new Date(a.occurred_at).getTime())[0]

  const due = nextDue(plant)

  if (plant.status === 'dead') return { key: 'dead', label: 'Did not make it' }

  const cats = obs ? (obs.observation?.symptoms || []).map(s => s.category) : []
  const keys = obs ? (obs.observation?.symptoms || []).map(s => s.key) : []

  if (cats.includes('pest')) return { key: 'infested', label: 'Infested' }
  if (cats.includes('disease')) return { key: 'diseased', label: 'Diseased' }
  if (due && due.status === 'overdue' && due.daysLeft < -Math.max(2, due.interval * 0.4)) {
    return { key: 'dry', label: 'Likely dry' }
  }
  if (keys.includes('brown_tips') || keys.includes('leaf_spots')) {
    return { key: 'burnt', label: 'Leaf stress' }
  }
  if (!obs || obs.observation?.overall_health == null)
    return { key: 'unknown', label: 'No reading' }

  const h = obs.observation.overall_health
  if (h >= 4) return { key: 'healthy', label: 'Healthy' }
  if (h === 3) return { key: 'fair', label: 'Fair' }
  return { key: 'struggling', label: 'Struggling' }
}

const trendFrom = (
  plantId: number,
  key: 'overall_health' | 'weight_grams' | 'growth_rate'
): Array<{ date: string; value: number | null } | { date: string; value: GrowthRate | null }> => {
  return STORE.events
    .filter(e => e.plant_id === plantId && e.type === 'observation')
    .sort((a, b) => new Date(a.occurred_at).getTime() - new Date(b.occurred_at).getTime())
    .map(e => {
      const obs = e.observation
      if (!obs) return { date: dateOnly(e.occurred_at), value: null }
      const val = obs[key]
      return { date: dateOnly(e.occurred_at), value: (val ?? null) as number | GrowthRate | null }
    }) as Array<{ date: string; value: number | null } | { date: string; value: GrowthRate | null }>
}

export const mockApi = {
  getPriming: async (): Promise<{ message: string }> => {
    await wait()
    return { message: 'Foliotrak ready' }
  },

  login: async (_email: string, _password: string): Promise<User> => {
    await wait(300)
    return clone(USER)
  },

  logout: async (): Promise<{ message: string }> => {
    await wait()
    return { message: 'Logged out' }
  },

  getUser: async (): Promise<User> => {
    await wait()
    return clone(USER)
  },

  listPlants: async (): Promise<PlantWithTags[]> => {
    await wait()
    return clone(STORE.plants)
  },

  getPlant: async (id: number): Promise<PlantWithTags | null> => {
    await wait()
    const plant = STORE.plants.find(p => p.id === id)
    return plant ? clone(plant) : null
  },

  createPlant: async (data: Partial<PlantWithTags>): Promise<PlantWithTags> => {
    await wait(280)
    const id = Math.max(...STORE.plants.map(p => p.id)) + 1
    const p: PlantWithTags = {
      id,
      common_name: data.common_name ?? null,
      scientific_name: data.scientific_name ?? null,
      gbif_key: data.gbif_key ?? null,
      location: data.location ?? null,
      acquired_on: data.acquired_on ?? dateOnly(iso(0)),
      status: 'active',
      notes: data.notes ?? null,
      watering_interval_days_override: null,
      fertilizing_interval_days_override: null,
      cover_photo_id: null,
      cover_photo: null,
      condition: { key: 'unknown', label: 'No reading' },
      created_at: iso(0),
      updated_at: iso(0),
      tags: data.tags || [],
    }
    STORE.plants.push(p)
    return clone(p)
  },

  updatePlant: async (id: number, data: Partial<PlantWithTags>): Promise<PlantWithTags | null> => {
    await wait()
    const p = STORE.plants.find(x => x.id === id)
    if (!p) return null
    Object.assign(p, data, { updated_at: iso(0) })
    return clone(p)
  },

  deletePlant: async (id: number): Promise<{ message: string }> => {
    await wait()
    STORE.plants = STORE.plants.filter(p => p.id !== id)
    return { message: 'Deleted' }
  },

  getTimeline: async (plantId: number): Promise<PlantTimeline | null> => {
    await wait()
    const plant = STORE.plants.find(p => p.id === plantId)
    if (!plant) return null
    const events = STORE.events
      .filter(e => e.plant_id === plantId)
      .sort((a, b) => new Date(b.occurred_at).getTime() - new Date(a.occurred_at).getTime())

    const healthTrend: TrendPoint[] = trendFrom(plantId, 'overall_health').map(t => ({
      date: t.date,
      value: t.value as number | null,
    }))
    const weightTrend: TrendPoint[] = trendFrom(plantId, 'weight_grams').map(t => ({
      date: t.date,
      value: t.value as number | null,
    }))
    const growthTrend: GrowthTrendPoint[] = trendFrom(plantId, 'growth_rate').map(t => ({
      date: t.date,
      value: t.value as GrowthRate | null,
    }))

    return clone({
      plant,
      events,
      health_trend: healthTrend,
      weight_trend: weightTrend,
      growth_trend: growthTrend,
      recommendations: STORE.recs.filter(r => r.plant_id === plantId),
      photos: STORE.photos.filter(p => p.plant_id === plantId),
    })
  },

  getRecommendations: async (plantId: number): Promise<Recommendation[]> => {
    await wait()
    return clone(STORE.recs.filter(r => r.plant_id === plantId))
  },

  createWatering: async (
    plantId: number,
    data: { occurred_at: string; amount_ml?: number | null; note?: string | null }
  ): Promise<CareEvent> => {
    await wait(220)
    const e = watering(plantId, ageDays(data.occurred_at), data.amount_ml ?? 0, {
      note: data.note ?? null,
    })
    e.occurred_at = data.occurred_at
    STORE.events.push(e)
    return clone(e)
  },

  createFertilizing: async (
    plantId: number,
    data: {
      occurred_at: string
      fertilizer_form_id?: number
      brand?: string | null
      product?: string | null
      npk_n?: number | null
      npk_p?: number | null
      npk_k?: number | null
      dose_pct?: number | null
      amount_ml?: number | null
      nutrients?: Array<{ nutrient_id: number; note?: string | null }>
      note?: string | null
    }
  ): Promise<CareEvent> => {
    await wait(220)
    const formId = data.fertilizer_form_id ?? 1
    const form: FertilizerForm = FORM_BY_ID[formId] ?? 'liquid'
    const e = fertilizing(plantId, ageDays(data.occurred_at), {
      form,
      brand: data.brand,
      product: data.product,
      npk_n: data.npk_n,
      npk_p: data.npk_p,
      npk_k: data.npk_k,
      dose_pct: data.dose_pct,
      amount_ml: data.amount_ml,
      nutrients: (data.nutrients || []).map(n => ({ id: n.nutrient_id, note: n.note })),
      note: data.note,
    })
    e.occurred_at = data.occurred_at
    STORE.events.push(e)
    return clone(e)
  },

  createRepotting: async (
    plantId: number,
    data: {
      occurred_at: string
      soil_recipe?: string | null
      pot_size_value?: number | null
      pot_size_unit?: 'in' | 'cm'
      fertilizer_added?: boolean
      note?: string | null
    }
  ): Promise<CareEvent> => {
    await wait(220)
    const e = repotting(plantId, ageDays(data.occurred_at), data)
    e.occurred_at = data.occurred_at
    STORE.events.push(e)
    return clone(e)
  },

  createObservation: async (
    plantId: number,
    data: ObservationOptions & { occurred_at: string }
  ): Promise<CareEvent> => {
    await wait(220)
    const e = observation(plantId, ageDays(data.occurred_at), {
      ...data,
      custom_symptoms: data.custom_symptoms || [],
    })
    e.occurred_at = data.occurred_at
    STORE.events.push(e)
    return clone(e)
  },

  createRelocation: async (
    plantId: number,
    data: {
      occurred_at?: string
      from_location?: string | null
      to_location?: string | null
      note?: string | null
    }
  ): Promise<CareEvent> => {
    await wait(180)
    const e = relocation(plantId, ageDays(data.occurred_at ?? iso(0)), {
      from: data.from_location,
      to: data.to_location,
      note: data.note,
    })
    e.occurred_at = data.occurred_at ?? iso(0)
    STORE.events.push(e)
    return clone(e)
  },

  updateCareEvent: async (eventId: number, data: Partial<CareEvent>): Promise<CareEvent | null> => {
    await wait()
    const e = STORE.events.find(x => x.id === eventId)
    if (!e) return null
    Object.assign(e, data, { updated_at: iso(0) })
    return clone(e)
  },

  deleteCareEvent: async (eventId: number): Promise<{ message: string }> => {
    await wait()
    STORE.events = STORE.events.filter(e => e.id !== eventId)
    return { message: 'Deleted' }
  },

  listPhotos: async (plantId: number): Promise<Photo[]> => {
    await wait()
    return clone(STORE.photos.filter(p => p.plant_id === plantId))
  },

  uploadPhoto: async (
    plantId: number,
    file: File | undefined,
    caption?: string | null
  ): Promise<Photo> => {
    await wait(300)
    const id = Math.max(0, ...STORE.photos.map(p => p.id)) + 1
    const p: Photo = {
      id,
      plant_id: plantId,
      care_event_id: null,
      path: `/storage/photos/upload-${id}.jpg`,
      original_filename: file?.name ?? 'upload.jpg',
      taken_on: dateOnly(iso(0)),
      caption: caption ?? null,
      created_at: iso(0),
      updated_at: iso(0),
    }
    STORE.photos.push(p)
    return clone(p)
  },

  deletePhoto: async (photoId: number): Promise<{ message: string }> => {
    await wait()
    STORE.photos = STORE.photos.filter(p => p.id !== photoId)
    return { message: 'Deleted' }
  },

  listTags: async (): Promise<Tag[]> => {
    await wait()
    return clone(TAGS)
  },

  createTag: async (name: string, color?: string | null): Promise<Tag> => {
    await wait()
    const tagId = Math.max(...TAGS.map(t => t.id)) + 1
    const t: Tag = { id: tagId, name, color: color ?? null }
    TAGS.push(t)
    return clone(t)
  },

  updateTag: async (id: number, name: string, color?: string | null): Promise<Tag | null> => {
    await wait()
    const t = TAGS.find(x => x.id === id)
    if (!t) return null
    Object.assign(t, { name, color })
    return clone(t)
  },

  deleteTag: async (_id: number): Promise<{ message: string }> => {
    await wait()
    return { message: 'Deleted' }
  },

  suggestSpecies: async (q: string): Promise<SpeciesSuggestion[]> => {
    await wait(220)
    const s = q.trim().toLowerCase()
    if (!s) return []
    return clone(
      GBIF.filter(g =>
        (g.canonical_name + ' ' + (g.common_name || '')).toLowerCase().includes(s)
      ).slice(0, 6)
    )
  },

  getDashboard: async (): Promise<DashboardData> => {
    await wait()
    const due: DueForCare[] = STORE.plants
      .filter(p => p.status === 'active')
      .map(p => {
        const d = nextDue(p)
        if (!d) return null
        return {
          plant_id: p.id,
          common_name: p.common_name,
          scientific_name: p.scientific_name,
          status: d.status,
          due_date: d.due_date,
          type: d.type,
          daysLeft: d.daysLeft,
          interval: d.interval,
        }
      })
      .filter(x => x !== null)
      .sort((a, b) => a.daysLeft - b.daysLeft)

    const recent: RecentActivity[] = STORE.events
      .slice()
      .sort((a, b) => new Date(b.occurred_at).getTime() - new Date(a.occurred_at).getTime())
      .slice(0, 8)
      .map(e => {
        const p = STORE.plants.find(x => x.id === e.plant_id)
        return {
          event_id: e.id,
          plant_id: e.plant_id,
          plant_common_name: p?.common_name ?? null,
          type: e.type,
          occurred_at: e.occurred_at,
          note: e.note,
        }
      })

    const flagged: FlaggedProblem[] = [
      {
        plant_id: 4,
        common_name: 'Fiddle-leaf fig',
        problem: 'Recent health decline coincided with a move away from the window',
        severity: 'alert',
      },
      {
        plant_id: 2,
        common_name: 'Monstera',
        problem: 'Yellowing leaf logged 7 weeks ago; watch for repeat',
        severity: 'warning',
      },
    ]

    return clone({
      user: USER,
      due_for_care: due,
      recent_activity: recent,
      flagged_problems: flagged,
    })
  },

  getGroupInsights: async (_tagId: number): Promise<GroupInsights> => {
    await wait(260)
    const tag = TAGS.find(t => t.id === _tagId)
    const inGroup = STORE.plants.filter(p => p.tags.some(t => t.id === _tagId))
    const comparison = inGroup.map(p => ({
      plant_id: p.id,
      common_name: p.common_name,
      health_trend: trendFrom(p.id, 'overall_health').map(t => ({
        date: t.date,
        value: t.value as number | null,
      })),
      watering_interval_days:
        STORE.recs.find(r => r.plant_id === p.id && r.type === 'watering')?.interval_days ?? null,
      fertilizer_interval_days:
        STORE.recs.find(r => r.plant_id === p.id && r.type === 'fertilizing')?.interval_days ??
        null,
    }))

    return clone({
      tag_id: _tagId,
      tag_name: tag?.name ?? '',
      plants: inGroup.map(p => p.id),
      comparison,
      correlation_pairs: [
        {
          x_variable: 'watering_frequency',
          y_variable: 'health_trend',
          correlation: 0.42,
          p_value: 0.08,
          sample_size: 48,
          confidence_band: { lower: 0.06, upper: 0.68 },
          significant_after_fdr: false,
          points: [],
        },
        {
          x_variable: 'light_level',
          y_variable: 'health_trend',
          correlation: 0.61,
          p_value: 0.02,
          sample_size: 22,
          confidence_band: { lower: 0.28, upper: 0.82 },
          significant_after_fdr: true,
          points: [],
        },
        {
          x_variable: 'fertilizer_npk_n',
          y_variable: 'growth_rate',
          correlation: 0.33,
          p_value: 0.21,
          sample_size: 14,
          confidence_band: { lower: -0.18, upper: 0.71 },
          significant_after_fdr: false,
          points: [],
        },
        {
          x_variable: 'pot_size',
          y_variable: 'health_trend',
          correlation: 0.18,
          p_value: 0.44,
          sample_size: 11,
          confidence_band: { lower: -0.34, upper: 0.61 },
          significant_after_fdr: false,
          points: [],
        },
      ],
    })
  },

  getSettings: async (): Promise<{ theme: string; pushover_user_key: string | null }> => {
    await wait()
    const s = localStorage.getItem('foliotrak-theme') || 'system'
    return { theme: s, pushover_user_key: USER.pushover_user_key }
  },

  updateSettings: async (data: { pushover_user_key?: string | null }): Promise<{ ok: boolean }> => {
    await wait()
    if (data.pushover_user_key !== undefined) {
      USER.pushover_user_key = data.pushover_user_key ?? null
    }
    return { ok: true }
  },
}
