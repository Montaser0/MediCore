"use client";

import { FormEvent, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useLocale, useTranslations } from "next-intl";
import { toast } from "sonner";
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
      className="flex min-h-screen flex-col items-center justify-center gap-5 p-4"
      style={{
        background:
          "linear-gradient(135deg, #143a68 0%, #1b4f8c 45%, #2a6fbb 100%)",
      }}
    >
      <img src="/logo.png?v=3" alt={env.appName} className="h-28 w-auto" />
      <form
        onSubmit={onSubmit}
        className="w-full max-w-md overflow-hidden border border-[#8fa0b0] bg-[#f3f6f9] shadow-lg"
      >
        <div
          className="px-4 py-2 text-center text-[13px] font-semibold text-white"
          style={{ background: "var(--his-header)" }}
        >
          {t("auth.loginTitle")}
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
          <Link
            href={`/${locale}/book`}
            className="block text-center text-[12px] underline"
            style={{ color: "var(--his-header)" }}
          >
            {t("auth.bookAppointment")}
          </Link>
        </div>
      </form>
    </div>
  );
}
