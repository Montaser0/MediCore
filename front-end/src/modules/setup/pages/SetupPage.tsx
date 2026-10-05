"use client";

import { FormEvent, useEffect, useState, type ReactNode } from "react";
import { useRouter } from "next/navigation";
import { useLocale, useTranslations } from "next-intl";
import { toast } from "sonner";
import { Button } from "@/components/ui/Button";
import { Input } from "@/components/ui/Input";
import { apiGet, apiPost, getErrorMessage } from "@/lib/api/client";
import { useAuthStore } from "@/stores/auth-store";
import { deskHrefs } from "@/config/desk";
import { env } from "@/config/environment";

export function SetupPage() {
  const t = useTranslations();
  const locale = useLocale();
  const router = useRouter();
  const login = useAuthStore((state) => state.login);
  const [pharmacyName, setPharmacyName] = useState("");
  const [adminName, setAdminName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [confirmation, setConfirmation] = useState("");
  const [loading, setLoading] = useState(false);
  const [checking, setChecking] = useState(true);

  useEffect(() => {
    let cancelled = false;
    apiGet<{ required: boolean }>("/setup/status")
      .then((res) => {
        if (cancelled) return;
        if (!res.data?.required) router.replace(`/${locale}/login`);
        else setChecking(false);
      })
      .catch(() => {
        if (!cancelled) setChecking(false);
      });
    return () => {
      cancelled = true;
    };
  }, [locale, router]);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    setLoading(true);
    try {
      await apiPost("/setup", {
        pharmacy_name: pharmacyName.trim(),
        admin_name: adminName.trim(),
        admin_email: email.trim(),
        password,
        password_confirmation: confirmation,
      });
      toast.success(t("station.setupDone"));
      await login(email.trim(), password);
      const home = deskHrefs(useAuthStore.getState().user)?.[0] ?? "/dashboard";
      router.replace(`/${locale}${home}`);
    } catch (error) {
      toast.error(getErrorMessage(error));
    } finally {
      setLoading(false);
    }
  }

  if (checking) {
    return (
      <div
        className="flex min-h-screen items-center justify-center text-sm text-white"
        style={{ background: "linear-gradient(135deg, #143a68 0%, #1b4f8c 45%, #2a6fbb 100%)" }}
      >
        {t("app.loading")}
      </div>
    );
  }

  return (
    <div
      className="flex min-h-screen flex-col items-center justify-center gap-5 p-4"
      style={{ background: "linear-gradient(135deg, #143a68 0%, #1b4f8c 45%, #2a6fbb 100%)" }}
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
          {t("station.setupTitle")}
        </div>
        <div className="space-y-3 p-5">
          <p className="m-0 text-[12px] text-[var(--his-muted)]">{t("station.setupSubtitle")}</p>
          <Field label={t("station.pharmacyName")}>
            <Input value={pharmacyName} onChange={(event) => setPharmacyName(event.target.value)} required />
          </Field>
          <Field label={t("station.adminName")}>
            <Input value={adminName} onChange={(event) => setAdminName(event.target.value)} required />
          </Field>
          <Field label={t("station.adminEmail")}>
            <Input
              type="email"
              dir="ltr"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              autoComplete="username"
              required
            />
          </Field>
          <Field label={t("station.adminPassword")}>
            <Input
              type="password"
              value={password}
              onChange={(event) => setPassword(event.target.value)}
              autoComplete="new-password"
              required
            />
          </Field>
          <Field label={t("station.adminPasswordConfirm")}>
            <Input
              type="password"
              value={confirmation}
              onChange={(event) => setConfirmation(event.target.value)}
              autoComplete="new-password"
              required
            />
          </Field>
          <p className="m-0 text-[11px] text-[var(--his-muted)]">{t("station.passwordHint")}</p>
          <Button type="submit" variant="primary" className="w-full h-9!" disabled={loading}>
            {loading ? t("app.loading") : t("station.finish")}
          </Button>
        </div>
      </form>
    </div>
  );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div>
      <label className="mb-1 block text-[12px]">{label}</label>
      {children}
    </div>
  );
}
