import { apiClient, unwrapData } from '../../shared/http/apiClient';
import { apiErrorMessage } from '../../shared/http/apiError';
import type { Branch } from '../../types/models';

export type BranchDashboardStats = {
  sales_today: number;
  sales_month: number;
  orders_month: number;
  orders_total: number;
};

export type TopProductRow = {
  product_id: number;
  name: string;
  sku: string;
  qty_sold: string | number;
  revenue: string | number;
};

export type LowStockRow = {
  product_id: number;
  quantity: number;
  name: string;
  sku: string;
  status: 'ACTIVE' | 'INACTIVE';
};

type DashboardResponse = {
  branch: Branch;
  low_stock_threshold: number;
  stats: BranchDashboardStats;
  top_products: TopProductRow[];
  low_stock_items: LowStockRow[];
};

type OverallDashboardResponse = {
  stats: BranchDashboardStats;
};

export const reportsService = {
  async getBranchDashboard(branchId: number, params?: { low_stock_threshold?: number }): Promise<DashboardResponse> {
    try {
      const resp = await apiClient.get(`/api/v1/branches/${branchId}/dashboard`, { params });
      return unwrapData<DashboardResponse>(resp.data);
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to load dashboard'));
    }
  },

  async getOverallDashboard(): Promise<OverallDashboardResponse> {
    try {
      const resp = await apiClient.get('/api/v1/dashboard/overall');
      return unwrapData<OverallDashboardResponse>(resp.data);
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to load overall dashboard'));
    }
  },
};
