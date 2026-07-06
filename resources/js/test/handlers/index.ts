import { http, HttpResponse } from 'msw'
import { setupServer } from 'msw/node'
import userFixture from '../fixtures/user.json'
import plantsList from '../fixtures/plants/list.json'
import plantDetail from '../fixtures/plants/detail-1.json'
import plantTimeline from '../fixtures/plant/timeline-1.json'
import plantRecommendations from '../fixtures/plant/recommendations-1.json'
import plantPhotos from '../fixtures/plant/photos-1.json'
import dashboardPopulated from '../fixtures/dashboard/populated.json'
import insightsGroup from '../fixtures/insights/group.json'
import insightsLocations from '../fixtures/insights/locations.json'
import careEventTypes from '../fixtures/lookups/care-event-types.json'
import fertilizerForms from '../fixtures/lookups/fertilizer-forms.json'
import nutrients from '../fixtures/lookups/nutrients.json'
import symptoms from '../fixtures/lookups/symptoms.json'
import equipment from '../fixtures/lookups/equipment.json'
import locations from '../fixtures/lookups/locations.json'
import tags from '../fixtures/lookups/tags.json'
import settings from '../fixtures/settings.json'
import speciesSuggest from '../fixtures/species/suggest.json'
import loginSuccess from '../fixtures/auth/login-200.json'
import loginFailure from '../fixtures/auth/login-401.json'
import logoutSuccess from '../fixtures/auth/logout-200.json'
import plantCreated from '../fixtures/plants/created-201.json'
import photoCreated from '../fixtures/photos/created-201.json'
import wateringCreated from '../fixtures/care-events/watering.json'
import fertilizingCreated from '../fixtures/care-events/fertilizing.json'
import repottingCreated from '../fixtures/care-events/repotting.json'
import observationCreated from '../fixtures/care-events/observation.json'
import careEventUpdated from '../fixtures/care-events/updated.json'
import locationCreated from '../fixtures/locations/created-201.json'
import tagCreated from '../fixtures/tags/created-201.json'
import tagUpdated from '../fixtures/tags/updated.json'
import equipmentCreated from '../fixtures/equipment/created-201.json'
import equipmentUpdated from '../fixtures/equipment/updated.json'
import settingsUpdated from '../fixtures/settings/updated.json'
import sensorsList from '../fixtures/sensors/list.json'
import sensorReadingsWeek from '../fixtures/sensors/readings-week.json'

export const handlers = [
  http.get('/sanctum/csrf-cookie', () => new HttpResponse(null, { status: 204 })),
  http.get('/api/user', () => HttpResponse.json(userFixture)),
  http.get('/api/plants', () => HttpResponse.json(plantsList)),
  http.get('/api/plants/:id', () => HttpResponse.json(plantDetail)),
  http.get('/api/plants/:id/timeline', () => HttpResponse.json(plantTimeline)),
  http.get('/api/plants/:id/recommendations', () => HttpResponse.json(plantRecommendations)),
  http.get('/api/plants/:id/photos', () => HttpResponse.json(plantPhotos)),
  http.get('/api/dashboard', () => HttpResponse.json(dashboardPopulated)),
  http.get('/api/insights/group', () => HttpResponse.json(insightsGroup)),
  http.get('/api/insights/locations', () => HttpResponse.json(insightsLocations)),
  http.get('/api/care-event-types', () => HttpResponse.json(careEventTypes)),
  http.get('/api/fertilizer-forms', () => HttpResponse.json(fertilizerForms)),
  http.get('/api/nutrients', () => HttpResponse.json(nutrients)),
  http.get('/api/symptoms', () => HttpResponse.json(symptoms)),
  http.get('/api/equipment', () => HttpResponse.json(equipment)),
  http.get('/api/locations', () => HttpResponse.json(locations)),
  http.get('/api/tags', () => HttpResponse.json(tags)),
  http.get('/api/settings', () => HttpResponse.json(settings)),
  http.get('/api/species/suggest', () => HttpResponse.json(speciesSuggest)),
  http.get('/api/sensors', () => HttpResponse.json(sensorsList)),
  http.get('/api/plants/:id/sensor-readings', () => HttpResponse.json(sensorReadingsWeek)),
  http.get('/api/plants/:id/sensor-snapshot', () => new HttpResponse(null, { status: 204 })),

  // auth
  http.post('/login', async ({ request }) => {
    const body = (await request.json()) as { email?: string }
    return body.email === 'wrong@example.com'
      ? HttpResponse.json(loginFailure, { status: 401 })
      : HttpResponse.json(loginSuccess, { status: 200 })
  }),
  http.post('/logout', () => HttpResponse.json(logoutSuccess, { status: 200 })),

  // plants
  http.post('/api/plants', () => HttpResponse.json(plantCreated, { status: 201 })),
  http.patch('/api/plants/:id', () => HttpResponse.json(plantDetail, { status: 200 })),
  http.delete('/api/plants/:id', () => new HttpResponse(null, { status: 204 })),

  // photos
  http.post('/api/plants/:id/photos', () => HttpResponse.json(photoCreated, { status: 201 })),
  http.delete('/api/photos/:id', () => new HttpResponse(null, { status: 204 })),

  // care events
  http.post('/api/plants/:id/care-events', async ({ request }) => {
    const body = (await request.json()) as { type: string }
    const fixtures: Record<string, unknown> = {
      watering: wateringCreated,
      fertilizing: fertilizingCreated,
      repotting: repottingCreated,
      observation: observationCreated,
    }
    return HttpResponse.json({ data: fixtures[body.type] }, { status: 201 })
  }),
  http.patch('/api/care-events/:id', () => HttpResponse.json(careEventUpdated, { status: 200 })),
  http.delete('/api/care-events/:id', () => new HttpResponse(null, { status: 204 })),

  // locations
  http.post('/api/locations', () => HttpResponse.json(locationCreated, { status: 201 })),

  // tags
  http.post('/api/tags', () => HttpResponse.json(tagCreated, { status: 201 })),
  http.patch('/api/tags/:id', () => HttpResponse.json(tagUpdated, { status: 200 })),
  http.delete('/api/tags/:id', () => new HttpResponse(null, { status: 204 })),

  // equipment
  http.post('/api/equipment', () => HttpResponse.json(equipmentCreated, { status: 201 })),
  http.patch('/api/equipment/:id', () => HttpResponse.json(equipmentUpdated, { status: 200 })),
  http.delete('/api/equipment/:id', () => new HttpResponse(null, { status: 204 })),

  // settings
  http.patch('/api/settings', () => HttpResponse.json(settingsUpdated, { status: 200 })),
]

export const server = setupServer(...handlers)
