import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import { zodResolver } from '@hookform/resolvers/zod'
import { isAxiosError } from 'axios'
import { AlertTriangle, Leaf } from 'lucide-react'
import api from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Field } from '@/components/app/field'

const loginSchema = z.object({
  email: z.string().email('Enter a valid email'),
  password: z.string().min(1, 'Enter your password'),
})

type LoginValues = z.infer<typeof loginSchema>

export function LoginPage() {
  const navigate = useNavigate()
  const [authError, setAuthError] = useState('')

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<LoginValues>({ resolver: zodResolver(loginSchema) })

  const onSubmit = async (values: LoginValues) => {
    setAuthError('')
    try {
      await api.get('/sanctum/csrf-cookie')
      await api.post('/login', values)
      navigate('/')
    } catch (err) {
      setAuthError(
        isAxiosError(err) && err.response?.status === 422
          ? 'Incorrect email or password.'
          : 'Something went wrong. Please try again.'
      )
    }
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
            {authError && (
              <div dusk="auth-error" className="flex items-center gap-1.5 text-[13px] text-overdue">
                <AlertTriangle size={14} />
                {authError}
              </div>
            )}
            <Button type="submit" className="w-full" disabled={isSubmitting}>
              Sign in
            </Button>
          </form>
        </Card>
        <p className="mt-4 text-center text-[12px] text-text-subtle">
          Self-hosted on your network.
        </p>
      </div>
    </div>
  )
}
