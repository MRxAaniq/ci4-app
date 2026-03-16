export interface PaginationMeta {
  page: number;
  per_page: number;
  total: number;
  total_pages: number;
  q?: string;
}

export interface PaginatedResult<T> {
  items: T[];
  pagination: PaginationMeta;
}
