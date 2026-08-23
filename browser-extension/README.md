# WorkIntel Browser Tracker

WorkIntel Browser Tracker 1.0.1 uses a **server-bound deployment package** when downloaded from the authenticated WorkIntel Downloads & Installation Center. The application writes only the current WorkIntel origin into `workintel-server.txt` inside the downloaded copy. The one-time enrollment code is never embedded in the package.

The extension popup therefore asks the end user for only the enrollment code. It reads the configured server origin from the package, requests browser host permission only for that exact origin, and calls `/api/v1/browser/enroll` through the existing background enrollment contract.

The canonical published browser ZIP remains server-agnostic and immutable. WorkIntel creates a temporary server-bound copy at download time rather than rewriting the canonical release bytes or checksum.

## Chromium (Chrome / Edge)

Download the Chrome / Edge package from WorkIntel, extract it, then load the extracted folder as an unpacked Manifest V3 extension. The Chromium manifest uses a service worker.

## Firefox

Download the Firefox package from WorkIntel, extract it, then load `manifest.json` from `about:debugging` during development. The Firefox build uses a non-persistent Manifest V3 background script and includes a Gecko extension ID/data-collection declaration for signing readiness.

## Enrollment

1. Generate a one-time enrollment code in WorkIntel Downloads or Devices & Agents.
2. Open the installed extension popup.
3. Confirm the read-only configured server shown by the popup.
4. Enter only the enrollment code and choose **Connect browser**.

A `WI-...` unified code may be used once by a browser and once by a desktop agent before it expires; browser-specific `WB-...` codes remain supported by the enrollment API.

## Privacy contract

The extension sends domain-only sessions. It does not send URL paths, query strings, fragments, page content, form values, clipboard values, passwords or typed text. Incognito/private tabs are ignored.

## Raw canonical package fallback

The canonical release artifact is intentionally not bound to any tenant/server because it also serves immutable release and supply-chain verification. Normal users should download through WorkIntel. Operators working directly with a raw canonical artifact must add a valid `workintel-server.txt` at the package root before loading it; no enrollment secret belongs in that file.
