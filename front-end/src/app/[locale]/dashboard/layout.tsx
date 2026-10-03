import { AuthGuard } from "@/components/auth/AuthGuard";
import { DeskGuard } from "@/components/auth/DeskGuard";
import { DashboardLayout } from "@/layouts/DashboardLayout";

export default function DashboardRootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <AuthGuard>
      <DeskGuard>
        <DashboardLayout>{children}</DashboardLayout>
      </DeskGuard>
    </AuthGuard>
  );
}
