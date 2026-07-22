import { BarChart3, Home, Settings, Sprout } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'

export interface NavItem {
  to: string
  label: string
  icon: LucideIcon
}

export const NAV: NavItem[] = [
  { to: '/', label: 'Dashboard', icon: Home },
  { to: '/plants', label: 'Plants', icon: Sprout },
  { to: '/insights', label: 'Insights', icon: BarChart3 },
  { to: '/settings', label: 'Settings', icon: Settings },
]

export const initials = (name: string) =>
  name
    .split(' ')
    .map(part => part.charAt(0))
    .join('')
