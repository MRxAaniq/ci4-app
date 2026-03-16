import { apiClient, unwrapData } from '../../shared/http/apiClient';
import { apiErrorMessage } from '../../shared/http/apiError';
import type { Product } from '../../types/models';
import type { PaginationMeta } from '../../types/pagination';

type ListResponse = { products: Product[]; pagination?: PaginationMeta };
type OneResponse = { message: string; product: Product };

type ProductUpsert = Pick<Product, 'name' | 'cost_price' | 'sale_price'> &
  Partial<Pick<Product, 'tax_percentage' | 'status'>>;

export const productsService = {
  async list(params?: { page?: number; per_page?: number; q?: string }): Promise<{ items: Product[]; pagination?: PaginationMeta }> {
    try {
      const resp = await apiClient.get('/api/v1/products', { params });
      const data = unwrapData<ListResponse>(resp.data);
      return { items: data.products, pagination: data.pagination };
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to load products'));
    }
  },

  async get(id: number): Promise<Product> {
    try {
      const resp = await apiClient.get(`/api/v1/products/${id}`);
      return unwrapData<{ product: Product }>(resp.data).product;
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to load product'));
    }
  },

  async create(input: ProductUpsert): Promise<Product> {
    try {
      const resp = await apiClient.post('/api/v1/products', input);
      return unwrapData<OneResponse>(resp.data).product;
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to create product'));
    }
  },

  async update(id: number, input: Partial<ProductUpsert>): Promise<Product> {
    try {
      const resp = await apiClient.patch(`/api/v1/products/${id}`, input);
      return unwrapData<OneResponse>(resp.data).product;
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to update product'));
    }
  },

  async remove(id: number): Promise<void> {
    try {
      await apiClient.delete(`/api/v1/products/${id}`);
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to delete product'));
    }
  },
};
