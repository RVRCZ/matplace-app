"""Reads a QR code off a picture the way a phone would (OpenCV's detector). The tests use it to confirm that the code
on a rendered sign carries what the form said; without OpenCV it says so and the test is skipped.

    python qr_decode.py <picture.png>  →  {"ok": true, "text": "..."}  or  {"ok": false, "error": "..."}
"""
import json
import sys


def read(img):
    """The detector is particular about scale: the picture is tried as it is and enlarged, with the plain and the
    ArUco-based detector, until one of them reads the code."""
    import cv2
    detectors = [cv2.QRCodeDetector()]
    if hasattr(cv2, "QRCodeDetectorAruco"):
        detectors.append(cv2.QRCodeDetectorAruco())
    for scale in (1, 2, 3, 0.5):
        big = img if scale == 1 else cv2.resize(img, None, fx=scale, fy=scale, interpolation=cv2.INTER_NEAREST)
        for det in detectors:
            text, _, _ = det.detectAndDecode(big)
            if text:
                return text
    return ""


def main(argv):
    try:
        if len(argv) < 2:
            raise ValueError("usage: qr_decode.py <picture>")
        import cv2
        img = cv2.imread(argv[1], cv2.IMREAD_GRAYSCALE)
        if img is None:
            raise ValueError("cannot read " + argv[1])
        # a phone sees the code with room around it: white all round, whatever the picture's own margin
        pad = max(16, img.shape[0] // 8)
        img = cv2.copyMakeBorder(img, pad, pad, pad, pad, cv2.BORDER_CONSTANT, value=255)
        text = read(img)
        if not text:
            raise ValueError("no code found")
        print(json.dumps({"ok": True, "text": text}))     # ASCII-escaped: a Windows console may not take the letters as they are
    except Exception as e:  # noqa: BLE001 - one JSON object whatever happened
        print(json.dumps({"ok": False, "error": str(e)}))


if __name__ == "__main__":
    main(sys.argv)
