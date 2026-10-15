import type { HeatmapDay } from '@/api/types'

const DAYS_IN_WEEK = 7

// Upper bounds of the first four shades: 0, 1, 2–3, 4–5; anything above is the brightest
const SHADE_LIMITS = [0, 1, 3, 5] as const

export type Shade = 0 | 1 | 2 | 3 | 4

const TASK_FORMS: Record<Intl.LDMLPluralRule, string> = {
  zero: 'задач',
  one: 'задача',
  two: 'задачи',
  few: 'задачи',
  many: 'задач',
  other: 'задачи',
}

const russianPlural = new Intl.PluralRules('ru')

// The dates are calendar days of the user, not moments: formatted in UTC so they never move a day
const dayFormat = new Intl.DateTimeFormat('ru', { day: 'numeric', month: 'short', timeZone: 'UTC' })

export function shadeOf(count: number): Shade {
  const index = SHADE_LIMITS.findIndex((limit) => count <= limit)

  return (index === -1 ? SHADE_LIMITS.length : index) as Shade
}

// Columns of Monday-first weeks; the server starts on a Monday, so the days after today
// in the last week are the only gaps
export function toWeeks(days: readonly HeatmapDay[]): (HeatmapDay | null)[][] {
  const weeks: (HeatmapDay | null)[][] = []

  for (let start = 0; start < days.length; start += DAYS_IN_WEEK) {
    const week: (HeatmapDay | null)[] = days.slice(start, start + DAYS_IN_WEEK)
    weeks.push([...week, ...Array<null>(DAYS_IN_WEEK - week.length).fill(null)])
  }

  return weeks
}

export function formatTasks(count: number): string {
  return `${count} ${TASK_FORMS[russianPlural.select(count)]}`
}

// '15 окт: 3 задачи'
export function formatDayLabel(day: HeatmapDay): string {
  const date = dayFormat.format(new Date(`${day.date}T00:00:00Z`)).replace('.', '')

  return `${date}: ${formatTasks(day.count)}`
}
