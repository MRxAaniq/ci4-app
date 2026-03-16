import type { CreateUserInput } from './users.service';
import { usersService } from './users.service';

export const usersController = {
  list: (params?: { page?: number; per_page?: number; q?: string; role?: string; status?: string }) => usersService.list(params),
  create: (input: CreateUserInput) => usersService.create(input),
};
