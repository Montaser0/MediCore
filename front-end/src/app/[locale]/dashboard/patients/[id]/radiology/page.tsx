import { PatientRadiologyOrderPage } from "@/modules/patients/pages/PatientRadiologyOrderPage";

type Props = { params: Promise<{ id: string }> };

export default async function Page({ params }: Props) {
  const { id } = await params;
  return <PatientRadiologyOrderPage patientId={id} />;
}
