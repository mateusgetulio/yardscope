# YardScope

Turn a homeowner's sentence and two to four yard photos into a job scope a marketplace can price, correct and book, with a pre-visit brief for the pro.

![Pipeline](docs/pipeline.svg)

Not affiliated with any lawn-care or home-services company. Rates are synthetic. Built as a one-day prototype to explore one question: how far can a vision model take a "manual quote" job before a human has to look?

## What it does

1. **Request.** The customer writes what they need ("My backyard is a mess. Clean it up and trim whatever needs trimming.") and adds two to four photos. Photos are re-encoded so no metadata survives, with the EXIF orientation applied first.
2. **Observation.** A vision model reports what the photos show, through a strict schema: one service line per service and yard section, with the photo and note that support it, which photo it counted from, the view of each photo, a possible narrow gate, hazards, and what it could not judge. It never prices, never measures, never decides readiness.
3. **Readiness gate.** Deterministic rules decide, per line, whether the work can be priced from these photos (`priceable`), needs a specific photo (`needs_photos`, with the exact request), has to be seen by a pro (`manual_quote`: large shrubs, large or uncertain branches, hazards), was seen but not asked for (`suggested`), or could not be read (`rejected`). The rules have a fixed precedence, so there is no tie-break to invent.
4. **Price and correct.** A synthetic rate card prices the priceable lines only. The customer can remove lines, add suggestions, and correct counts, sizes and severities within bounds; every correction is re-gated by the same rules (a shrub corrected to large becomes a pro quote) and stored with the value the customer was looking at and their reason.
5. **Book and brief.** "Book this job, $165" when everything is priced, "Book priced work, $165" when some lines are gated, with the gated lines named under the price. The pro reads a brief built from the scope alone: every value tagged as seen in the photos or corrected by the customer, evidence per photo, access flags, open questions, and three actions (accept the scope, request a photo, adjust the quote).

A collapsible panel on the result page and the Pro View shows what each stage produced for the request: the photos as described, the extraction runs, validation rejections, every line's rule checks, the pricing arithmetic, and the correction history.

## The worked example

Three photos of a medium backyard, sentence above. The model sees a heavy cleanup, four medium shrubs counted from photo 1, and one large fallen branch it cannot judge from photo 2, plus a side gate that looks narrow.

| Line | Disposition | Why |
|---|---|---|
| Yard cleanup, backyard, heavy | priceable | wide view of the section, evidence on photo 1 |
| Shrub trimming, backyard, 4 medium | priceable | one counting view, size within range |
| Branch removal, backyard, 1 large | manual quote | large and uncertain branches are quoted on site |

Result: **partial**, $165 for 2 to 3.5 hours of estimated work including a $29 visit fee, "Branch removal is not included. A pro will quote it separately." The arithmetic is in the panel: 2.3 to 3.225 hours, midpoint at $48 per hour plus the visit fee, rounded up to the next $5.

## Invariants

Nine invariants run over 1,500 seeded random scopes on every test run (`SCOPE_TEST_SEED=<n> vendor/bin/pest --filter='INV-5\b'` reruns one with a chosen seed):

- INV-1 no line is priced without valid evidence; observed counts have exactly one counting view.
- INV-2 only validated scopes reach pricing; unknown types and rejected lines never do.
- INV-3 lines that need photos, a pro, or were only suggested never contribute to the price.
- INV-4 pricing is deterministic.
- INV-5 adding priceable work or raising a count, size or severity within range never lowers the price.
- INV-6 corrections stay within bounds; a correction that crosses a rule boundary changes the disposition instead of being priced.
- INV-7 the pro brief mentions only what the scope contains, and every value carries its origin.
- INV-8 access notes and suggested lines never change the price.
- INV-9 readiness is a pure function of the dispositions, and a photo that satisfies a failed rule never makes a line worse.

## Evals

The model is not covered by invariants; it is measured. `evals/sets/<name>/labels.json` labels a photo set with the expected lines, counts, counting photo, dispositions, readiness, unusable photos and photo request. `php artisan yardscope:eval --live` runs every set through the configured extractor, records each answer, and writes `evals/results/<date>.json` with service precision and recall, count accuracy (exact and within one), schema-valid rate, hallucinated lines, and disposition, readiness and photo-request accuracy. `--fixtures` replays the recorded answers, which is what CI runs, and reproduces the live numbers exactly. The `/evals` page shows the latest file as written; nothing is rounded up or edited.

Twelve labeled sets live in `evals/sets`, one per scenario from the plan (happy path twice, cleanup plus shrubs twice, missing wide shot twice, requested service unseen, unusable photo, branch uncertainty, manual-only service, hallucination trap, multiple sections). The photos are openly licensed images found through Openverse; every title, creator, license and source is in `evals/LICENSES.md`. Several sets combine photos of different properties and say so in their labels.

