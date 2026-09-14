# Invariants

Nine invariants run over 1,500 seeded random scopes on every test run. `SCOPE_TEST_SEED=<n> vendor/bin/pest --filter='INV-5\b'` reruns one with a chosen seed, and a failure prints the seed.

- **INV-1** No line is priced without valid evidence; observed counts have exactly one counting view. A customer-corrected count may exceed the observed one only on a line with valid evidence and an explicit correction record.
- **INV-2** Only validated scopes reach pricing; unknown types and rejected lines never do.
- **INV-3** Lines that need photos, need a pro, or were only suggested never contribute to the price or the hours.
- **INV-4** Pricing is deterministic: the same scope, profile and rate card always give the same estimate.
- **INV-5** Adding a priceable line, or raising a count, a size or a severity within the priceable range, never lowers the price or the hours.
- **INV-6** Corrections stay within domain bounds; a correction that crosses a rule boundary changes the disposition (a shrub corrected to large becomes a pro quote) instead of being priced.
- **INV-7** The pro brief mentions only services, counts, notes and questions present in the scope, and every value carries its origin.
- **INV-8** Access notes and suggested lines never change the price.
- **INV-9** Request readiness is a pure function of the line dispositions, and a photo that satisfies a failed rule never makes any line's disposition worse.

Two deliberate sabotages were used to check that the invariants bite: removing the large-shrub rule fails INV-6, and pricing every line fails INV-3.
