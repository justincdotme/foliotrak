export const axis = {
  stroke: 'var(--border-strong)',
  tick: { fontSize: 11, fill: 'var(--text-subtle)' },
  tickLine: false,
}

export function prettyVar(v: string): string {
  return (
    (
      {
        watering_frequency: 'Watering frequency',
        light_level: 'Light level',
        fertilizer_npk_n: 'Fertilizer N',
        pot_size: 'Pot size',
        health_trend: 'Health',
        growth_rate: 'Growth',
      } as Record<string, string>
    )[v] || v.replace(/_/g, ' ')
  )
}
