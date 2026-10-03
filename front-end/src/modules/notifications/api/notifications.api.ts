import { apiGet } from "@/lib/api/client";

export type NotificationRow = {
  id: string;
  type?: string;
  title?: string;
  message?: string;
  read_at?: string | null;
  created_at?: string;
};

export const notificationsApi = {
  list: () =>
    apiGet<NotificationRow[]>("/notifications", { params: { per_page: 100 } }),
};
