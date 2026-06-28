import * as Dialog from '@radix-ui/react-dialog'
import { X } from 'lucide-react'
import type { ReactNode } from 'react'
import { cn } from '@/lib/utils'
import { useAppContext } from '@/components/app/app-context'

interface ModalProps {
  open: boolean
  onClose: () => void
  title: ReactNode
  subtitle?: ReactNode
  children: ReactNode
  footer?: ReactNode
  wide?: boolean
}

// Radix Dialog supplies the focus trap, escape handling, and scroll lock; the styling
// handles layout (centered on desktop, bottom sheet on mobile) to match the prototype.
export function Modal({ open, onClose, title, subtitle, children, footer, wide }: ModalProps) {
  const { mobile } = useAppContext()
  return (
    <Dialog.Root
      open={open}
      onOpenChange={next => {
        if (!next) onClose()
      }}
    >
      <Dialog.Portal>
        <Dialog.Overlay className="fixed inset-0 z-50 bg-black/45 data-[state=closed]:animate-out data-[state=open]:animate-in data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0" />
        <Dialog.Content
          aria-describedby={undefined}
          className={cn(
            'fixed z-50 flex flex-col border border-border bg-surface shadow-xl focus:outline-none',
            mobile
              ? 'inset-x-0 bottom-0 max-h-[92vh] w-full rounded-t-[16px] data-[state=closed]:animate-out data-[state=open]:animate-in data-[state=closed]:slide-out-to-bottom data-[state=open]:slide-in-from-bottom'
              : 'left-1/2 top-1/2 max-h-[88vh] -translate-x-1/2 -translate-y-1/2 rounded-card data-[state=closed]:animate-out data-[state=open]:animate-in data-[state=closed]:fade-out-0 data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95',
            wide ? 'w-[680px] max-w-[92vw]' : 'w-[460px] max-w-[92vw]'
          )}
        >
          <div
            className={cn(
              'relative flex shrink-0 items-start gap-3 border-b border-border p-4',
              mobile && 'pt-6'
            )}
          >
            {mobile && (
              <div className="absolute top-2 left-1/2 h-1 w-10 -translate-x-1/2 rounded-full bg-border-strong" />
            )}
            <div className="min-w-0">
              <Dialog.Title className="font-semibold text-text">{title}</Dialog.Title>
              {subtitle && (
                <Dialog.Description className="mt-0.5 text-[12px] text-text-muted">
                  {subtitle}
                </Dialog.Description>
              )}
            </div>
            <Dialog.Close
              aria-label="Close"
              className="ml-auto grid h-9 w-9 shrink-0 place-items-center rounded-md text-text-subtle hover:bg-surface-raised hover:text-text"
            >
              <X size={18} />
            </Dialog.Close>
          </div>
          <div className="overflow-y-auto p-4">{children}</div>
          {footer && (
            <div className="flex shrink-0 justify-end gap-2 border-t border-border p-4">
              {footer}
            </div>
          )}
        </Dialog.Content>
      </Dialog.Portal>
    </Dialog.Root>
  )
}
