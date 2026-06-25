import { Menu } from 'lucide-react'
import { useLocation, useNavigate } from 'react-router-dom'
import { cn } from '@/lib/utils'
import { NAV, type NavItem } from './nav'

export function TabBar() {
  const navigate = useNavigate()
  const { pathname } = useLocation()
  const items: NavItem[] = [...NAV.slice(0, 3), { to: '/settings', label: 'More', icon: Menu }]

  return (
    <nav className="grid shrink-0 grid-cols-4 border-t border-border bg-surface pb-[env(safe-area-inset-bottom)]">
      {items.map(item => {
        const active = item.to === '/' ? pathname === '/' : pathname.startsWith(item.to)
        const Icon = item.icon
        return (
          <button
            key={item.to}
            onClick={() => navigate(item.to)}
            className={cn(
              'flex min-h-[56px] flex-col items-center justify-center gap-0.5 py-2 text-[11px] font-medium',
              active ? 'text-primary' : 'text-text-subtle'
            )}
          >
            <Icon size={20} />
            {item.label}
          </button>
        )
      })}
    </nav>
  )
}
