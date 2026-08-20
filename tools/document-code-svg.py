#!/usr/bin/env python3
"""Generate QR or Code128 SVG for Document Studio when optional Python libraries are available."""
import base64
import io
import sys


def decode_value(encoded: str) -> str:
    """Decode the base64url-compatible input value supplied by PHP."""
    padding = "=" * ((4 - len(encoded) % 4) % 4)
    return base64.urlsafe_b64decode(encoded + padding).decode("utf-8")


def qr_svg(value: str) -> str:
    """Return a standards-compliant QR SVG using the installed qrcode package."""
    import qrcode
    import qrcode.image.svg
    image = qrcode.make(value, image_factory=qrcode.image.svg.SvgPathImage, border=2)
    stream = io.BytesIO()
    image.save(stream)
    return stream.getvalue().decode("utf-8")


def barcode_svg(value: str) -> str:
    """Return a Code128 SVG using ReportLab's barcode renderer."""
    from reportlab.graphics import renderSVG
    from reportlab.graphics.barcode import createBarcodeDrawing
    drawing = createBarcodeDrawing("Code128", value=value, barHeight=38, humanReadable=True)
    rendered = renderSVG.drawToString(drawing)
    return rendered.decode("utf-8") if isinstance(rendered, bytes) else rendered


def main() -> int:
    """Parse CLI arguments and emit SVG to stdout without writing persistent files."""
    if len(sys.argv) != 3 or sys.argv[1] not in {"qr", "barcode"}:
        return 2
    value = decode_value(sys.argv[2])
    sys.stdout.write(qr_svg(value) if sys.argv[1] == "qr" else barcode_svg(value))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
