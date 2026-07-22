import { ClipboardList, Droplets, FlaskConical, Shovel } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useCareLog } from '@/components/plant/care-log-context'

export function PlantLogActions() {
  const { openLog } = useCareLog()
  return (
    <div className="grid grid-cols-2 gap-2">
      <Button variant="outline" dusk="log-watering" onClick={() => openLog('watering')}>
        <Droplets size={16} className="text-info" />
        Water
      </Button>
      <Button variant="outline" dusk="log-fertilizing" onClick={() => openLog('fertilizing')}>
        <FlaskConical size={16} className="text-accent" />
        Fertilize
      </Button>
      <Button variant="outline" dusk="log-repotting" onClick={() => openLog('repotting')}>
        <Shovel size={16} style={{ color: 'var(--series-4)' }} />
        Repot
      </Button>
      <Button variant="outline" dusk="log-observation" onClick={() => openLog('observation')}>
        <ClipboardList size={16} className="text-primary" />
        Observe
      </Button>
    </div>
  )
}
