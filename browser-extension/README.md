# WorkIntel Browser Tracker

The browser tracker is server-agnostic. The user enters the Workforce Intelligence **server base URL** at enrollment time and the extension requests permission only for that origin. Use a value such as `https://team.company.com`, not an API route. If a WorkIntel desktop/browser enrollment endpoint is pasted, the popup safely reduces it to the server origin before requesting permission or enrolling.

## Chromium (Chrome / Edge)

Load `browser-extension/` as an unpacked Manifest V3 extension. The Chromium manifest uses a service worker.

## Firefox

Load `browser-extension/firefox/manifest.json` from `about:debugging` during development. The Firefox build uses a non-persistent Manifest V3 background script and includes a Gecko extension ID/data-collection declaration for signing readiness.

## Privacy contract

The extension sends domain-only sessions. It does not send URL paths, query strings, fragments, page content, form values, clipboard values, passwords or typed text. Incognito/private tabs are ignored.

## Any deployment domain

The extension has no baked-in server domain. At enrollment enter, for example:

- `https://team.company.com`
- `https://time.example.net:8443`
- `http://192.168.1.20:8080` for an internal development server

The requested optional host permission is scoped to that exact server origin.

## Unified enrollment codes (Milestone 13)

The Browser Tracker accepts both `WB-...` browser codes and `WI-...` Devices & Agents codes. A WI code may be used once by a browser and once by a desktop agent before the code expires; the two uses are tracked separately. Reload the unpacked extension after updating its source.
