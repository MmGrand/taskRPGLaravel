const ALWAYS_OFFERED = 'UTC'

function supportedTimezones(): string[] {
  return typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : []
}

// IANA zones the browser knows, plus the ones it may leave out: UTC and the user's own,
// which can be an older name such as Asia/Calcutta
export function timezoneOptions(
  current: string,
  known: readonly string[] = supportedTimezones(),
): string[] {
  return [...new Set([...known, ALWAYS_OFFERED, current])].sort((a, b) => a.localeCompare(b))
}

// Daily tasks reset at the user's local midnight, so the browser zone is the default
export function browserTimezone(): string {
  return Intl.DateTimeFormat().resolvedOptions().timeZone
}

// "Europe/Moscow (GMT+3)"
export function timezoneLabel(timezone: string, at: Date = new Date()): string {
  const offset = new Intl.DateTimeFormat('en-US', {
    timeZone: timezone,
    timeZoneName: 'shortOffset',
  })
    .formatToParts(at)
    .find((part) => part.type === 'timeZoneName')?.value

  return offset === undefined ? timezone : `${timezone} (${offset})`
}

// Wall-clock time in the zone, "14:05"
export function localTime(timezone: string, at: Date = new Date()): string {
  return new Intl.DateTimeFormat('ru-RU', {
    timeZone: timezone,
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).format(at)
}
