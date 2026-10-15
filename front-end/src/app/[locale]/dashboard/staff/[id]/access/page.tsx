import { StaffAccessPage } from "@/modules/staff/pages/StaffAccessPage";

type Props = { params: Promise<{ id: string }> };

export default async function Page({ params }: Props) {
  const { id } = await params;
  return <StaffAccessPage staffId={id} />;
}
