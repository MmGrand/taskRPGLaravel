import type { TaskDifficulty, Weekday } from '@/api/types'

export const EVERY_DAY: readonly Weekday[] = [1, 2, 3, 4, 5, 6, 7]

export const DIFFICULTY_OPTIONS: readonly { value: TaskDifficulty; label: string }[] = [
  { value: 'easy', label: 'Лёгкая' },
  { value: 'medium', label: 'Средняя' },
  { value: 'hard', label: 'Тяжёлая' },
]

export const WEEKDAYS: readonly { day: Weekday; short: string; full: string }[] = [
  { day: 1, short: 'Пн', full: 'Понедельник' },
  { day: 2, short: 'Вт', full: 'Вторник' },
  { day: 3, short: 'Ср', full: 'Среда' },
  { day: 4, short: 'Чт', full: 'Четверг' },
  { day: 5, short: 'Пт', full: 'Пятница' },
  { day: 6, short: 'Сб', full: 'Суббота' },
  { day: 7, short: 'Вс', full: 'Воскресенье' },
]

// '' for every day: the row then shows no schedule at all
export function formatWeekdays(days: readonly Weekday[]): string {
  if (days.length === EVERY_DAY.length) {
    return ''
  }

  return WEEKDAYS.filter(({ day }) => days.includes(day))
    .map(({ short }) => short.toLowerCase())
    .join(', ')
}

// A medium task gives the usual reward, so only the others are marked in the list
export function difficultyLabel(difficulty: TaskDifficulty): string {
  return difficulty === 'medium'
    ? ''
    : (DIFFICULTY_OPTIONS.find((option) => option.value === difficulty)?.label.toLowerCase() ?? '')
}
