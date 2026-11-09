import { Command as CommandPrimitive } from 'cmdk'
import { Search } from 'lucide-react'
import type { ComponentProps } from 'react'
import { cn } from '@/lib/utils'

export function Command({ className, ref, ...props }: ComponentProps<typeof CommandPrimitive>) {
  return (
    <CommandPrimitive
      ref={ref}
      className={cn(
        'flex h-full w-full flex-col overflow-hidden rounded-md bg-surface-raised text-text',
        className
      )}
      {...props}
    />
  )
}
Command.displayName = CommandPrimitive.displayName

export function CommandInput({
  className,
  ref,
  ...props
}: ComponentProps<typeof CommandPrimitive.Input>) {
  return (
    <div className="flex items-center border-b border-border px-3">
      <Search size={16} className="mr-2 shrink-0 opacity-50" />
      <CommandPrimitive.Input
        ref={ref}
        className={cn(
          'flex h-11 w-full bg-transparent py-3 text-[13px] outline-none placeholder:text-text-muted disabled:cursor-not-allowed disabled:opacity-50',
          className
        )}
        {...props}
      />
    </div>
  )
}
CommandInput.displayName = CommandPrimitive.Input.displayName

export function CommandList({
  className,
  ref,
  ...props
}: ComponentProps<typeof CommandPrimitive.List>) {
  return (
    <CommandPrimitive.List
      ref={ref}
      className={cn('max-h-[300px] overflow-y-auto overflow-x-hidden', className)}
      {...props}
    />
  )
}
CommandList.displayName = CommandPrimitive.List.displayName

export function CommandEmpty({ ref, ...props }: ComponentProps<typeof CommandPrimitive.Empty>) {
  return (
    <CommandPrimitive.Empty
      ref={ref}
      className="py-6 text-center text-[13px] text-text-muted"
      {...props}
    />
  )
}
CommandEmpty.displayName = CommandPrimitive.Empty.displayName

export function CommandGroup({
  className,
  ref,
  ...props
}: ComponentProps<typeof CommandPrimitive.Group>) {
  return (
    <CommandPrimitive.Group
      ref={ref}
      className={cn('overflow-hidden p-1 text-text', className)}
      {...props}
    />
  )
}
CommandGroup.displayName = CommandPrimitive.Group.displayName

export function CommandItem({
  className,
  ref,
  ...props
}: ComponentProps<typeof CommandPrimitive.Item>) {
  return (
    <CommandPrimitive.Item
      ref={ref}
      className={cn(
        'relative flex cursor-pointer select-none items-center rounded-[6px] px-3 py-2 text-[13px] outline-none transition-colors',
        'data-[selected=true]:bg-surface data-[selected=true]:text-text',
        'data-[disabled=true]:pointer-events-none data-[disabled=true]:opacity-50',
        'aria-selected:bg-surface aria-selected:text-text',
        className
      )}
      {...props}
    />
  )
}
CommandItem.displayName = CommandPrimitive.Item.displayName

export function CommandSeparator({
  className,
  ref,
  ...props
}: ComponentProps<typeof CommandPrimitive.Separator>) {
  return (
    <CommandPrimitive.Separator
      ref={ref}
      className={cn('-mx-1 my-1 h-px bg-border', className)}
      {...props}
    />
  )
}
CommandSeparator.displayName = CommandPrimitive.Separator.displayName
