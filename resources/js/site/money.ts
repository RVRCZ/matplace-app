/**
 * Amounts on the page, printed the way the server prints them (App\Support\Money): "1 250 Kč", "49,90 €" in Czech,
 * "1,250 Kč", "€49.90" in English, "1.250 Kč", "49,90 €" in Spanish. The layout hands over the visitor's currency,
 * the fixed rate and the language of the page in window.MP_MONEY.
 */
interface MoneyCfg { currency: string; rate: number; locale: string }
const cfg = (): MoneyCfg => (window as unknown as { MP_MONEY?: MoneyCfg }).MP_MONEY ?? { currency: 'CZK', rate: 25, locale: 'cs' };
const SYMBOL: Record<string, string> = { CZK: 'Kč', EUR: '€' };
const NBSP = ' ';

/** The currency prices are shown in to this visitor. */
export const currency = (): string => cfg().currency;

/** An amount in a currency, as text. Crowns without decimals when there are none; euros always with cents. */
export function money(amount: number, code: string = cfg().currency, signed = false): string {
    const { locale } = cfg();
    const size = Math.abs(amount);
    const decimals = code === 'EUR' || Math.abs(size - Math.round(size)) >= 0.005 ? 2 : 0;
    const [point, thousands] = locale === 'cs' ? [',', NBSP] : locale === 'es' ? [',', '.'] : ['.', ','];
    const [whole, cents] = size.toFixed(decimals).split('.');
    const number = whole.replace(/\B(?=(\d{3})+(?!\d))/g, thousands) + (cents ? point + cents : '');
    const sign = amount < -0.004 ? '−' : signed && amount > 0.004 ? '+' : '';
    const symbol = SYMBOL[code] ?? code;
    return sign + (locale !== 'cs' && locale !== 'es' && code === 'EUR' ? symbol + number : number + NBSP + symbol);
}

/** A price defined in crowns, in the visitor's currency: euros at the fixed rate, rounded up to ten cents. */
export function fromCzk(czk: number, code: string = cfg().currency): number {
    if (code !== 'EUR') return czk;
    const sign = czk < 0 ? -1 : 1;
    return (sign * Math.ceil(Math.round((Math.abs(czk) / cfg().rate) * 10 * 1e6) / 1e6)) / 10;
}

/** A price defined in crowns, as text in the visitor's currency. */
export const price = (czk: number): string => money(fromCzk(czk));
