import { useState } from 'react'
import { Bell, Check, LogOut, Sun, User as UserIcon } from 'lucide-react'
import type { ThemeChoice } from '@/hooks/useTheme'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card } from '@/components/ui/card'
import { SectionTitle } from '@/components/app/section-title'
import { Field } from '@/components/app/field'
import { Segmented } from '@/components/app/segmented'
import { mockApi, USER } from '@/api/mock'

interface SettingsPageProps {
  theme: ThemeChoice
  setTheme: (theme: ThemeChoice) => void
  onLogout: () => void
}

export function SettingsPage({ theme, setTheme, onLogout }: SettingsPageProps) {
  const [key, setKey] = useState(USER.pushover_user_key || '')
  const [saved, setSaved] = useState(false)

  const save = async () => {
    await mockApi.updateSettings({ pushover_user_key: key })
    setSaved(true)
    setTimeout(() => setSaved(false), 1600)
  }

  return (
    <div className="space-y-5 max-w-xl">
      <h1 className="text-2xl font-semibold">Settings</h1>
      <Card className="p-4">
        <SectionTitle icon={Bell}>Notifications</SectionTitle>
        <Field label="Pushover user key" hint="for watering reminders">
          <div className="flex gap-2">
            <Input
              value={key}
              onChange={e => setKey(e.target.value)}
              placeholder="u9kx2…"
              className="flex-1 tnum"
            />
            <Button onClick={save}>
              {saved ? (
                <>
                  <Check size={16} />
                  Saved
                </>
              ) : (
                'Save'
              )}
            </Button>
          </div>
        </Field>
        <p className="mt-2 text-[12px] text-text-subtle">
          Reminders are sent to your device when a plant is due, on your customizable schedule.
        </p>
      </Card>
      <Card className="p-4">
        <SectionTitle icon={Sun}>Appearance</SectionTitle>
        <Segmented
          value={theme}
          onChange={v => setTheme(v as ThemeChoice)}
          options={[
            { value: 'light', label: 'Light' },
            { value: 'dark', label: 'Dark' },
            { value: 'system', label: 'Follow system' },
          ]}
        />
      </Card>
      <Card className="p-4">
        <SectionTitle icon={UserIcon}>Account</SectionTitle>
        <div className="mb-3 flex items-center gap-3">
          <span className="grid h-11 w-11 place-items-center rounded-full bg-primary/15 font-semibold text-primary">
            {USER.name
              .split(' ')
              .map(s => s[0])
              .join('')}
          </span>
          <div>
            <div className="font-medium">{USER.name}</div>
            <div className="tnum text-[12px] text-text-muted">{USER.email}</div>
          </div>
        </div>
        <Button variant="outline" onClick={onLogout}>
          <LogOut size={16} />
          Log out
        </Button>
      </Card>
      <p className="text-center text-[11px] text-text-subtle">
        Foliotrak · self-hosted, LAN-first plant care
      </p>
    </div>
  )
}
