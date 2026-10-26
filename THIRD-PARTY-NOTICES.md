# Third-party notices

Prevodka Lite has no runtime dependencies beyond PHP's own extensions (dom, mbstring).

## ISO 20022 schema (tests only)

`tests/xsd/pain.001.001.09.xsd` is the published ISO 20022 message definition for the payment initiation
message pain.001.001.09. It is used only by the test suite to check that every file Prevodka Lite writes is
valid against the standard, and it is not part of the Composer archive (`/tests` is export-ignored). The schema
remains the work of its publisher (ISO 20022 Registration Authority and the standards editors named in the
file's header comment); no licence terms are added or removed by PN Scripts. The copy was taken from the test
fixtures of the MIT-licensed php-sepa-xml project.
