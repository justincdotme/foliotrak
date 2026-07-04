import { Leaf, Moon, Plus, Sun } from 'lucide-react'
import { NavLink, useNavigate } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { IconButton } from '@/components/app/icon-button'
import { DropdownMenu, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'
import { useTheme } from '@/hooks/useTheme'
import { useCurrentUser } from '@/hooks/useCurrentUser'
import { cn } from '@/lib/utils'
import { AccountMenu } from './account-menu'
import { initials, NAV } from './nav'

interface TopBarProps {
  onAdd: () => void
  onLogout: () => void
}

export function TopBar({ onAdd, onLogout }: TopBarProps) {
  const navigate = useNavigate()
  const { isDark, toggle } = useTheme()
  const { user } = useCurrentUser()

  return (
    <header
      dusk="top-bar"
      className="sticky top-0 z-30 border-b border-border bg-surface/90 backdrop-blur"
    >
      <div className="mx-auto flex h-16 max-w-[1200px] items-center gap-2 px-5">
        <button
          dusk="logo-link"
          onClick={() => navigate('/')}
          className="mr-4 flex items-center gap-2"
        >
          <span className="grid h-8 w-8 place-items-center rounded-md bg-primary text-white">
            <Leaf size={18} />
          </span>
          <span className="text-[15px] font-semibold">Foliotrak</span>
        </button>
        <nav className="flex items-center gap-1">
          {NAV.slice(0, 3).map(item => {
            const Icon = item.icon
            return (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.to === '/'}
                dusk={`nav-${item.label.toLowerCase()}`}
                className={({ isActive }) =>
                  cn(
                    'flex h-9 items-center gap-1.5 rounded-md px-3 text-[13px] font-medium',
                    isActive ? 'bg-surface-raised text-text' : 'text-text-muted hover:text-text'
                  )
                }
              >
                <Icon size={15} />
                {item.label}
              </NavLink>
            )
          })}
        </nav>
        <div className="ml-auto flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={onAdd}
            dusk="add-plant"
            className="border-accent/40 text-accent hover:bg-accent/10"
          >
            <Plus size={16} />
            Add plant
          </Button>
          <IconButton dusk="theme-toggle" label="Toggle theme" onClick={toggle} className="h-9 w-9">
            {isDark ? <Sun size={17} /> : <Moon size={17} />}
          </IconButton>
          <DropdownMenu>
            <DropdownMenuTrigger asChild>
              <button
                dusk="user-menu"
                className="grid h-9 w-9 place-items-center rounded-full bg-primary/15 text-[13px] font-semibold text-primary"
              >
                {initials(user?.name ?? '')}
              </button>
            </DropdownMenuTrigger>
            <AccountMenu onLogout={onLogout} />
          </DropdownMenu>
        </div>
      </div>
    </header>
  )
}
