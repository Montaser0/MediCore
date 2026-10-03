import { latinDigits } from "@/lib/utils";

const CODES: Record<string, string> = {
  RCP: "إيصال",
  ORD: "طلب",
  RX: "وصفة",
  DSP: "صرف",
  INV: "فاتورة",
  ADM: "تنويم",
  OPR: "عملية",
  LAB: "عينة",
  STD: "دراسة",
  EMP: "موظف",
};

/** Daily serial shown to staff. Stored values stay unique across days. */
export function dailyNo(value: unknown): string {
  if (value == null || value === "") return "";
  const text = String(value).trim();
  const stored = text.match(/^\d{4}-\d{2}-\d{2}-(\d+)$/);
  if (stored) return String(Number(stored[1]));
  const coded = text.match(/^(?:MRN|ENC|APT|زيارة|موعد)[\s-]+(?:\d{4,8}-)?(\d+)$/i);
  if (coded) return String(Number(coded[1]));
  if (/^\d+$/.test(text)) return String(Number(text));
  return text;
}

/** Arabic wording for stored English phrases and document codes. The patient file number (MRN) stays unchanged. */
export function localize(value: unknown): string {
  if (value == null) return "";
  let text = String(value);
  if (!text.trim()) return text;
  if (/^\d{4}-\d{2}-\d{2}-\d+$/.test(text)) return dailyNo(text);

  text = text.replace(/medical file (?:MRN-\d{4}-\d+ )?created\.?/gi, "تسجيل المريض");
  text = text.replace(/\b(?:MRN|ENC|APT)-\d{4,8}-\d+\b/gi, "");
  text = text.replace(/(?:زيارة|موعد)\s+\d{4}-\d+/g, "");
  text = text.replace(/bed charge/gi, "أجرة السرير");
  text = text.replace(/surgical supply/gi, "مستلزم جراحي");
  text = text.replace(/imaging study/gi, "فحص أشعة");
  text = text.replace(/laboratory test/gi, "تحليل مخبري");
  text = text.replace(/appointment booked/gi, "حُجز الموعد");
  text = text.replace(/(\d+)\s+reading\(s\) outside the normal range/gi, "$1 قراءة خارج المجال الطبيعي");
  text = text.replace(/all readings within normal range/gi, "كل القراءات ضمن المجال الطبيعي");
  text = text.replace(/result within reference range/gi, "ضمن المجال المرجعي");
  text = text.replace(/medical file (MRN-\d{4}-\d+) created\.?/gi, "أُنشئ الملف $1");
  text = text.replace(/payment received:\s*/gi, "استُلم مبلغ ");
  text = text.replace(/vital signs recorded/gi, "سُجّلت العلامات الحيوية");
  text = text.replace(/patient registered/gi, "تسجيل المريض");
  text = text.replace(/patient discharged/gi, "إنهاء الزيارة");
  text = text.replace(/visit cancelled/gi, "إلغاء الزيارة");
  text = text.replace(/admitted to hospital/gi, "تنويم");
  text = text.replace(/operation scheduled/gi, "جدولة عملية");
  text = text.replace(/operation completed/gi, "اكتمال العملية");
  text = text.replace(/procedure performed:\s*/gi, "إجراء: ");
  text = text.replace(/\bfor order\s+(?:ORD-\d{4}-\d+\s*)?/gi, "");
  text = text.replace(/\breceipt\b/gi, "إيصال");
  text = text.replace(/\bcancelled\b/gi, "ملغى");
  text = text.replace(/\bprescription\b/gi, "وصفة");
  text = text.replace(/\border\b/gi, "طلب");
  text = text.replace(/\bvisit\b/gi, "زيارة");
  text = text.replace(/\bNew\b/g, "جديد");
  text = text.replace(/result flagged\s+critical/gi, "نتيجة حرجة");
  text = text.replace(/result flagged\s+high/gi, "النتيجة مرتفعة");
  text = text.replace(/result flagged\s+low/gi, "النتيجة منخفضة");
  text = text.replace(/critical radiology finding/gi, "نتيجة أشعة حرجة");
  text = text.replace(/radiology report ready/gi, "تقرير الأشعة جاهز");
  text = text.replace(/critical laboratory result/gi, "نتيجة تحليل حرجة");
  text = text.replace(/has been verified\.?/gi, "تم اعتمادها");
  text = text.replace(/has been reported\.?/gi, "تم إصدار التقرير");
  text = text.replace(/returned a critical value\.?/gi, "نتيجتها حرجة");
  text = text.replace(/new admission/gi, "تنويم جديد");
  text = text.replace(/has been admitted as/gi, "نُوّم برقم");
  text = text.replace(/patient transferred in/gi, "وصل مريض منقول");
  text = text.replace(/was transferred to your department\.?/gi, "نُقل إلى قسمكم");
  text = text.replace(/another department/gi, "قسم آخر");
  text = text.replace(/\bno bed\b/gi, "بدون سرير");
  text = text.replace(/with (\d+) item\(s\)\.?/gi, "بعدد $1");
  text = text.replace(/you are on a surgical team/gi, "أُضفت إلى فريق عملية");
  text = text.replace(/result awaiting verification/gi, "نتيجة بانتظار الاعتماد");
  text = text.replace(/has a result ready to verify\.?/gi, "جاهزة للاعتماد");
  text = text.replace(/\btransferred to\b/gi, "نُقل إلى");
  text = text.replace(/\bdiagnosis:\s*/gi, "تشخيص: ");
  text = text.replace(/\bward\b/gi, "الجناح");
  text = text.replace(/\boutpatient\b/gi, "عيادة خارجية");
  text = text.replace(/\bfollow_up\b/gi, "مراجعة");
  text = text.replace(/\bemergency\b/gi, "إسعاف");
  text = text.replace(/\binpatient\b/gi, "تنويم");
  text = text.replace(
    /\b(RCP|ORD|RX|DSP|INV|ADM|OPR|LAB|STD|EMP)-(\d{4}-\d+)\b/g,
    (_, prefix: string, number: string) => `${CODES[prefix] ?? prefix} ${number}`,
  );

  return latinDigits(text.replace(/\s{2,}/g, " ").trim());
}
