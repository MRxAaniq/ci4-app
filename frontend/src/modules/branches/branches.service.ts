import { apiClient, unwrapData } from '../../shared/http/apiClient';
import { apiErrorMessage } from '../../shared/http/apiError';
import type { Branch } from '../../types/models';
import type { PaginationMeta } from '../../types/pagination';

type ListResponse = { branches: Branch[]; pagination?: PaginationMeta };
type OneResponse = { message: string; branch: Branch };

export const branchesService = {
  async list(params?: { page?: number; per_page?: number; q?: string }): Promise<{ items: Branch[]; pagination?: PaginationMeta }> {
    try {
      const resp = await apiClient.get('/api/v1/branches', { params });
      const data = unwrapData<ListResponse>(resp.data);
      return { items: data.branches, pagination: data.pagination };
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to load branches'));
    }
  },

  async get(id: number): Promise<Branch> {
    try {
      const resp = await apiClient.get(`/api/v1/branches/${id}`);
      return unwrapData<{ branch: Branch }>(resp.data).branch;
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to load branch'));
    }
  },

  async create(input: Pick<Branch, 'name' | 'address'> & Partial<Pick<Branch, 'manager_id' | 'status'>>): Promise<Branch> {
    try {
      const resp = await apiClient.post('/api/v1/branches', input);
      return unwrapData<OneResponse>(resp.data).branch;
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to create branch'));
    }
  },

  async update(id: number, input: Partial<Pick<Branch, 'name' | 'address' | 'manager_id' | 'status'>>): Promise<Branch> {
    try {
      const resp = await apiClient.patch(`/api/v1/branches/${id}`, input);
      return unwrapData<OneResponse>(resp.data).branch;
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to update branch'));
    }
  },
};
