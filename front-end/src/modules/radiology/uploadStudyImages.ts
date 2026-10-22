import { apiClient } from "@/lib/api/client";

const BATCH_FILES = 12;
const BATCH_BYTES = 32 * 1024 * 1024;

/**
 * A study folder can hold hundreds of DICOM slices. PHP accepts a limited
 * number of files per request, so the folder is sent in small batches and a
 * ZIP goes through as one package for the server to unpack.
 */
export async function uploadStudyImages(
  studyId: number,
  files: File[],
  onBatch?: (files: File[]) => void,
): Promise<string[]> {
  const warnings: string[] = [];

  for (const batch of batchesOf(files)) {
    const body = new FormData();
    batch.forEach((file) => body.append("images[]", file));
    const response = await apiClient.post(`/radiology/studies/${studyId}/images`, body, {
      headers: { "Content-Type": undefined },
    });
    onBatch?.(batch);
    const found = response.data?.data?.warnings;
    if (!Array.isArray(found)) continue;
    for (const warning of found) {
      if (typeof warning === "string" && warning && !warnings.includes(warning)) warnings.push(warning);
    }
  }

  return warnings;
}

function batchesOf(files: File[]): File[][] {
  const batches: File[][] = [];
  let batch: File[] = [];
  let size = 0;

  for (const file of files) {
    const overflows = batch.length > 0 && (batch.length >= BATCH_FILES || size + file.size > BATCH_BYTES);
    if (overflows) {
      batches.push(batch);
      batch = [];
      size = 0;
    }
    batch.push(file);
    size += file.size;
  }

  if (batch.length > 0) batches.push(batch);
  return batches;
}
