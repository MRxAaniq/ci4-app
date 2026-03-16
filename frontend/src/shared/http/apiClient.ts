import axios from 'axios';

// Use relative baseURL so Vite proxy can forward /api to backend.
export const apiClient = axios.create({
  baseURL: '',
  withCredentials: true,
  headers: {
    'Content-Type': 'application/json',
  },
});

export function unwrapData<T>(payload: unknown): T {
  // Backend success envelope: { data: ... }
  if (payload && typeof payload === 'object' && 'data' in (payload as any)) {
    return (payload as any).data as T;
  }
  return payload as T;
}
