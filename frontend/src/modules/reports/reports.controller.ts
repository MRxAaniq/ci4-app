import { reportsService } from './reports.service';

export const reportsController = {
  getBranchDashboard: (branchId: number, params?: { low_stock_threshold?: number }) => reportsService.getBranchDashboard(branchId, params),
};
