import { AlertTriangle } from 'lucide-react'

interface FormErrorProps {
  message: string | null
}

export function FormError({ message }: FormErrorProps) {
  if (!message) return null
  return (
    <div role="alert" className="flex items-center gap-1.5 text-[12px] text-overdue">
      <AlertTriangle size={14} />
      {message}
    </div>
  )
}
