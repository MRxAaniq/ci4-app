import { apiClient, unwrapData } from '../../shared/http/apiClient';
import { apiErrorMessage } from '../../shared/http/apiError';
import type { Branch } from '../../types/models';
import type { PaginationMeta } from '../../types/pagination';

type ListResponse = { branches: Branch[]; pagination?: PaginationMeta };
type OneResponse = { message: string; branch: Branch };

function normalizeBranch(raw: any): Branch {
  return {
    ...(raw as Branch),
    id: Number(raw?.id ?? 0) || 0,
    manager_id: raw?.manager_id === null || raw?.manager_id === undefined || raw?.manager_id === ''
      ? null
      : (Number(raw.manager_id) || null),
  };
}

export const branchesService = {
  async list(params?: { page?: number; per_page?: number; q?: string }): Promise<{ items: Branch[]; pagination?: PaginationMeta }> {
    try {
      const resp = await apiClient.get('/api/v1/branches', { params });
      const data = unwrapData<ListResponse>(resp.data);
      return { items: (data.branches || []).map(normalizeBranch), pagination: data.pagination };
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to load branches'));
    }
  },

  async get(id: number): Promise<Branch> {
    try {
      const resp = await apiClient.get(`/api/v1/branches/${id}`);
      return normalizeBranch(unwrapData<{ branch: Branch }>(resp.data).branch);
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to load branch'));
    }
  },

  async create(
    input: Pick<Branch, 'name' | 'address'> &
      Partial<Pick<Branch, 'manager_id' | 'manager_name' | 'manager_email' | 'status'>>
  ): Promise<Branch> {
    try {
      const resp = await apiClient.post('/api/v1/branches', input);
      return normalizeBranch(unwrapData<OneResponse>(resp.data).branch);
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to create branch'));
    }
  },

  async update(
    id: number,
    input: Partial<Pick<Branch, 'name' | 'address' | 'manager_id' | 'manager_name' | 'manager_email' | 'status'>>
  ): Promise<Branch> {
    try {
      const resp = await apiClient.patch(`/api/v1/branches/${id}`, input);
      return normalizeBranch(unwrapData<OneResponse>(resp.data).branch);
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to update branch'));
    }
  },

  async remove(id: number): Promise<void> {
    try {
      await apiClient.delete(`/api/v1/branches/${id}`);
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to delete branch'));
    }
  },
};
