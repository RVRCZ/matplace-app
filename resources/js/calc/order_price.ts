/**
 * The price lines of the order page (Roman, 22 Oct 2026): the big number is the print (with VAT and the designer's
 * share, without delivery), a small line under it names the delivery chosen and what it costs, and the sum of both is
 * said only where it is paid. Pure, so scripts/check_order_timeline.mjs can check it without a browser.
 */
export interface PriceParts { total: number; shipping: number }

type Tr = (key: string, params?: Record<string, string | number>) => string;

/** The print alone: everything of the price but the delivery. */
export function printOnly(p: PriceParts | null): number | null {
    return p ? Math.round((p.total - (p.shipping || 0)) * 100) / 100 : null;
}

/**
 * The delivery line. `way` = the delivery chosen ('' none yet), `price` = the price with that delivery (null while the
 * delivery quote is on its way), `open` = the order is not paid yet (then nothing chosen asks to choose).
 */
export function shippingText(way: string, price: PriceParts | null, open: boolean, tr: Tr, money: (n: number) => string): string {
    if (!way) return open ? tr('farm.order.ship.choose') : '';
    if (way === 'pickup') return tr('farm.order.ship.pickup');
    if (!price) return tr('farm.order.ship.counting');
    return price.shipping > 0 ? tr('farm.order.ship.parcel', { amount: money(price.shipping) }) : tr('farm.order.ship.free');
}
