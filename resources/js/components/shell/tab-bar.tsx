import { Menu } from 'lucide-react'
import { NavLink } from 'react-router-dom'
import { cn } from '@/lib/utils'
import { NAV, type NavItem } from './nav'

export function TabBar() {
  const items: NavItem[] = [...NAV.slice(0, 3), { to: '/settings', label: 'More', icon: Menu }]

  return (
    <nav
      dusk="tab-bar"
      className="grid shrink-0 grid-cols-4 border-t border-border bg-surface pb-[env(safe-area-inset-bottom)]"
    >
      {items.map(item => {
        const Icon = item.icon
        return (
          <NavLink
            key={item.to}
            to={item.to}
            end={item.to === '/'}
            dusk={`tab-${item.label.toLowerCase()}`}
            className={({ isActive }) =>
              cn(
                'flex min-h-[56px] flex-col items-center justify-center gap-0.5 py-2 text-[11px] font-medium',
                isActive ? 'text-primary' : 'text-text-subtle'
              )
            }
          >
            <Icon size={20} />
            {item.label}
          </NavLink>
        )
      })}
    </nav>
  )
}
