import axios from 'axios';

function firstValidationError(errors: unknown): string | null {
  if (!errors || typeof errors !== 'object') return null;
  const entries = Object.entries(errors as Record<string, unknown>);
  if (entries.length === 0) return null;
  const value = entries[0]?.[1];
  if (Array.isArray(value)) return value.filter(Boolean).join('\n') || null;
  if (typeof value === 'string') return value;
  return null;
}

export function apiErrorMessage(err: unknown, fallback = 'Request failed'): string {
  if (axios.isAxiosError(err)) {
    const data = err.response?.data as any;
    if (data?.message && typeof data.message === 'string') return data.message;
    const v = firstValidationError(data?.errors);
    if (v) return v;
    return err.message || fallback;
  }

  if (err instanceof Error) return err.message;
  return fallback;
}
