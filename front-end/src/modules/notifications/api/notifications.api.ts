import { apiGet, apiPost } from "@/lib/api/client";

export type NotificationRow = {
  id: number | string;
  type?: string;
  title?: string;
  message?: string;
  read_at?: string | null;
  created_at?: string;
  payload?: { patient_id?: number; order_type?: string } | null;
};

export const notificationsApi = {
  list: () =>
    apiGet<NotificationRow[]>("/notifications", { params: { per_page: 20 } }),
  markRead: (id: number | string) => apiPost(`/notifications/${id}/read`),
  markAllRead: () => apiPost("/notifications/read-all"),
};
