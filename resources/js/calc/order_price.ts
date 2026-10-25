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
 * The sum with the delivery, under the price (the sales path, 25 Oct 2026): exact once the delivery quote is here
 * (`quoted`), before that the print plus the carrier's list price for the country (`offered`), said as an estimate;
 * pickup is the print alone. '' when nothing is chosen or the print is not priced yet.
 */
export function totalText(way: string, print: number | null, quoted: PriceParts | null, offered: number | null, tr: Tr, money: (n: number) => string): string {
    if (!way || print === null) return '';
    if (way === 'pickup') return tr('farm.order.ship.total', { total: money(print) });
    if (quoted) return tr('farm.order.ship.total', { total: money(quoted.total) });
    if (offered !== null) return tr('farm.order.ship.total_estimate', { total: money(Math.round((print + offered) * 100) / 100) });
    return '';
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
