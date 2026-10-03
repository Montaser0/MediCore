import { apiGet } from "@/lib/api/client";

export type DocumentRow = Record<string, unknown> & {
  id: number;
  title?: string;
  name?: string;
  file_name?: string;
  created_at?: string;
};

export const documentsApi = {
  list: async () => {
    try {
      return await apiGet<DocumentRow[]>("/documents", { params: { per_page: 100 } });
    } catch {
      return { data: [] as DocumentRow[] };
    }
  },
};
