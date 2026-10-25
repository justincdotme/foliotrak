import { useState } from 'react'
import { Navigate, useNavigate } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { useForm, Controller } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { isAxiosError } from 'axios'
import { AlertTriangle, Leaf } from 'lucide-react'
import api from '@/lib/api'
import { getUser } from '@/api/client'
import { Spinner } from '@/components/app/spinner'
import { TooltipButton } from '@/components/ui/tooltip-button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Field } from '@/components/app/field'
import { Switch } from '@/components/ui/switch'
import { Label } from '@/components/ui/label'

const loginSchema = z.object({
  email: z.string().email('Enter a valid email'),
  password: z.string().min(1, 'Enter your password'),
  remember: z.boolean().default(false),
})

type LoginValues = z.infer<typeof loginSchema>

export function LoginPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [authError, setAuthError] = useState('')

  // A bookmark, home-screen icon, or address-bar autocomplete lands signed-in
  // users here too; a form they cannot tell apart from being signed out is not
  // an answer, so the session is checked first and they are sent on.
  const session = useQuery({ queryKey: ['auth-user'], queryFn: getUser, retry: false })

  const {
    register,
    handleSubmit,
    control,
    formState: { errors, isSubmitting },
  } = useForm<LoginValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { remember: false },
  })

  const onSubmit = async (values: LoginValues) => {
    setAuthError('')
    try {
      await api.get('/sanctum/csrf-cookie')
      await api.post('/login', values)
      // The session check above cached a 401 the gate would act on before refetching.
      queryClient.removeQueries({ queryKey: ['auth-user'] })
      navigate('/')
    } catch (err) {
      setAuthError(
        isAxiosError(err) && err.response?.status === 401
          ? 'Incorrect email or password.'
          : 'Something went wrong. Please try again.'
      )
    }
  }

  if (session.data) {
    return <Navigate to="/" replace />
  }

  if (session.isPending) {
    return (
      <div className="grid min-h-screen place-items-center bg-bg p-6">
        <Spinner />
      </div>
    )
  }

  return (
    <div className="grid min-h-screen place-items-center bg-bg p-6">
      <div className="w-full max-w-sm">
        <div className="mb-6 flex items-center justify-center gap-2.5">
          <span className="grid h-10 w-10 place-items-center rounded-[10px] bg-primary text-white">
            <Leaf size={22} />
          </span>
          <span className="text-2xl font-semibold">Foliotrak</span>
        </div>
        <Card className="p-6">
          <h1 className="mb-1 text-lg font-semibold">Welcome back</h1>
          <p className="mb-5 text-[13px] text-text-muted">Sign in to your plant log.</p>
          <form dusk="login-form" onSubmit={handleSubmit(onSubmit)} className="space-y-4">
            <Field label="Email" error={errors.email?.message}>
              <Input type="email" autoComplete="username" {...register('email')} />
            </Field>
            <Field label="Password" error={errors.password?.message}>
              <Input type="password" autoComplete="current-password" {...register('password')} />
            </Field>
            <div className="flex items-center gap-2">
              <Controller
                name="remember"
                control={control}
                render={({ field }) => (
                  <Switch
                    id="remember"
                    checked={field.value}
                    onCheckedChange={field.onChange}
                    aria-label="Remember me"
                  />
                )}
              />
              <Label htmlFor="remember" className="text-[13px]">
                Remember me
              </Label>
            </div>
            {authError && (
              <div dusk="auth-error" className="flex items-center gap-1.5 text-[13px] text-overdue">
                <AlertTriangle size={14} />
                {authError}
              </div>
            )}
            <TooltipButton
              type="submit"
              className="w-full"
              disabled={isSubmitting}
              tooltipContent={isSubmitting ? 'Saving...' : undefined}
            >
              Sign in
            </TooltipButton>
          </form>
        </Card>
        <p className="mt-4 text-center text-[12px] text-text-subtle">
          Self-hosted on your network.
        </p>
      </div>
    </div>
  )
}
