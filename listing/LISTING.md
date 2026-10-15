# Prevodka Lite: Packagist submission

Status 2026-10-15: the repository is **public**, topics set, GitHub release https://github.com/pnscripts/php-prevodka-lite/releases/tag/v1.0.0 (tag `v1.0.0`). Not yet on Packagist (needs a browser sign-in). Packagist account `PNScripts` already exists.

1. Done 2026-10-15: repo public, `main` and `v1.0.0` pushed.
2. In the browser, signed in to Packagist as `PNScripts` (GitHub login): https://packagist.org/packages/submit → repository URL `https://github.com/pnscripts/php-prevodka-lite` → Check → Submit. Vendor `pnscripts` is already ours.
3. Auto-update: the GitHub hook from the connected Packagist account, or the webhook `https://packagist.org/api/github?username=PNScripts` (JSON, push event).
4. GitHub repo settings: description = composer description, topics `sepa pain001 iso20022 iban php bulgaria`, social preview `listing/social-preview-1280x640.png`.
5. On pnscripts.com: Prevodka's product page, "Get it on" → Packagist, edition lite, URL `https://packagist.org/packages/pnscripts/prevodka-lite` once live.

Checks run: `composer validate --strict` passes; 11 PHPUnit tests (output validated against the ISO 20022 pain.001.001.09 schema). Versions come from tags (no `version` field). The dist ZIP excludes tests/listing/tools via .gitattributes.

Lite scope: building pain.001.001.09 in PHP code with IBAN/BIC/amount checks, transliteration and exact sums. The full edition (Coming soon) keeps Excel/CSV lists, the command and web page, pain.001.001.03, SEPA Instant, RF references, TARGET days, duplicate warnings and the file reader.
