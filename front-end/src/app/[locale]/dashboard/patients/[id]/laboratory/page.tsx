import { PatientLabOrderPage } from "@/modules/patients/pages/PatientLabOrderPage";

type Props = { params: Promise<{ id: string }> };

export default async function Page({ params }: Props) {
  const { id } = await params;
  return <PatientLabOrderPage patientId={id} />;
}
