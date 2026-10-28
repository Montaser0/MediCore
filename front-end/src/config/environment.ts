declare global {
  interface Window {
    __HMS__?: { apiUrl?: string };
  }
}

export function resolveApiUrl(): string {
  if (typeof window !== "undefined" && window.__HMS__?.apiUrl) {
    return window.__HMS__.apiUrl;
  }

  return process.env.NEXT_PUBLIC_API_URL ?? "http://127.0.0.1:8001/api";
}

export const env = {
  get apiUrl() {
    return resolveApiUrl();
  },
  appName: process.env.NEXT_PUBLIC_APP_NAME ?? "MediCore",
};
