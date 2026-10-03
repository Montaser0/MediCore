"use client";

import { FormEvent, useState } from "react";
import { useRouter } from "next/navigation";
import { useLocale, useTranslations } from "next-intl";
import { toast } from "sonner";
import { Hospital } from "lucide-react";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { useAuthStore } from "@/stores/auth-store";
import { deskHrefs } from "@/config/desk";
import { getErrorMessage } from "@/lib/api/client";
import { env } from "@/config/environment";

export function LoginPage() {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const login = useAuthStore((s) => s.login);
  const [username, setUsername] = useState("");
  const [password, setPassword] = useState("");
  const [loading, setLoading] = useState(false);

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    setLoading(true);
    try {
      await login(username, password);
      toast.success(t("app.welcome"));
      const home = deskHrefs(useAuthStore.getState().user)?.[0] ?? "/dashboard";
      router.replace(`/${locale}${home}`);
    } catch (error) {
      toast.error(getErrorMessage(error, t("auth.failed")));
    } finally {
      setLoading(false);
    }
  }

  return (
    <div
      className="flex min-h-screen items-center justify-center p-4"
      style={{
        background:
          "linear-gradient(135deg, #143a68 0%, #1b4f8c 45%, #2a6fbb 100%)",
      }}
    >
      <form
        onSubmit={onSubmit}
        className="w-full max-w-md border border-[#8fa0b0] bg-[#f3f6f9] shadow-lg"
      >
        <div
          className="flex items-center gap-2 px-4 py-3 text-white"
          style={{ background: "var(--his-header)" }}
        >
          <Hospital size={20} />
          <div>
            <div className="text-[15px] font-semibold">{env.appName}</div>
            <div className="text-[11px] opacity-80">{t("auth.loginTitle")}</div>
          </div>
        </div>

        <div className="space-y-3 p-5">
          <p className="m-0 text-[12px] text-[var(--his-muted)]">
            {t("auth.loginSubtitle")}
          </p>
          <div>
            <label className="mb-1 block text-[12px]">{t("auth.username")}</label>
            <Input
              value={username}
              onChange={(e) => setUsername(e.target.value)}
              autoComplete="username"
              required
            />
          </div>
          <div>
            <label className="mb-1 block text-[12px]">{t("auth.password")}</label>
            <Input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              autoComplete="current-password"
              required
            />
          </div>
          <Button
            type="submit"
            variant="primary"
            className="w-full h-9!"
            disabled={loading}
          >
            {loading ? t("app.loading") : t("auth.submit")}
          </Button>
        </div>
      </form>
    </div>
  );
}
