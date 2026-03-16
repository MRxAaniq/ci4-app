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

type ListUser = Pick<User, 'id' | 'name' | 'email'> & { role?: Role | string; status?: string; branch_id?: number };
type ListResponse = { users: ListUser[] };

export const usersService = {
  async list(params?: { page?: number; per_page?: number; q?: string; role?: Role | string; status?: string }): Promise<ListUser[]> {
    try {
      const resp = await apiClient.get('/api/v1/users', { params });
      return (unwrapData<ListResponse>(resp.data).users || []).map((u: any) => ({
        id: Number(u?.id ?? 0) || 0,
        name: String(u?.name ?? ''),
        email: String(u?.email ?? ''),
        role: u?.role,
        status: u?.status,
        branch_id: u?.branch_id === null || u?.branch_id === undefined ? undefined : Number(u.branch_id) || 0,
      }));
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to load users'));
    }
  },

  async create(input: CreateUserInput): Promise<CreateResponse> {
    try {
      const resp = await apiClient.post('/api/v1/users', input);
      return unwrapData<CreateResponse>(resp.data);
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to create user'));
    }
  },
};
