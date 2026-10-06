/** A line icon of the site's sprite (resources/views/partials/icons.blade.php) as markup for scripts that build HTML. */
export function icon(name: string, cls = 'h-4 w-4'): string {
    return `<svg class="inline-block shrink-0 ${cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><use href="#i-${name}"/></svg>`;
}
