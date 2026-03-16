import { apiClient, unwrapData } from '../../shared/http/apiClient';
import { apiErrorMessage } from '../../shared/http/apiError';
import type { Order } from '../../types/models';
import type { PaginationMeta } from '../../types/pagination';

type CreateResponse = { message: string; order: Order };
type ListResponse = { branch_id: number; orders: Array<Order & { user_name?: string; user_email?: string }>; pagination?: PaginationMeta };
type ApproveResponse = { message: string; order: Order };

export type OrderProductLine = { product_id: number; quantity: number };

export const ordersService = {
  async create(branchId: number, products: OrderProductLine[]): Promise<Order> {
    try {
      const resp = await apiClient.post('/api/v1/orders', {
        branch_id: branchId,
        products,
      });
      return unwrapData<CreateResponse>(resp.data).order;
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to submit order'));
    }
  },

  async listByBranch(
    branchId: number,
    params?: { page?: number; per_page?: number; q?: string; status?: Order['status'] }
  ): Promise<{ items: Array<Order & { user_name?: string; user_email?: string }>; pagination?: PaginationMeta }> {
    try {
      const resp = await apiClient.get(`/api/v1/branches/${branchId}/orders`, { params });
      const data = unwrapData<ListResponse>(resp.data);
      return { items: data.orders, pagination: data.pagination };
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to load orders'));
    }
  },

  async approve(orderId: number): Promise<Order> {
    try {
      const resp = await apiClient.post(`/api/v1/orders/${orderId}/approve`);
      return unwrapData<ApproveResponse>(resp.data).order;
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to approve order'));
    }
  },
};
