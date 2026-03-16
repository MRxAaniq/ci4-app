import { apiClient, unwrapData } from '../../shared/http/apiClient';
import { apiErrorMessage } from '../../shared/http/apiError';
import type { InventoryMovement, InventoryRow } from '../../types/models';
import type { PaginationMeta } from '../../types/pagination';

type ListResponse = { branch_id: number; inventory: InventoryRow[]; pagination?: PaginationMeta };
type MovementsResponse = { branch_id: number; movements: InventoryMovement[]; pagination?: PaginationMeta };

export const inventoryService = {
  async getBranchInventory(branchId: number, params?: { page?: number; per_page?: number; q?: string }): Promise<{ items: InventoryRow[]; pagination?: PaginationMeta }> {
    try {
      const resp = await apiClient.get(`/api/v1/branches/${branchId}/inventory`, { params });
      const data = unwrapData<ListResponse>(resp.data);
      return { items: data.inventory, pagination: data.pagination };
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to load inventory'));
    }
  },

  async addStock(branchId: number, productId: number, quantity: number, note?: string): Promise<void> {
    try {
      await apiClient.post(`/api/v1/branches/${branchId}/inventory/add`, {
        product_id: productId,
        quantity,
        note,
      });
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to add stock'));
    }
  },

  async adjustStock(branchId: number, productId: number, delta: number, note?: string): Promise<void> {
    try {
      await apiClient.post(`/api/v1/branches/${branchId}/inventory/adjust`, {
        product_id: productId,
        delta,
        note,
      });
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to adjust stock'));
    }
  },

  async getMovements(
    branchId: number,
    params?: { page?: number; per_page?: number; q?: string; product_id?: number; ref_type?: string }
  ): Promise<{ items: InventoryMovement[]; pagination?: PaginationMeta }> {
    try {
      const resp = await apiClient.get(`/api/v1/branches/${branchId}/inventory/movements`, { params });
      const data = unwrapData<MovementsResponse>(resp.data);
      return { items: data.movements, pagination: data.pagination };
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to load movement history'));
    }
  },
};
