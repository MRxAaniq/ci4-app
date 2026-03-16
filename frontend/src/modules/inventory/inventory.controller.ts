import { inventoryService } from './inventory.service';

export const inventoryController = {
  getBranchInventory: (branchId: number, params?: { page?: number; per_page?: number; q?: string }) =>
    inventoryService.getBranchInventory(branchId, params),
  addStock: (branchId: number, productId: number, quantity: number, note?: string) =>
    inventoryService.addStock(branchId, productId, quantity, note),
  adjustStock: (branchId: number, productId: number, delta: number, note?: string) =>
    inventoryService.adjustStock(branchId, productId, delta, note),
  getMovements: (branchId: number, params?: { page?: number; per_page?: number; q?: string; product_id?: number; ref_type?: string }) =>
    inventoryService.getMovements(branchId, params),
};
