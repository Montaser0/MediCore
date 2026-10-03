import axios, { type AxiosError, type AxiosRequestConfig } from "axios";
import { env } from "@/config/environment";
import type { ApiResponse } from "@/types/api";

const TOKEN_KEY = "hms_token";

export function getToken(): string | null {
  if (typeof window === "undefined") return null;
  return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token: string | null) {
  if (typeof window === "undefined") return;
  if (token) localStorage.setItem(TOKEN_KEY, token);
  else localStorage.removeItem(TOKEN_KEY);
}

export const apiClient = axios.create({
  baseURL: env.apiUrl,
  headers: {
    Accept: "application/json",
    "Content-Type": "application/json",
  },
});

apiClient.interceptors.request.use((config) => {
  const token = getToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<{ message?: string; error?: string }>) => {
    if (error.response?.status === 401 && typeof window !== "undefined") {
      setToken(null);
      const path = window.location.pathname;
      if (!path.includes("/login")) {
        window.location.href = "/ar/login";
      }
    }
    return Promise.reject(error);
  },
);

export async function apiGet<T>(url: string, config?: AxiosRequestConfig) {
  const res = await apiClient.get<ApiResponse<T>>(url, config);
  return res.data;
}

export async function apiPost<T>(
  url: string,
  data?: unknown,
  config?: AxiosRequestConfig,
) {
  const res = await apiClient.post<ApiResponse<T>>(url, data, config);
  return res.data;
}

export async function apiPut<T>(
  url: string,
  data?: unknown,
  config?: AxiosRequestConfig,
) {
  const res = await apiClient.put<ApiResponse<T>>(url, data, config);
  return res.data;
}

export async function apiPatch<T>(
  url: string,
  data?: unknown,
  config?: AxiosRequestConfig,
) {
  const res = await apiClient.patch<ApiResponse<T>>(url, data, config);
  return res.data;
}

export async function apiDelete<T>(url: string, config?: AxiosRequestConfig) {
  const res = await apiClient.delete<ApiResponse<T>>(url, config);
  return res.data;
}

const STATUS_AR: Record<number, string> = {
  401: "يلزم تسجيل الدخول.",
  403: "ليست لديك صلاحية لهذا الإجراء.",
  404: "السجل المطلوب غير موجود.",
  409: "هذا الإجراء يتعارض مع الحالة الحالية.",
  422: "البيانات المدخلة غير صالحة.",
  429: "محاولات كثيرة. انتظر قليلاً ثم أعد المحاولة.",
};

function toArabic(message: string, status?: number, fallback = "حدث خطأ غير متوقع") {
  const text = message.trim();
  if (!text) return status ? (STATUS_AR[status] ?? fallback) : fallback;
  if (/[\u0600-\u06FF]/.test(text)) return text;
  if (status && status >= 500) return "حدث خطأ في الخادم.";
  return status ? (STATUS_AR[status] ?? fallback) : fallback;
}

export function getErrorMessage(error: unknown, fallback = "حدث خطأ غير متوقع") {
  if (axios.isAxiosError(error)) {
    if (!error.response) return "تعذر الاتصال بالخادم.";
    const data = error.response.data as
      | { message?: string; errors?: Record<string, string[]> }
      | undefined;
    const first = data?.errors
      ? Object.values(data.errors).flat().find(Boolean)
      : undefined;
    return toArabic(first ?? data?.message ?? "", error.response.status, fallback);
  }
  if (error instanceof Error) return toArabic(error.message, undefined, fallback);
  return fallback;
}
