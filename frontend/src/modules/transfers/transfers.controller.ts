import { transfersService } from './transfers.service';

export const transfersController = {
  create: (fromBranch: number, toBranch: number, productId: number, quantity: number) =>
    transfersService.create(fromBranch, toBranch, productId, quantity),
};
