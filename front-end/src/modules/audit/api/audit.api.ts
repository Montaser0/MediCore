import { apiGet } from "@/lib/api/client";

export type AuditLogRow = {
  id: number;
  action?: string;
  entity_type?: string;
  entity_id?: number;
  user?: { name?: string };
  created_at?: string;
};

export const auditApi = {
  list: async () => {
    try {
      return await apiGet<AuditLogRow[]>("/audit/logs", { params: { per_page: 100 } });
    } catch {
      return apiGet<AuditLogRow[]>("/audit-logs", { params: { per_page: 100 } });
    }
  },
};
