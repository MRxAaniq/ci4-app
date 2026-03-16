import type { CreateUserInput } from './users.service';
import { usersService } from './users.service';

export const usersController = {
  create: (input: CreateUserInput) => usersService.create(input),
};