Two live runs were made on 2026-09-14 through the Claude Code driver, both kept in `evals/results` as written:

| Metric | First run | Second run |
|---|---|---|
| Schema-valid answers | 12/12 | 12/12 |
| Service precision / recall | 0.81 / 0.90 | 1.00 / 1.00 |
| Counts exact / within one | 0.80 / 0.80 | 1.00 / 1.00 |
| Hallucinated lines | 4 | 0 |
| Severity / size correct | 0.89 / 0.25 | 0.89 / 0.75 |
| Dispositions correct | 0.47 | 0.89 |
| Request readiness correct | 7/12 | 10/12 |
| Photo request correct | 0.50 | 1.00 |
| Unusable photos flagged | 0.92 | 0.92 |

The first run found a real problem: the model reported ordinary overhead power lines as hazards in five sets, and rule R5 then sent whole sections to a pro quote. The instruction was tightened to name what makes the work itself unsafe, and three labels were corrected to the pipeline's own conventions (a requested service no photo shows is a placeholder without a section). The second run is what the model does now. Its two readiness misses are honest disagreements: it calls the five hibiscus shrubs large where the label says medium, so that line goes to a pro, and it rates the storm-debris cleanup moderate instead of heavy while flagging the fallen limb leaning on a pergola as a hazard.

## Running it

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
php artisan migrate
npm run build
php artisan serve
```

Everything runs on recorded model answers by default (`YARDSCOPE_EXTRACTOR=fixtures`), so the tests and CI need no key. Two live drivers exist:

- `YARDSCOPE_EXTRACTOR=api` sends the photos through the Laravel AI SDK to the configured provider (`YARDSCOPE_AI_PROVIDER`, `YARDSCOPE_AI_MODEL`, default Anthropic with `ANTHROPIC_API_KEY`).
- `YARDSCOPE_EXTRACTOR=claude-code` runs the same instructions and schema through the local Claude Code CLI in print mode on the developer's own session, for development without an API key. The CLI reads the photos with its own tool and answers through structured output; the repair attempt resumes the same session.

`php artisan yardscope:record "<sentence>" photo1.jpg photo2.jpg` records one live answer as a fixture keyed by the photo bytes and the sentence.

Checks: `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`, `vendor/bin/pest`, `npm run types:check`, `npm run lint`, `npm run format:check`.

## How it was built

- **Stack.** Laravel 13 on PHP 8.4, Inertia with React 19 and TypeScript, Vite, Tailwind, Pest, Larastan at level 7, Pint. SQLite with four tables: requests, observation runs, correction records and pro actions. The scope itself is never stored; it is rebuilt from the latest observation plus the replayed corrections on every load, so what the customer sees is always what the domain computes.
- **Domain first.** `app/Scoping` has no framework dependency, enforced by an architecture test: final readonly value objects, backed enums, integer cents, domain exceptions. The readiness rules, pricing, corrections and the pro brief are all there and all unit tested.
- **Extraction.** The observation schema and the prompt live in one agent class. The Laravel AI SDK version used (0.11) has no `AgentFake` class despite the docs; the fake is `Ai::fakeAgent()`, and the driver tests use it. The Claude Code driver was verified live; the API driver was built against the SDK's fake and not exercised against a provider, because no API key was available while building.
- **Fixtures as the test seam.** Feature tests re-encode the same bytes the app stores and key the recording on them, so the fixture lookup is exercised honestly rather than mocked.
- **Repair, then refuse.** An observation that fails the schema gets one repair attempt with the parser's own message; a second failure lands the request on a pro quote, never on a guess. A provider failure is stored on the run and the customer sees one plain sentence; the raw error stays on the Pro View.
- **What would change in production.** Analysis would run on a queue with a timeout instead of inside the request; the property profile would come from an address lookup instead of config; the rate card would be real; photos would go to object storage; the pro actions would notify someone.

## Layout

```
app/Scoping        domain: observation parsing, readiness gate, pricing, corrections, pro brief
app/Extraction     vision extractors: fixtures, AI SDK agent, Claude Code CLI
app/Intake         photo store, analyzer, scope assembler
app/Evals          labeled sets, scorer, metrics, runner
app/Http           controllers, form requests, presenters
resources/js       Inertia pages: request, result, booked, pro, evals; the pipeline panel
tests/Unit         domain tests and the invariants
tests/Feature      the customer flow, the pro view, the drivers, the commands
evals/             labeled sets, recorded answers, results
```
