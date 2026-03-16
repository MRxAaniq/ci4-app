import { apiClient, unwrapData } from '../../shared/http/apiClient';
import { apiErrorMessage } from '../../shared/http/apiError';
import type { StockTransfer } from '../../types/models';

type CreateResponse = { message: string; transfer: StockTransfer };

export const transfersService = {
  async create(fromBranch: number, toBranch: number, productId: number, quantity: number): Promise<StockTransfer> {
    try {
      const resp = await apiClient.post('/api/v1/transfers', {
        from_branch: fromBranch,
        to_branch: toBranch,
        product_id: productId,
        quantity,
      });
      return unwrapData<CreateResponse>(resp.data).transfer;
    } catch (e) {
      throw new Error(apiErrorMessage(e, 'Failed to create transfer'));
    }
  },
};
