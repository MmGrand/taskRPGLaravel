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

export const REWARD_LIMIT_MESSAGE = 'Награды на сегодня закончились — задача всё равно засчитана.'

// A part that did not change is left out: spent gold cannot be taken back, and "+0" would read as a gain
export function formatReward(reward: Reward): string {
  const parts = [
    reward.xp === 0 ? '' : `${signed(reward.xp)} XP`,
    reward.gold === 0 ? '' : `${signed(reward.gold)} золота`,
  ]

  return parts.filter((part) => part !== '').join(' · ')
}

// What the status line says; undoing a mark past the limit changed nothing, so formatReward gives ''
export function describeReward(reward: Reward): string {
  return reward.capped ? REWARD_LIMIT_MESSAGE : formatReward(reward)
}

export function isEmptyReward(reward: Reward): boolean {
  return reward.xp === 0 && reward.gold === 0
}

// Share of the current level already earned, 0–100
export function xpPercent(progress: Progress): number {
  return Math.min(PERCENT, Math.floor((progress.xp_in_level / progress.xp_to_next_level) * PERCENT))
}
