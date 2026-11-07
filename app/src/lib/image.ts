// Compress on the phone before storing/uploading: max 1600 px, JPEG (NFR-02).
export async function compress(file: Blob, max = 1600, quality = 0.82): Promise<Blob> {
  const bmp = await createImageBitmap(file).catch(() => null);
  if (!bmp) return file;
  const scale = Math.min(1, max / Math.max(bmp.width, bmp.height));
  const c = document.createElement('canvas');
  c.width = Math.round(bmp.width * scale); c.height = Math.round(bmp.height * scale);
  c.getContext('2d')!.drawImage(bmp, 0, 0, c.width, c.height);
  bmp.close?.();
  return new Promise(res => c.toBlob(b => res(b ?? file), 'image/jpeg', quality));
}
