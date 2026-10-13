import type { Progress, Reward } from '@/api/types'

const PERCENT = 100

const DAY_FORMS: Record<Intl.LDMLPluralRule, string> = {
  zero: 'дней',
  one: 'день',
  two: 'дня',
  few: 'дня',
  many: 'дней',
  other: 'дня',
}

const russianPlural = new Intl.PluralRules('ru')

export function formatDays(days: number): string {
  return `${days} ${DAY_FORMS[russianPlural.select(days)]}`
}

// A typographic minus, so a taken back reward reads like the earned one
function signed(value: number): string {
  return value < 0 ? `−${Math.abs(value)}` : `+${value}`
}

export function formatReward(reward: Reward): string {
  return `${signed(reward.xp)} XP · ${signed(reward.gold)} золота`
}

// Share of the current level already earned, 0–100
export function xpPercent(progress: Progress): number {
  return Math.min(PERCENT, Math.floor((progress.xp_in_level / progress.xp_to_next_level) * PERCENT))
}
