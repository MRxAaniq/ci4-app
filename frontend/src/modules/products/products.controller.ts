import type { Product } from '../../types/models';
import { productsService } from './products.service';

export const productsController = {
  list: (params?: { page?: number; per_page?: number; q?: string }) => productsService.list(params),
  get: (id: number) => productsService.get(id),
  create: (input: Pick<Product, 'name' | 'sku' | 'cost_price' | 'sale_price'> & Partial<Pick<Product, 'tax_percentage' | 'status'>>) =>
    productsService.create(input),
  update: (id: number, input: Partial<Pick<Product, 'name' | 'sku' | 'cost_price' | 'sale_price' | 'tax_percentage' | 'status'>>) =>
    productsService.update(id, input),
  remove: (id: number) => productsService.remove(id),
};
