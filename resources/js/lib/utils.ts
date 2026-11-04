import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

/** Las vocales con tilde cuentan como su versión sin tilde. */
const SIN_TILDES: Record<string, string> = { á: 'a', é: 'e', í: 'i', ó: 'o', ú: 'u', ü: 'u', ñ: 'n' };

/**
 * Fragmento del código de un material (su categoría o su base): solo
 * mayúsculas, máximo 3 letras. Es la misma regla que aplica el modelo.
 */
export function normalizarFragmentoCodigo(valor: unknown): string {
    return String(valor ?? '')
        .toLowerCase()
        .split('')
        .map((letra) => SIN_TILDES[letra] ?? letra)
        .join('')
        .replace(/[^a-z]/g, '')
        .slice(0, 3)
        .toUpperCase();
}
