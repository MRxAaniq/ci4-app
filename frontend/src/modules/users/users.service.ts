import { apiClient, unwrapData } from '../../shared/http/apiClient';
import { apiErrorMessage } from '../../shared/http/apiError';
import type { Role, User } from '../../types/models';

export type CreatableUserRole = Extract<Role, 'BRANCH_MANAGER' | 'SALES'>;

export type CreateUserInput = {
  name: string;
  email: string;
  password: string;
  role: CreatableUserRole;
  branch_id: number;
};

type CreateResponse = {
  message: string;
  user: User & { status?: string };
  managed_branch_id?: number;
};

export const usersService = {
  async create(input: CreateUserInput): Promise<CreateResponse> {
    try {
      const resp = await apiClient.post('/api/v1/users', input);
      return unwrapData<CreateResponse>(resp.data);
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to create user'));
    }
  },
};
