import { LogOut, Settings } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { DropdownMenuContent, DropdownMenuItem } from '@/components/ui/dropdown-menu'
import { useCurrentUser } from '@/hooks/useCurrentUser'
import { initials } from './nav'

export function AccountMenu({ onLogout }: { onLogout: () => void }) {
  const navigate = useNavigate()
  const { user } = useCurrentUser()

  return (
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
      <DropdownMenuItem dusk="settings-link" onSelect={() => navigate('/settings')}>
        <Settings size={15} />
        Settings
      </DropdownMenuItem>
      <DropdownMenuItem dusk="logout-button" onSelect={onLogout} className="text-overdue">
        <LogOut size={15} />
        Log out
      </DropdownMenuItem>
    </DropdownMenuContent>
  )
}
