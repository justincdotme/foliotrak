import { Leaf, LogOut, Moon, Plus, Settings, Sun } from 'lucide-react'
import { useLocation, useNavigate } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { IconButton } from '@/components/app/icon-button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useTheme } from '@/hooks/useTheme'
import { useCurrentUser } from '@/hooks/useCurrentUser'
import { cn } from '@/lib/utils'
import { initials, NAV } from './nav'

interface TopBarProps {
  onAdd: () => void
  onLogout: () => void
}

export function TopBar({ onAdd, onLogout }: TopBarProps) {
  const navigate = useNavigate()
  const { pathname } = useLocation()
  const { isDark, toggle } = useTheme()
  const { user } = useCurrentUser()

  return (
    <header className="sticky top-0 z-30 border-b border-border bg-surface/90 backdrop-blur">
      <div className="mx-auto flex h-16 max-w-[1200px] items-center gap-2 px-5">
        <button onClick={() => navigate('/')} className="mr-4 flex items-center gap-2">
          <span className="grid h-8 w-8 place-items-center rounded-md bg-primary text-white">
            <Leaf size={18} />
          </span>
          <span className="text-[15px] font-semibold">Foliotrak</span>
        </button>
        <nav className="flex items-center gap-1">
          {NAV.slice(0, 3).map(item => {
            const active = item.to === '/' ? pathname === '/' : pathname.startsWith(item.to)
            const Icon = item.icon
            return (
              <button
                key={item.to}
                onClick={() => navigate(item.to)}
                className={cn(
                  'flex h-9 items-center gap-1.5 rounded-md px-3 text-[13px] font-medium',
                  active ? 'bg-surface-raised text-text' : 'text-text-muted hover:text-text'
                )}
              >
                <Icon size={15} />
                {item.label}
              </button>
            )
          })}
        </nav>
        <div className="ml-auto flex items-center gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={onAdd}
            className="border-accent/40 text-accent hover:bg-accent/10"
          >
            <Plus size={16} />
            Add plant
          </Button>
          <IconButton label="Toggle theme" onClick={toggle} className="h-9 w-9">
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
            <DropdownMenuContent className="w-44 p-1">
              <div className="border-b border-border px-3 py-2">
                <div className="text-[13px] font-medium">{user?.name}</div>
                <div className="tnum text-[11px] text-text-subtle">{user?.email}</div>
              </div>
              <DropdownMenuItem onSelect={() => navigate('/settings')}>
                <Settings size={15} />
                Settings
              </DropdownMenuItem>
              <DropdownMenuItem dusk="logout-button" onSelect={onLogout} className="text-overdue">
                <LogOut size={15} />
                Log out
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </div>
    </header>
  )
}
