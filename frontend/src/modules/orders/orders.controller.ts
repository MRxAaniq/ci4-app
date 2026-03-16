import { ordersService, type OrderProductLine } from './orders.service';
import type { Order } from '../../types/models';

export const ordersController = {
  create: (branchId: number, products: OrderProductLine[]) => ordersService.create(branchId, products),
  listByBranch: (branchId: number, params?: { page?: number; per_page?: number; q?: string; status?: Order['status'] }) =>
    ordersService.listByBranch(branchId, params),
  approve: (orderId: number) => ordersService.approve(orderId),
};
