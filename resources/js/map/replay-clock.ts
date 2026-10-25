// E5.2: the time of the recorded day that a replay shows moves on with the page, a minute at a time. It is read when
// wanted, never announced: a live region ticking every minute would interrupt screen readers for nothing.
export function followReplayClock(clock: HTMLTimeElement, timeZone: string): void {
    const shown = Date.parse(clock.dateTime);
    const opened = Date.now();
    const format = new Intl.DateTimeFormat('fr-FR', { hour: '2-digit', minute: '2-digit', timeZone });

    if (Number.isNaN(shown)) {
        return;
    }

    window.setInterval(() => {
        const at = new Date(shown + Date.now() - opened);
        clock.dateTime = at.toISOString();
        clock.textContent = format.format(at);
    }, 30_000);
}
