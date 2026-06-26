import { ChevronDown, Leaf, LogOut, Moon, Plus, Sun, User as UserIcon } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { useTheme } from '@/hooks/useTheme'
import { useCurrentUser } from '@/hooks/useCurrentUser'
import { initials } from './nav'

interface MobileHeaderProps {
  onAdd: () => void
  onLogout: () => void
}

export function MobileHeader({ onAdd, onLogout }: MobileHeaderProps) {
  const navigate = useNavigate()
  const { isDark, toggle } = useTheme()
  const { user } = useCurrentUser()

  return (
    <header className="sticky top-0 z-30 flex h-14 items-center gap-2 border-b border-border bg-surface/90 px-4 backdrop-blur">
      <button onClick={() => navigate('/')} className="flex items-center gap-2">
        <span className="grid h-7 w-7 place-items-center rounded-[7px] bg-primary text-white">
          <Leaf size={16} />
        </span>
        <span className="font-semibold">Foliotrak</span>
      </button>
      <div className="ml-auto flex items-center gap-1.5">
        <button
          onClick={onAdd}
          aria-label="Add plant"
          title="Add plant"
          className="inline-flex h-9 items-center gap-1 rounded-md border border-accent/40 px-2.5 text-[13px] font-medium text-accent hover:bg-accent/10"
        >
          <Plus size={16} />
          Plant
        </button>
        <button
          onClick={toggle}
          aria-label="Toggle theme"
          className="grid h-9 w-9 place-items-center rounded-md text-text-muted hover:bg-surface-raised"
        >
          {isDark ? <Sun size={17} /> : <Moon size={17} />}
        </button>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <button
              aria-label="Account"
              className="inline-flex h-9 items-center gap-1 rounded-full border border-border bg-surface-raised pl-0.5 pr-1.5"
            >
              <span className="grid h-7 w-7 place-items-center rounded-full bg-primary/15 text-[12px] font-semibold text-primary">
                {initials(user?.name ?? '')}
              </span>
              <ChevronDown size={14} className="text-text-subtle" />
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuContent className="w-48 p-1">
            <div className="flex items-center gap-2 border-b border-border px-3 py-2">
              <span className="grid h-8 w-8 place-items-center rounded-full bg-primary/15 text-[12px] font-semibold text-primary">
                {initials(user?.name ?? '')}
              </span>
              <div className="min-w-0">
                <div className="truncate text-[13px] font-medium">{user?.name}</div>
                <div className="tnum truncate text-[11px] text-text-subtle">{user?.email}</div>
              </div>
            </div>
            <DropdownMenuItem onSelect={() => navigate('/settings')}>
              <UserIcon size={15} />
              Account &amp; settings
            </DropdownMenuItem>
            <DropdownMenuItem onSelect={onLogout} className="text-overdue">
              <LogOut size={15} />
              Log out
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </div>
    </header>
  )
}
