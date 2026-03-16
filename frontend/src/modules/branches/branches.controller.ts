import type { Branch } from '../../types/models';
import { branchesService } from './branches.service';

export const branchesController = {
  list: (params?: { page?: number; per_page?: number; q?: string }) => branchesService.list(params),
  get: (id: number) => branchesService.get(id),
  create: (
    input: Pick<Branch, 'name' | 'address'> & Partial<Pick<Branch, 'manager_id' | 'manager_name' | 'manager_email' | 'status'>>
  ) => branchesService.create(input),
  update: (id: number, input: Partial<Pick<Branch, 'name' | 'address' | 'manager_id' | 'manager_name' | 'manager_email' | 'status'>>) =>
    branchesService.update(id, input),
  remove: (id: number) => branchesService.remove(id),
};
