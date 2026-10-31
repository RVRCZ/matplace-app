/**
 * What a part of a design is called, the same words on the tool's page and in the calculation's download panel:
 * "body" and "stand" mean different things per product (the box, the logo, the sign…). The tools of a picture in
 * colours are `shape`, the tools that edit a model file `edit` (OrderService::partLabel says the same for an order).
 */
export type PartFamily = 'shape' | 'edit' | null;

export function partLabel(part: string, kind: string, family: PartFamily, i18n: Record<string, string>): string {
    const n = part.match(/_(\d{1,2})$/)?.[1] ?? '';
    const stem = n ? part.slice(0, -n.length - 1) : part;
    const say = (key: string): string => i18n[key].split(':n').join(n);
    const first = (...keys: string[]): string => { const key = keys.find((k) => i18n[k]); return key ? say(key) : part; };
    if (part.startsWith('bin_') && i18n['param.part.bin']) return i18n['param.part.bin'].replace(':s', part.slice(4).replace('x', ' × '));
    if (family === 'edit') return first(...(kind === 'filament_art' ? [`edit.art.part.${stem}`] : [`edit.${kind}.part.${stem}`, `edit.part.${stem}`, `edit.split.part.${stem}`]));
    if (family === 'shape') {
        const icing = stem === 'icing' ? ['shape.part.icing'] : [];
        const color = stem === 'color' ? ['shape.part.color'] : [];
        const own = stem === 'body' || stem === 'rim' ? [`shape.part.${stem}`] : [];
        return first(`shape.part.${part}.${kind}`, ...icing, ...color, ...own, `param.part.${part}.${kind}`, `param.part.${part}`, `param.part.${stem}`);
    }
    return first(`param.part.${part}.${kind}`, `param.part.${part}`, `param.part.${stem}`);
}
