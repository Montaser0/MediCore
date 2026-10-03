import { PatientWorkspacePage } from "@/modules/patients";

type Props = { params: Promise<{ id: string }> };

export default async function Page({ params }: Props) {
  const { id } = await params;
  return <PatientWorkspacePage patientId={id} />;
}
