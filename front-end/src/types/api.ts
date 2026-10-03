export type ApiMeta = {
  current_page?: number;
  per_page?: number;
  total?: number | null;
  last_page?: number | null;
};

export type ApiResponse<T> = {
  data: T;
  meta?: ApiMeta;
  message?: string;
};

export type Paginated<T> = {
  data: T[];
  meta: ApiMeta;
};
