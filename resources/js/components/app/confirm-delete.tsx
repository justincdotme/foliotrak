import { Trash2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Modal } from '@/components/app/modal'

interface ConfirmDeleteProps {
  open: boolean
  onClose: () => void
  onConfirm: () => void
  label?: string | React.ReactNode
}

export function ConfirmDelete({ open, onClose, onConfirm, label }: ConfirmDeleteProps) {
  return (
    <Modal
      open={open}
      onClose={onClose}
      title="Delete this entry?"
      subtitle="This cannot be undone."
      footer={
        <>
          <Button variant="ghost" onClick={onClose}>
            Cancel
          </Button>
          <Button
            variant="danger"
            onClick={() => {
              onConfirm()
              onClose()
            }}
          >
            <Trash2 size={16} />
            Delete
          </Button>
        </>
      }
    >
      <p className="text-sm text-text-muted">
        {label || "The entry will be permanently removed from this plant's history."}
      </p>
    </Modal>
  )
}
