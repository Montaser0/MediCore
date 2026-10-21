import { barcodeSvg } from "@/modules/inventory/barcode";

export function BarcodeImage({
  code,
  height = 48,
}: {
  code: string;
  height?: number;
}) {
  const svg = barcodeSvg(code, 70);
  if (!svg) return <span className="font-mono text-[11px]">{code}</span>;

  return (
    <span
      dir="ltr"
      className="inline-block bg-white"
      style={{ width: 168, height }}
      dangerouslySetInnerHTML={{ __html: svg }}
    />
  );
}
