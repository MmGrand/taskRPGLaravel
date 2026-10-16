// Structural on purpose: game/ does not depend on api/ types
interface Progress {
  current: number
  target: number
}

interface Earned {
  title: string
  reward_item: { name: string } | null
}

const PERCENT = 100

// Share of the way done, 0–100; nothing to reach counts as done
export function progressPercent({ current, target }: Progress): number {
  return target <= 0 ? PERCENT : Math.min(PERCENT, Math.floor((current / target) * PERCENT))
}

// "12 / 30"
export function formatProgress({ current, target }: Progress): string {
  return `${current} / ${target}`
}

const earnedDate = new Intl.DateTimeFormat('ru-RU', {
  day: '2-digit',
  month: '2-digit',
  year: 'numeric',
})

// "16.10.2026" on the calendar of the browser
export function formatEarnedDate(iso: string): string {
  return earnedDate.format(new Date(iso))
}

// "Достижение: Неделя без пропусков! Получен предмет: Штаны паладина."
export function formatEarned(achievements: readonly Earned[]): string {
  return achievements
    .map(({ title, reward_item: item }) =>
      item === null
        ? `Достижение: ${title}!`
        : `Достижение: ${title}! Получен предмет: ${item.name}.`,
    )
    .join(' ')
}

export function earnedCount(achievements: readonly { earned_at: string | null }[]): number {
  return achievements.filter((achievement) => achievement.earned_at !== null).length
}
