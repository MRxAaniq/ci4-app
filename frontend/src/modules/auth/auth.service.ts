import { apiClient, unwrapData } from '../../shared/http/apiClient';
import { apiErrorMessage } from '../../shared/http/apiError';
import type { User } from '../../types/models';

type LoginResponse = { message: string; user: User };

export const authService = {
  async login(email: string, password: string): Promise<User> {
    try {
      const resp = await apiClient.post('/api/v1/auth/login', { email, password });
      const payload = unwrapData<LoginResponse>(resp.data);
      return payload.user;
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Login failed'));
    }
  },

  async logout(): Promise<void> {
    try {
      await apiClient.post('/api/v1/auth/logout');
    } catch (e) {
      // If session is already invalid, treat as logged out.
    }
  },
};
