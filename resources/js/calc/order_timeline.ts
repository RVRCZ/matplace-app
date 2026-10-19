/**
 * The "Print" line of the order page's print / dispatch / delivery block (docs/AH.md §9.3), as the order stands now:
 * before payment and in the queue the time on the printer, while printing what is left (and which plate), printed,
 * sent. Pure: the order page (farm.ts) hands in the state and its words; scripts can check it without a browser.
 */
export interface TimelineState {
    status: string;
    minutes: number | null;
    plates: number;
    plates_done: number;
    plate_now: number | null;
    delivery: string;
    queue: { finish_in: number } | null;
    print: { progress: number } | null;
}

type Tr = (key: string, params?: Record<string, string | number>) => string;

/** null = the block has nothing to say (the order was cancelled or failed). */
export function timelinePrint(s: TimelineState, tr: Tr, duration: (min: number) => string): string | null {
    if (s.status === 'cancelled' || s.status === 'failed') return null;
    if (s.status === 'done' || s.status === 'handed_over') return tr('farm.order.tl.done');
    if (s.status === 'printing') {
        // what is left: the farm's estimate of the end, else the part of the print time the progress has not covered
        const left = s.queue ? s.queue.finish_in : s.minutes !== null && s.print ? Math.round(s.minutes * (1 - Math.min(100, Math.max(0, s.print.progress)) / 100)) : null;
        const plate = s.plates > 1 ? ` · ${tr('farm.copies.plate_of', { i: s.plate_now ?? Math.min(s.plates, s.plates_done + 1), p: s.plates })}` : '';
        const text = left === null ? tr('farm.order.tl.printing_now') : left < 1 ? tr('farm.order.tl.printing_end') : tr('farm.order.tl.printing', { time: duration(left) });
        return text + plate;
    }
    return s.minutes === null ? null : tr('farm.order.tl.print', { time: duration(s.minutes) });
}

/** The "Dispatch" line once the parcel has left; null = the rule (on the day the print ends, working days). */
export function timelineDispatch(s: TimelineState, tr: Tr): string | null {
    return s.status === 'handed_over' && s.delivery !== 'pickup' ? tr('farm.order.tl.dispatched') : null;
}
