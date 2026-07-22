import { Calendar } from 'lucide-react'
import { useState } from 'react'
import type { Plant, PlantRecommendations } from '@/api/types'
import { Card } from '@/components/ui/card'
import { SectionTitle } from '@/components/app/section-title'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { MyScheduleTab, type NextDue } from './my-schedule-tab'
import { RecommendedTab } from './recommended-tab'

export type { NextDue }

interface ScheduleSectionProps {
  plant: Plant
  recommendations: PlantRecommendations | null
  recommendationsLoading?: boolean
  recommendationsError?: boolean
  due: NextDue
}

export function ScheduleSection({
  plant,
  recommendations,
  recommendationsLoading = false,
  recommendationsError = false,
  due,
}: ScheduleSectionProps) {
  const [tab, setTab] = useState('mine')
  const locked = recommendations?.gate?.state === 'countdown'

  return (
    <Card className="p-4" dusk="schedule-section">
      <SectionTitle icon={Calendar}>Schedule</SectionTitle>
      <Tabs value={tab} onValueChange={setTab} className="w-full">
        <TabsList className="grid w-full grid-cols-2 mb-3">
          <TabsTrigger value="mine">My schedule</TabsTrigger>
          <TabsTrigger value="rec">
            Recommended
            {locked && (
              <span className="ml-1 text-[10px] px-1.5 py-0.5 rounded-full bg-border-strong text-text-subtle">
                soon
              </span>
            )}
          </TabsTrigger>
        </TabsList>

        <TabsContent value="mine" className="space-y-3">
          <MyScheduleTab plant={plant} due={due} />
        </TabsContent>

        <TabsContent value="rec" className="space-y-3">
          <RecommendedTab
            plant={plant}
            recommendations={recommendations}
            recommendationsLoading={recommendationsLoading}
            recommendationsError={recommendationsError}
            onAdopted={() => setTab('mine')}
          />
        </TabsContent>
      </Tabs>
    </Card>
  )
}
