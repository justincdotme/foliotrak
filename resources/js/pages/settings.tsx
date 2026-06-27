import { useEffect, useRef, useState } from 'react'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { isAxiosError } from 'axios'
import { AlertTriangle, Bell, Check, LogOut, Sun, User as UserIcon } from 'lucide-react'
import type { ThemeChoice } from '@/hooks/useTheme'
import { useSettings, useUpdateSettings } from '@/hooks/useSettings'
import { useCurrentUser } from '@/hooks/useCurrentUser'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Card } from '@/components/ui/card'
import { SectionTitle } from '@/components/app/section-title'
import { Segmented } from '@/components/app/segmented'
import { Spinner } from '@/components/app/spinner'
import { initials } from '@/components/shell/nav'

interface SettingsPageProps {
  theme: ThemeChoice
  setTheme: (theme: ThemeChoice) => void
  onLogout: () => void
}

// A blank field clears the key; otherwise it must match Pushover's exact format,
// mirroring the server rule so a bad key is caught before the request.
const keySchema = z.object({
  pushover_user_key: z
    .string()
    .trim()
    .refine(value => value === '' || /^[A-Za-z0-9]{30}$/.test(value), {
      message: 'Enter your 30-character Pushover key, or leave it blank to clear.',
    }),
})

type KeyValues = z.infer<typeof keySchema>

function serverError(err: unknown): string {
  if (isAxiosError(err) && err.response?.status === 422) {
    const errors = err.response.data?.errors?.pushover_user_key
    if (Array.isArray(errors) && errors[0]) return errors[0]
  }
  return 'Could not save. Please try again.'
}

function PushoverKeyForm({
  initialKey,
  onSave,
}: {
  initialKey: string | null
  onSave: (key: string | null) => Promise<unknown>
}) {
  const [saved, setSaved] = useState(false)
  const flash = useRef<ReturnType<typeof setTimeout>>(undefined)
  useEffect(() => () => clearTimeout(flash.current), [])

  const {
    register,
    handleSubmit,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<KeyValues>({
    resolver: zodResolver(keySchema),
    defaultValues: { pushover_user_key: initialKey ?? '' },
  })

  const submit = async (values: KeyValues) => {
    const key = values.pushover_user_key.trim()
    try {
      await onSave(key === '' ? null : key)
      setSaved(true)
      clearTimeout(flash.current)
      flash.current = setTimeout(() => setSaved(false), 1600)
    } catch (err) {
      setError('pushover_user_key', { message: serverError(err) })
    }
  }

  const errorMessage = errors.pushover_user_key?.message

  return (
    <form onSubmit={handleSubmit(submit)}>
      <label htmlFor="pushover-key" className="mb-1.5 flex items-baseline gap-2">
        <span className="text-[13px] font-medium text-text">Pushover user key</span>
        <span className="text-[12px] text-text-subtle">for care reminders</span>
      </label>
      <div className="flex gap-2">
        <Input
          id="pushover-key"
          {...register('pushover_user_key')}
          placeholder="30-character key from Pushover"
          autoComplete="off"
          autoCapitalize="none"
          spellCheck={false}
          aria-invalid={errorMessage ? true : undefined}
          aria-describedby={errorMessage ? 'pushover-key-error' : undefined}
          className="flex-1 tnum"
        />
        <Button type="submit" disabled={isSubmitting} className="shrink-0">
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
      {errorMessage && (
        <div id="pushover-key-error" role="alert" className="mt-1 text-[12px] text-overdue">
          {errorMessage}
        </div>
      )}
      <p className="mt-2 text-[12px] text-text-subtle">
        Reminders are sent to your device when a plant is due, on the schedule each plant derives
        from its own history. Leave this blank to stop receiving them.
      </p>
    </form>
  )
}

export function SettingsPage({ theme, setTheme, onLogout }: SettingsPageProps) {
  const { data: settings, loading, error } = useSettings()
  const { user } = useCurrentUser()
  const update = useUpdateSettings()

  return (
    <div className="max-w-xl space-y-5">
      <h1 className="text-2xl font-semibold">Settings</h1>
      <Card className="p-4">
        <SectionTitle icon={Bell}>Notifications</SectionTitle>
        {loading ? (
          <div className="py-2">
            <Spinner />
          </div>
        ) : error || !settings ? (
          <div className="flex items-center gap-1.5 text-[13px] text-overdue">
            <AlertTriangle size={14} />
            Unable to load your reminder settings.
          </div>
        ) : (
          <PushoverKeyForm
            initialKey={settings.pushover_user_key}
            onSave={key => update.mutateAsync({ pushover_user_key: key })}
          />
        )}
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
        {user && (
          <div className="mb-3 flex items-center gap-3">
            <span className="grid h-11 w-11 place-items-center rounded-full bg-primary/15 font-semibold text-primary">
              {initials(user.name)}
            </span>
            <div>
              <div className="font-medium">{user.name}</div>
              <div className="tnum text-[12px] text-text-muted">{user.email}</div>
            </div>
          </div>
        )}
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
