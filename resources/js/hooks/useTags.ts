import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import { listTags, createTag, updateTag, deleteTag } from '@/api/client'
import type { Tag } from '@/api/types'

export function useTags() {
  const query = useQuery({ queryKey: ['tags'], queryFn: listTags })
  return { data: query.data ?? null, loading: query.isPending, error: query.error }
}

export function useCreateTag() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (name: string) => createTag(name),
    onSuccess: (created: Tag) => {
      qc.setQueryData<Tag[]>(['tags'], old => [...(old ?? []), created])
    },
  })
}

export function useUpdateTag() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, payload }: { id: number; payload: { name?: string } }) =>
      updateTag(id, payload),
    onSuccess: (updated: Tag) => {
      qc.setQueryData<Tag[]>(['tags'], old =>
        (old ?? []).map(t => (t.id === updated.id ? updated : t))
      )
    },
  })
}

export function useDeleteTag() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => deleteTag(id),
    onSuccess: (_data, id) => {
      qc.setQueryData<Tag[]>(['tags'], old => (old ?? []).filter(t => t.id !== id))
    },
  })
}
