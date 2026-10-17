/**
 * A photo before it leaves the browser (the photo tools: relief, bust, pet figurine). Phones give pictures of 4 000 px
 * and 8 to 12 MB; the tools need 2 000 px at most. A bigger photo is drawn smaller on a canvas (turned the way the
 * camera meant it) and sent as a JPEG of a few hundred kB: the upload is quick on mobile data and never hits the
 * server's limit. What the browser cannot draw (HEIC on Windows, a broken file) goes as it is, and the server judges it.
 */
export const PHOTO_EDGE = 2000;

/** The photo made at most `edge` px on its longer side; the same file when it is already small enough. */
export async function shrinkPhoto(file: File, edge = PHOTO_EDGE): Promise<File> {
    if (!file.type.startsWith('image/') || file.type === 'image/gif') return file;
    let bitmap: ImageBitmap;
    try {
        bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    } catch {
        return file;
    }
    const scale = Math.min(1, edge / Math.max(bitmap.width, bitmap.height));
    // a small photo that is also light stays untouched (no second JPEG compression)
    if (scale === 1 && file.size <= 3 * 1024 * 1024) { bitmap.close(); return file; }
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(bitmap.width * scale));
    canvas.height = Math.max(1, Math.round(bitmap.height * scale));
    const ctx = canvas.getContext('2d');
    if (!ctx) { bitmap.close(); return file; }
    // a transparent PNG would turn black in a JPEG: white under it
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();
    const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.92));
    if (!blob || (scale === 1 && blob.size >= file.size)) return file;
    return new File([blob], `${file.name.replace(/\.[^.]+$/, '') || 'photo'}.jpg`, { type: 'image/jpeg', lastModified: file.lastModified });
}

/** Larger than the server takes (in MB, as the field says)? */
export const overLimit = (file: File, mb: number): boolean => file.size > mb * 1024 * 1024;
